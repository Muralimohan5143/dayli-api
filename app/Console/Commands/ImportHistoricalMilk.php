<?php

namespace App\Console\Commands;

use App\Services\Imports\HistoricalImporterService;
use App\Services\InvoiceGeneratorService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportHistoricalMilk extends Command
{
    protected $signature = '
        milk:historical-import
        {file : Path to workbook}
        {--zone_id=1 : Zone assigned to imported subscription workflow}
        {--by_user_id=1 : Staff/system user creating the SCR}
        {--subscription_type_id=3 : Milk subscription type ID}
        {--vendor_id= : Optional vendor ID for Draft Order Items}
        {--dry-run : Parse only. Do not write database}
    ';

    protected $description =
    'Import historical milk workbook through the normal subscription, daily-order and invoice flow';

    private const IMPORT_SOURCE = 'milk_historical_h1_2026';
    private const CONTINUATION_START_DATE = '2026-07-01';
    private const CONTINUATION_END_DATE = '2026-07-19';

    private HistoricalImporterService $importer;

    public function handle(
        HistoricalImporterService $importer,
        InvoiceGeneratorService $invoiceGenerator
    ): int {
        $this->importer = $importer;

        $file = (string) $this->argument('file');

        if (!is_file($file)) {
            $this->error('Workbook not found:');
            $this->line($file);

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Reading workbook...');
        $this->newLine();

        $preview = $this->importer->preview($file);
        $summary = $preview['summary'] ?? [];
        $customers = $preview['customers'] ?? [];

        if ($this->option('dry-run')) {
            return $this->showDryRun($summary, $customers);
        }

        if (empty($customers)) {
            $this->warn('No importable customers were found in the workbook.');

            return self::SUCCESS;
        }

        $zoneId = (int) $this->option('zone_id');
        $subscriptionTypeId = (int) $this->option('subscription_type_id');

        [$rangeStart, $rangeEnd] = $this->resolveHistoricalRange($customers);

        /*
         * Excel contains Jan-Jun history, but the imported subscription
         * state must continue through 19-Jul-2026. Historical July orders
         * are generated from the final active June subscription state.
         */
        $rangeEnd = self::CONTINUATION_END_DATE;

        if (!$rangeStart || !$rangeEnd) {
            $this->error('Could not determine the historical date range from the workbook.');

            return self::FAILURE;
        }

        try {
            /*
             * PHASE 1
             * Excel -> SCR -> Draft Order -> Draft Order Items only.
             *
             * The importer must not create monthly summary Orders.
             */
            $workflowStats = $this->importSubscriptionWorkflows($customers);

            /*
             * PHASE 2
             * Use the real production generator to create one order per
             * customer/date and the actual daily order items.
             */
            $this->newLine();
            $this->info("Generating daily orders from {$rangeStart} to {$rangeEnd}...");

            $exitCode = Artisan::call('dayli:generate-daily-orders', [
                '--from' => $rangeStart,
                '--to' => $rangeEnd,
            ]);

            $generatorOutput = trim(Artisan::output());
            if ($generatorOutput !== '') {
                $this->line($generatorOutput);
            }

            if ($exitCode !== self::SUCCESS) {
                throw new \RuntimeException(
                    'GenerateDailyOrders failed with exit code ' . $exitCode
                );
            }

            /*
             * PHASE 3
             * Use InvoiceGeneratorService for the normal monthly aggregation:
             * order_items -> delivery count/current dues -> invoice items.
             *
             * After each invoice is generated, historical finance values are
             * applied as follows:
             * - delivery fee: Excel
             * - amount paid: Excel
             * - first previous due: Excel opening balance
             * - later previous due: previous calculated closing balance
             * - total/closing due: calculated by the system
             */
            $invoiceStats = $this->generateAndApplyHistoricalInvoices(
                $customers,
                $invoiceGenerator,
                $zoneId,
                $subscriptionTypeId
            );

            $this->newLine();
            $this->info('Historical import completed.');

            $this->table(
                ['Result', 'Count'],
                [
                    ['SCR Created', $workflowStats['created_scr']],
                    ['Draft Orders Created', $workflowStats['created_draft_orders']],
                    ['Draft Order Items Created', $workflowStats['created_draft_order_items']],
                    ['Existing DOI Segments Skipped', $workflowStats['skipped_existing_draft_order_items']],
                    ['Customers Without User Skipped', $workflowStats['skipped_no_user']],
                    ['Invoices Processed', $invoiceStats['invoices_processed']],
                    ['Historical Payments Created', $invoiceStats['payments_created']],
                    ['Existing Payments Skipped', $invoiceStats['payments_skipped']],
                    ['Invoice Rows Missing', $invoiceStats['invoices_missing']],
                    ['Closing-Due Mismatches', $invoiceStats['closing_due_mismatches']],
                ]
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('Historical import failed.');
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function importSubscriptionWorkflows(array $customers): array
    {
        $stats = [
            'created_scr' => 0,
            'created_draft_orders' => 0,
            'created_draft_order_items' => 0,
            'skipped_existing_draft_order_items' => 0,
            'skipped_no_user' => 0,
        ];

        DB::transaction(function () use ($customers, &$stats): void {
            $this->newLine();
            $this->info('Creating subscription history...');

            $now = now();

            foreach ($customers as $customer) {
                $customerId = $customer['user_id'] ?? null;

                if (!$customerId) {
                    $stats['skipped_no_user']++;
                    continue;
                }

                $workflow = $this->createSubscriptionWorkflow($customer, $now);

                $stats['created_scr'] += $workflow['created_scr'];
                $stats['created_draft_orders'] += $workflow['created_draft_order'];
                $stats['created_draft_order_items'] += $workflow['created_draft_order_items'];
                $stats['skipped_existing_draft_order_items'] +=
                    $workflow['skipped_existing_draft_order_items'];
            }
        });

        return $stats;
    }

    private function generateAndApplyHistoricalInvoices(
        array $customers,
        InvoiceGeneratorService $invoiceGenerator,
        int $zoneId,
        int $subscriptionTypeId
    ): array {
        $stats = [
            'invoices_processed' => 0,
            'payments_created' => 0,
            'payments_skipped' => 0,
            'invoices_missing' => 0,
            'closing_due_mismatches' => 0,
        ];

        $monthPayloads = $this->buildMonthPayloads($customers);
        ksort($monthPayloads);

        /* Running closing balance per customer. */
        $customerClosingBalances = [];

        foreach ($monthPayloads as $month => $customersForMonth) {
            $monthStart = Carbon::createFromFormat('Y-m-d', $month . '-01')
                ->startOfMonth();
            $monthEndExclusive = $monthStart->copy()->addMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();

            $this->newLine();
            $this->info("Generating invoice aggregation for {$month}...");

            $invoiceGenerator->generateForReport(
                $zoneId,
                $subscriptionTypeId,
                $monthStart->toDateString(),
                $monthEndExclusive->toDateString()
            );

            foreach ($customersForMonth as $customerId => $sheetFinance) {
                $invoiceNumber = sprintf(
                    'MILK-%s-Z%s-S%s-U%s',
                    str_replace('-', '', $month),
                    $zoneId,
                    $subscriptionTypeId,
                    $customerId
                );

                $invoice = DB::table('invoices')
                    ->where('number', $invoiceNumber)
                    ->first();

                if (!$invoice) {
                    $stats['invoices_missing']++;
                    $this->warn("Invoice not found after generation: {$invoiceNumber}");
                    continue;
                }

                /*
 * Keep the production-generated subtotal for audit/comparison.
 */
                $calculatedSubtotal = round(
                    (float) ($invoice->subtotal ?? 0),
                    2
                );

                /*
 * Historical invoice financial value must match Excel Current Dues.
 */
                $subtotal = round(
                    (float) $sheetFinance['sheet_current_dues'],
                    2
                );

                /* Historical delivery fee and payment come directly from Excel. */
                $deliveryFee = round(
                    (float) $sheetFinance['delivery_fee'],
                    2
                );

                $amountPaid = round(
                    (float) $sheetFinance['amount_paid'],
                    2
                );

                if (!array_key_exists($customerId, $customerClosingBalances)) {
                    /* First imported month opening balance comes from Excel. */
                    $previousDues = round(
                        (float) $sheetFinance['sheet_previous_dues'],
                        2
                    );
                } else {
                    /* Later months carry the previous calculated closing balance. */
                    $previousDues = round(
                        (float) $customerClosingBalances[$customerId],
                        2
                    );
                }

                $currentMonthTotal = round($subtotal + $deliveryFee, 2);
                $grandTotal = round($previousDues + $currentMonthTotal, 2);
                $calculatedClosingDues = round(
                    max(0, $grandTotal - $amountPaid),
                    2
                );

                $sheetClosingDues = round(
                    (float) $sheetFinance['sheet_closing_dues'],
                    2
                );

                $closingDifference = round(
                    $calculatedClosingDues - $sheetClosingDues,
                    2
                );

                if (abs($closingDifference) > 0.01) {
                    $stats['closing_due_mismatches']++;
                }

                $paymentStatus = match (true) {
                    $calculatedClosingDues <= 0 => 'paid',
                    $amountPaid > 0 => 'partial',
                    default => 'unpaid',
                };

                $meta = $this->decodeJsonObject($invoice->meta ?? null);
                $meta = array_merge($meta, [
                    'import_source' => self::IMPORT_SOURCE,
                    'historical_import' => true,
                    'month' => $month,
                    'delivery_fee_source' => 'excel',
                    'sheet_delivery_count' => $sheetFinance['sheet_delivery_count'],
                    'calculated_delivery_count' => $this->calculateMonthlyDeliveryCount(
                        (int) $customerId,
                        $monthStart->toDateString(),
                        $monthEndExclusive->toDateString()
                    ),
                    'sheet_delivery_fee' => $deliveryFee,
                    'sheet_current_dues' => $sheetFinance['sheet_current_dues'],
                    'calculated_current_dues' => $calculatedSubtotal,
                    'applied_current_dues' => $subtotal,
                    'current_dues_source' => 'excel',
                    'sheet_previous_dues' => $sheetFinance['sheet_previous_dues'],
                    'applied_previous_dues' => $previousDues,
                    'sheet_amount_paid' => $amountPaid,
                    'sheet_closing_dues' => $sheetClosingDues,
                    'calculated_closing_dues' => $calculatedClosingDues,
                    'closing_due_difference' => $closingDifference,
                ]);

                DB::transaction(function () use (
                    $invoice,
                    $subtotal,
                    $deliveryFee,
                    $previousDues,
                    $currentMonthTotal,
                    $grandTotal,
                    $calculatedClosingDues,
                    $paymentStatus,
                    $meta,
                    $amountPaid,
                    $monthEnd,
                    &$stats
                ): void {
                    DB::table('invoices')
                        ->where('id', $invoice->id)
                        ->update([
                            'subtotal' => $subtotal,
                            'Unpaid_dues' => $previousDues,
                            'delivery_fee' => $deliveryFee,
                            'total' => $currentMonthTotal,
                            'grand_total' => $grandTotal,
                            'payment_status' => $paymentStatus,
                            'meta' => json_encode($meta),
                            'updated_at' => now(),
                        ]);

                    if ($amountPaid > 0) {
                        $created = $this->storeHistoricalPayment(
                            (int) $invoice->id,
                            (int) $invoice->user_id,
                            $amountPaid,
                            $monthEnd->toDateString()
                        );

                        if ($created) {
                            $stats['payments_created']++;
                        } else {
                            $stats['payments_skipped']++;
                        }
                    }
                });

                $customerClosingBalances[$customerId] = $calculatedClosingDues;
                $stats['invoices_processed']++;
            }
        }

        return $stats;
    }

    private function buildMonthPayloads(array $customers): array
    {
        $result = [];

        foreach ($customers as $customer) {
            $customerId = isset($customer['user_id'])
                ? (int) $customer['user_id']
                : 0;

            if ($customerId <= 0) {
                continue;
            }

            foreach (($customer['months'] ?? []) as $month => $monthData) {
                $rows = $monthData['rows'] ?? [];

                if (empty($rows)) {
                    continue;
                }

                $result[$month][$customerId] = [
                    'sheet_delivery_count' => $this->maximumMoney($rows, 'delivery_count'),
                    'delivery_fee' => $this->maximumMoney($rows, 'delivery_fee'),
                    'sheet_current_dues' => $this->maximumMoney($rows, 'this_month_dues'),
                    'sheet_previous_dues' => $this->maximumMoney($rows, 'previous_dues'),
                    'amount_paid' => $this->maximumMoney($rows, 'payment'),
                    'sheet_closing_dues' => $this->maximumMoney($rows, 'closing_dues'),
                ];
            }
        }

        return $result;
    }

    private function calculateMonthlyDeliveryCount(
        int $customerId,
        string $monthStart,
        string $monthEndExclusive
    ): int {
        return (int) round((float) DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->where('o.customer_id', $customerId)
            ->whereNotNull('oi.actuals_date')
            ->where('oi.actuals_date', '>=', $monthStart)
            ->where('oi.actuals_date', '<', $monthEndExclusive)
            ->sum('oi.quantity'));
    }

    /**
     * Store the Excel payment in inward_payments without assuming one exact
     * historical schema. Only columns that really exist are inserted.
     */
    private function storeHistoricalPayment(
        int $invoiceId,
        int $userId,
        float $amount,
        string $paymentDate
    ): bool {
        if (!Schema::hasTable('inward_payments')) {
            throw new \RuntimeException(
                'inward_payments table is required to import historical Amount Paid values.'
            );
        }

        $columns = Schema::getColumnListing('inward_payments');

        $query = DB::table('inward_payments')
            ->where('invoice_id', $invoiceId)
            ->where('amount', $amount);

        if (in_array('payment_date', $columns, true)) {
            $query->whereDate('payment_date', $paymentDate);
        }

        if ($query->exists()) {
            return false;
        }

        $data = [];

        $this->putExistingColumn($data, $columns, 'invoice_id', $invoiceId);
        $this->putExistingColumn($data, $columns, 'user_id', $userId);
        $this->putExistingColumn($data, $columns, 'customer_id', $userId);
        $this->putExistingColumn($data, $columns, 'amount', $amount);
        $this->putExistingColumn($data, $columns, 'payment_date', $paymentDate);
        $this->putExistingColumn($data, $columns, 'payment_method', 'historical_excel');
        $this->putExistingColumn($data, $columns, 'method', 'historical_excel');
        $this->putExistingColumn($data, $columns, 'status', 'received');
        $this->putExistingColumn($data, $columns, 'reference', self::IMPORT_SOURCE);
        $this->putExistingColumn($data, $columns, 'reference_number', self::IMPORT_SOURCE);
        $this->putExistingColumn($data, $columns, 'notes', 'Historical payment imported from milk workbook');
        $this->putExistingColumn($data, $columns, 'meta', json_encode([
            'import_source' => self::IMPORT_SOURCE,
            'historical_import' => true,
            'payment_date_assumed_month_end' => true,
        ]));
        $this->putExistingColumn($data, $columns, 'created_at', now());
        $this->putExistingColumn($data, $columns, 'updated_at', now());

        if (!isset($data['invoice_id']) || !isset($data['amount'])) {
            throw new \RuntimeException(
                'inward_payments must contain invoice_id and amount columns.'
            );
        }

        DB::table('inward_payments')->insert($data);

        return true;
    }

    private function putExistingColumn(
        array &$data,
        array $columns,
        string $column,
        mixed $value
    ): void {
        if (in_array($column, $columns, true)) {
            $data[$column] = $value;
        }
    }

    private function decodeJsonObject(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function resolveHistoricalRange(array $customers): array
    {
        $first = null;
        $last = null;

        foreach ($customers as $customer) {
            foreach (($customer['months'] ?? []) as $month => $monthData) {
                try {
                    $monthStart = Carbon::createFromFormat('Y-m-d', $month . '-01')
                        ->startOfMonth();
                } catch (\Throwable) {
                    continue;
                }

                $monthEnd = !empty($monthData['payment_date'])
                    ? Carbon::parse($monthData['payment_date'])
                    : $monthStart->copy()->endOfMonth();

                if ($first === null || $monthStart->lt($first)) {
                    $first = $monthStart->copy();
                }

                if ($last === null || $monthEnd->gt($last)) {
                    $last = $monthEnd->copy();
                }
            }
        }

        return [
            $first?->toDateString(),
            $last?->toDateString(),
        ];
    }

    private function showDryRun(array $summary, array $customers): int
    {
        $this->newLine();
        $this->info('DRY RUN SUCCESS');

        $this->table(
            ['Metric', 'Value'],
            [
                ['Customers', count($customers)],
                ['Monthly Groups', $summary['monthly_groups'] ?? 0],
                ['Rows Accepted', $summary['rows_accepted'] ?? 0],
                ['Rows Skipped', $summary['rows_skipped'] ?? 0],
                ['Users Matched', $summary['users_matched'] ?? 0],
                ['Users Missing', $summary['users_missing'] ?? 0],
                ['Products Mapped', $summary['products_mapped'] ?? 0],
                ['Products Unmapped', count($summary['products_unmapped'] ?? [])],
                ['Total Delivered Qty', $summary['total_delivered_quantity'] ?? 0],
                ['Zero Delivery Rows', $summary['zero_delivery_rows'] ?? 0],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * Customer -> approved SCR -> Draft Order -> historical DOI segments.
     */
    private function createSubscriptionWorkflow(array $customer, $now): array
    {
        $customerId = (int) $customer['user_id'];
        $zoneId = (int) $this->option('zone_id');
        $byUserId = (int) $this->option('by_user_id');
        $subscriptionTypeId = (int) $this->option('subscription_type_id');

        $vendorOption = $this->option('vendor_id');
        $vendorId = $vendorOption !== null && $vendorOption !== ''
            ? (int) $vendorOption
            : null;

        $result = [
            'draft_order_id' => null,
            'created_scr' => 0,
            'created_draft_order' => 0,
            'created_draft_order_items' => 0,
            'skipped_existing_draft_order_items' => 0,
        ];

        $plans = $this->importer->buildSubscriptionPlans($customer);

        if (empty($plans)) {
            return $result;
        }

        $sourceMeta = [
            'import_source' => self::IMPORT_SOURCE,
            'historical_import' => true,
            'customer_name' => $customer['customer_name'] ?? null,
            'phone' => $customer['phone'] ?? null,
        ];

        $scr = DB::table('sub_change_requests')
            ->where('for_user_id', $customerId)
            ->where('subscription_type_id', $subscriptionTypeId)
            ->first();

        if (!$scr) {
            $scrId = DB::table('sub_change_requests')->insertGetId([
                'for_user_id' => $customerId,
                'by_user_id' => $byUserId,
                'party_type' => 'consumer',
                'from_id' => null,
                'draft_order_id' => null,
                'zone_id' => $zoneId,
                'subscription_type_id' => $subscriptionTypeId,
                'subtypes_json' => null,
                'invoice_cycle' => 'monthly',
                'change_reason' => 'staff-error',
                'action' => 'create',
                'status' => 'approved',
                'meta' => json_encode($sourceMeta),
                'payload' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $scr = DB::table('sub_change_requests')->where('id', $scrId)->first();
            $result['created_scr']++;
        }

        $draftOrder = DB::table('draft_orders')
            ->where('change_request_id', $scr->id)
            ->first();

        $firstStartDate = null;
        $firstCadence = 'daily';

        foreach ($plans as $plan) {
            foreach ($plan['segments'] as $segment) {
                if ($firstStartDate === null || $segment['start_date']->lt($firstStartDate)) {
                    $firstStartDate = $segment['start_date']->copy();
                }

                if (!empty($segment['frequency_type'])) {
                    $firstCadence = $segment['frequency_type'];
                    break 2;
                }
            }
        }

        if (!$draftOrder) {
            $draftOrderId = DB::table('draft_orders')->insertGetId([
                'change_request_id' => $scr->id,
                'customer_id' => $customerId,
                'zone_id' => $zoneId,
                'cadence' => $firstCadence,
                'start_date' => $firstStartDate?->toDateString() ?? '2026-01-01',
                'end_date' => null,
                'invoice_cycle' => 'monthly',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $draftOrder = DB::table('draft_orders')->where('id', $draftOrderId)->first();

            DB::table('sub_change_requests')
                ->where('id', $scr->id)
                ->update([
                    'draft_order_id' => $draftOrderId,
                    'updated_at' => $now,
                ]);

            $result['created_draft_order']++;
        } elseif ((int) ($scr->draft_order_id ?? 0) !== (int) $draftOrder->id) {
            DB::table('sub_change_requests')
                ->where('id', $scr->id)
                ->update([
                    'draft_order_id' => $draftOrder->id,
                    'updated_at' => $now,
                ]);
        }

        $result['draft_order_id'] = (int) $draftOrder->id;

        foreach ($plans as $plan) {
            $mapping = $plan['mapping'];
            $previousCreatedItemId = null;

            foreach ($plan['segments'] as $segment) {
                $startDate = $segment['start_date']->toDateString();
                $endDate = $segment['end_date']?->toDateString();

                $segmentKey = hash('sha256', implode('|', [
                    self::IMPORT_SOURCE,
                    $customerId,
                    $draftOrder->id,
                    $mapping['product_id'],
                    $mapping['variant_id'],
                    $startDate,
                    $endDate ?? 'OPEN',
                    $segment['qty'],
                    $segment['frequency_type'] ?? 'PAUSE',
                    $segment['change_action'],
                ]));

                $existingDoi = DB::table('draft_order_items')
                    ->where('draft_order_id', $draftOrder->id)
                    ->where('product_id', $mapping['product_id'])
                    ->where('variant_id', $mapping['variant_id'])
                    ->whereDate('start_date', $startDate)
                    ->where('meta', 'like', '%' . $segmentKey . '%')
                    ->first();

                if ($existingDoi) {
                    $previousCreatedItemId = $existingDoi->id;
                    $result['skipped_existing_draft_order_items']++;
                    continue;
                }

                $priceSnapshot = $this->importer->resolveVariantPriceForHistoricalDate(
                    (int) $mapping['product_id'],
                    (int) $mapping['variant_id'],
                    $segment['start_date'],
                    isset($mapping['unit_price'])
                        ? (float) $mapping['unit_price']
                        : null
                );

                $doiMeta = [
                    'import_source' => self::IMPORT_SOURCE,
                    'historical_import' => true,
                    'historical_segment_key' => $segmentKey,
                    'customer_name' => $customer['customer_name'] ?? null,
                    'phone' => $customer['phone'] ?? null,
                    'product_name' => $mapping['title'] ?? null,
                    'variant_name' => $mapping['variant'] ?? null,
                    'pattern_start_value' => $segment['pattern_start_value'] ?? null,
                    'pattern_kind' => $segment['pattern_kind'] ?? null,
                    'source_rows' => $plan['source_rows'] ?? [],
                ];

                $doiId = DB::table('draft_order_items')->insertGetId([
                    'original_item_id' => $previousCreatedItemId,
                    'change_action' => $segment['change_action'],
                    'draft_order_id' => $draftOrder->id,
                    'product_id' => (int) $mapping['product_id'],
                    'variant_id' => (int) $mapping['variant_id'],
                    'vendor_id' => $vendorId,
                    'frequency_type' => $segment['frequency_type'],
                    'qty' => (float) $segment['qty'],
                    'unit' => 'pcs',
                    'price_snapshot' => $priceSnapshot,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'status' => (float) $segment['qty'] > 0 ? 'active' : 'paused',
                    'meta' => json_encode($doiMeta),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $previousCreatedItemId = $doiId;
                $result['created_draft_order_items']++;
            }

            /*
             * Continue the customer's final active June subscription state
             * from 01-Jul-2026 through 19-Jul-2026.
             *
             * Important:
             * - This does not invent July Excel quantities.
             * - It carries forward only the last active plan segment.
             * - A paused/zero final segment is not continued.
             */
            $lastSegment = !empty($plan['segments'])
                ? $plan['segments'][array_key_last($plan['segments'])]
                : null;

            if (
                $lastSegment
                && (float) ($lastSegment['qty'] ?? 0) > 0
                && !empty($lastSegment['frequency_type'])
            ) {
                $continuationStart = self::CONTINUATION_START_DATE;
                $continuationEnd = self::CONTINUATION_END_DATE;

                $continuationKey = hash('sha256', implode('|', [
                    self::IMPORT_SOURCE,
                    'july_continuation',
                    $customerId,
                    $draftOrder->id,
                    $mapping['product_id'],
                    $mapping['variant_id'],
                    $continuationStart,
                    $continuationEnd,
                    $lastSegment['qty'],
                    $lastSegment['frequency_type'],
                ]));

                $existingContinuation = DB::table('draft_order_items')
                    ->where('draft_order_id', $draftOrder->id)
                    ->where('product_id', $mapping['product_id'])
                    ->where('variant_id', $mapping['variant_id'])
                    ->whereDate('start_date', $continuationStart)
                    ->whereDate('end_date', $continuationEnd)
                    ->where('meta', 'like', '%' . $continuationKey . '%')
                    ->first();

                if ($existingContinuation) {
                    $result['skipped_existing_draft_order_items']++;
                } else {
                    $priceSnapshot =
                        $this->importer->resolveVariantPriceForHistoricalDate(
                            (int) $mapping['product_id'],
                            (int) $mapping['variant_id'],
                            Carbon::parse($continuationStart),
                            isset($mapping['unit_price'])
                                ? (float) $mapping['unit_price']
                                : null
                        );

                    $continuationMeta = [
                        'import_source' => self::IMPORT_SOURCE,
                        'historical_import' => true,
                        'july_continuation' => true,
                        'historical_segment_key' => $continuationKey,
                        'continued_from_june_state' => true,
                        'customer_name' => $customer['customer_name'] ?? null,
                        'phone' => $customer['phone'] ?? null,
                        'product_name' => $mapping['title'] ?? null,
                        'variant_name' => $mapping['variant'] ?? null,
                        'pattern_start_value' =>
                        $lastSegment['pattern_start_value'] ?? null,
                        'pattern_kind' =>
                        $lastSegment['pattern_kind'] ?? null,
                    ];

                    DB::table('draft_order_items')->insert([
                        'original_item_id' => $previousCreatedItemId,
                        'change_action' => 'modify',
                        'draft_order_id' => $draftOrder->id,
                        'product_id' => (int) $mapping['product_id'],
                        'variant_id' => (int) $mapping['variant_id'],
                        'vendor_id' => $vendorId,
                        'frequency_type' => $lastSegment['frequency_type'],
                        'qty' => (float) $lastSegment['qty'],
                        'unit' => 'pcs',
                        'price_snapshot' => $priceSnapshot,
                        'start_date' => $continuationStart,
                        'end_date' => $continuationEnd,
                        'status' => 'active',
                        'meta' => json_encode($continuationMeta),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $result['created_draft_order_items']++;
                }
            }
        }

        return $result;
    }

    private function maximumMoney(array $rows, string $field): float
    {
        $values = [];

        foreach ($rows as $row) {
            $values[] = round((float) ($row[$field] ?? 0), 2);
        }

        return empty($values) ? 0.0 : max($values);
    }
}
