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
        {--repair-invoices : Repair only existing Jan-Jun invoices and invoice_items}
        {--repair-payments : Repair only historical inward_payments from workbook payment totals}
        {--customer_id= : Optional customer ID filter for repair}
        {--month= : Optional YYYY-MM month filter for invoice repair}
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

        /*
         * PAYMENT REPAIR MODE
         * -------------------
         * Reads the workbook and repairs ONLY historical inward_payments.
         * It does NOT touch invoices, invoice_items, orders, order_items,
         * SCRs, draft_orders or draft_order_items.
         */
        if ($this->option('repair-payments')) {
            if (empty($customers)) {
                $this->warn('No importable customers were found in the workbook.');
                return self::SUCCESS;
            }

            $zoneId = (int) $this->option('zone_id');
            $subscriptionTypeId = (int) $this->option('subscription_type_id');

            if ($this->option('dry-run')) {
                return $this->showHistoricalPaymentRepairDryRun(
                    $customers,
                    $zoneId,
                    $subscriptionTypeId
                );
            }

            return $this->repairHistoricalPaymentsOnly(
                $customers,
                $zoneId,
                $subscriptionTypeId
            );
        }

        /*
         * REPAIR MODE
         * ----------
         * Reads the workbook, but DOES NOT touch:
         * - SCRs
         * - draft_orders / draft_order_items
         * - orders / order_items
         *
         * It only rebuilds invoices + invoice_items from the existing
         * order_items, then reapplies the corrected historical finance
         * values from the workbook.
         */
        if ($this->option('repair-invoices')) {
            if (empty($customers)) {
                $this->warn('No importable customers were found in the workbook.');
                return self::SUCCESS;
            }

            $zoneId = (int) $this->option('zone_id');
            $subscriptionTypeId = (int) $this->option('subscription_type_id');

            if ($this->option('dry-run')) {
                return $this->showInvoiceRepairDryRun(
                    $customers,
                    $zoneId,
                    $subscriptionTypeId
                );
            }

            return $this->repairInvoicesOnly(
                $customers,
                $invoiceGenerator,
                $zoneId,
                $subscriptionTypeId
            );
        }

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
                $subscriptionTypeId,
                false
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


    private function showHistoricalPaymentRepairDryRun(
        array $customers,
        int $zoneId,
        int $subscriptionTypeId
    ): int {
        $monthPayloads = $this->buildMonthPayloads($customers);
        ksort($monthPayloads);

        $rows = [];

        foreach ($monthPayloads as $month => $customersForMonth) {
            if (!$this->repairMonthAllowed($month)) {
                continue;
            }

            $monthEnd = Carbon::createFromFormat('Y-m-d', $month . '-01')
                ->endOfMonth()
                ->toDateString();

            foreach ($customersForMonth as $customerId => $sheetFinance) {
                $customerId = (int) $customerId;

                if (!$this->repairCustomerAllowed($customerId)) {
                    continue;
                }

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
                    $rows[] = [
                        $month,
                        $customerId,
                        'MISSING',
                        '-',
                        number_format((float) ($sheetFinance['amount_paid'] ?? 0), 2, '.', ''),
                        number_format((float) ($sheetFinance['sheet_closing_dues'] ?? 0), 2, '.', ''),
                        'SKIP',
                    ];
                    continue;
                }

                $oldHistoricalPaid = round((float) DB::table('inward_payments')
                    ->where('invoice_id', $invoice->id)
                    ->where('method', 'historical_excel')
                    ->whereNull('deleted_at')
                    ->sum('amount'), 2);

                $sheetPaid = round(
                    (float) ($sheetFinance['amount_paid'] ?? 0),
                    2
                );

                $sheetClosing = round(
                    max(0, (float) ($sheetFinance['sheet_closing_dues'] ?? 0)),
                    2
                );

                $action = abs($oldHistoricalPaid - $sheetPaid) <= 0.01
                    ? 'OK'
                    : ($sheetPaid > 0 ? 'UPDATE' : 'REMOVE');

                $rows[] = [
                    $month,
                    $customerId,
                    $invoice->billing_name ?? '',
                    number_format($oldHistoricalPaid, 2, '.', ''),
                    number_format($sheetPaid, 2, '.', ''),
                    number_format($sheetClosing, 2, '.', ''),
                    $action,
                ];
            }
        }

        $this->newLine();
        $this->info('HISTORICAL PAYMENT REPAIR DRY RUN - NO DATABASE WRITES');

        $this->table(
            [
                'Month',
                'User',
                'Customer',
                'Old Paid',
                'Sheet Paid',
                'Sheet Close',
                'Action',
            ],
            $rows
        );

        return self::SUCCESS;
    }

    private function repairHistoricalPaymentsOnly(
        array $customers,
        int $zoneId,
        int $subscriptionTypeId
    ): int {
        if (!Schema::hasTable('inward_payments')) {
            $this->error('inward_payments table does not exist.');
            return self::FAILURE;
        }

        $monthPayloads = $this->buildMonthPayloads($customers);
        ksort($monthPayloads);

        $stats = [
            'updated' => 0,
            'created' => 0,
            'removed' => 0,
            'unchanged' => 0,
            'missing_invoice' => 0,
        ];

        $this->newLine();
        $this->warn('HISTORICAL PAYMENT REPAIR MODE');
        $this->line('ONLY inward_payments rows with method=historical_excel are changed.');
        $this->line('Invoices, invoice_items, orders and order_items are NOT touched.');
        $this->newLine();

        try {
            foreach ($monthPayloads as $month => $customersForMonth) {
                if (!$this->repairMonthAllowed($month)) {
                    continue;
                }

                $paymentDate = Carbon::createFromFormat('Y-m-d', $month . '-01')
                    ->endOfMonth()
                    ->toDateString();

                foreach ($customersForMonth as $customerId => $sheetFinance) {
                    $customerId = (int) $customerId;

                    if (!$this->repairCustomerAllowed($customerId)) {
                        continue;
                    }

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
                        $stats['missing_invoice']++;
                        $this->warn("Missing invoice {$invoiceNumber}; payment skipped.");
                        continue;
                    }

                    $sheetPaid = round(
                        (float) ($sheetFinance['amount_paid'] ?? 0),
                        2
                    );

                    $sheetClosing = round(
                        max(0, (float) ($sheetFinance['sheet_closing_dues'] ?? 0)),
                        2
                    );

                    DB::transaction(function () use (
                        $invoice,
                        $invoiceNumber,
                        $sheetPaid,
                        $sheetClosing,
                        $paymentDate,
                        &$stats
                    ): void {
                        $payments = DB::table('inward_payments')
                            ->where('invoice_id', $invoice->id)
                            ->where('method', 'historical_excel')
                            ->whereNull('deleted_at')
                            ->orderBy('id')
                            ->get();

                        $oldPaid = round(
                            (float) $payments->sum('amount'),
                            2
                        );

                        if (
                            abs($oldPaid - $sheetPaid) <= 0.01
                            && $payments->count() <= 1
                        ) {
                            /*
                             * Keep due_amount/payment_date synchronized even
                             * when the amount itself is already correct.
                             */
                            if ($payments->count() === 1) {
                                DB::table('inward_payments')
                                    ->where('id', $payments->first()->id)
                                    ->update([
                                        'payment_date' => $paymentDate,
                                        'due_amount' => $sheetClosing,
                                        'currency' => 'INR',
                                        'method' => 'historical_excel',
                                        'note' => 'Historical payment repaired from milk workbook',
                                        'updated_at' => now(),
                                    ]);
                            }

                            $stats['unchanged']++;
                            return;
                        }

                        /*
                         * Sheet says no payment for this month:
                         * soft-delete any old historical_excel payment rows.
                         */
                        if ($sheetPaid <= 0) {
                            if ($payments->isNotEmpty()) {
                                DB::table('inward_payments')
                                    ->whereIn('id', $payments->pluck('id')->all())
                                    ->update([
                                        'deleted_at' => now(),
                                        'updated_at' => now(),
                                    ]);

                                $stats['removed'] += $payments->count();
                            } else {
                                $stats['unchanged']++;
                            }

                            $this->line(
                                "{$invoiceNumber}: paid {$oldPaid} -> 0.00"
                            );
                            return;
                        }

                        if ($payments->isNotEmpty()) {
                            /*
                             * Reuse the first historical payment row so its ID
                             * and invoice relationship remain stable.
                             */
                            $first = $payments->first();

                            DB::table('inward_payments')
                                ->where('id', $first->id)
                                ->update([
                                    'payment_date' => $paymentDate,
                                    'amount' => $sheetPaid,
                                    'due_amount' => $sheetClosing,
                                    'currency' => 'INR',
                                    'method' => 'historical_excel',
                                    'note' => 'Historical payment repaired from milk workbook',
                                    'deleted_at' => null,
                                    'updated_at' => now(),
                                ]);

                            /*
                             * If duplicate historical rows exist for the same
                             * invoice, soft-delete all extras.
                             */
                            $extraIds = $payments
                                ->slice(1)
                                ->pluck('id')
                                ->all();

                            if (!empty($extraIds)) {
                                DB::table('inward_payments')
                                    ->whereIn('id', $extraIds)
                                    ->update([
                                        'deleted_at' => now(),
                                        'updated_at' => now(),
                                    ]);
                            }

                            $stats['updated']++;
                        } else {
                            DB::table('inward_payments')->insert([
                                'order_id' => null,
                                'shopify_order_gid' => null,
                                'invoice_id' => $invoice->id,
                                'previous_payment_id' => null,
                                'payment_date' => $paymentDate,
                                'amount' => $sheetPaid,
                                'due_amount' => $sheetClosing,
                                'currency' => 'INR',
                                'method' => 'historical_excel',
                                'shopify_metaobject_gid' => null,
                                'note' => 'Historical payment repaired from milk workbook',
                                'created_at' => now(),
                                'updated_at' => now(),
                                'deleted_at' => null,
                            ]);

                            $stats['created']++;
                        }

                        $this->line(sprintf(
                            '%s: paid %.2f -> %.2f; due %.2f',
                            $invoiceNumber,
                            $oldPaid,
                            $sheetPaid,
                            $sheetClosing
                        ));
                    });
                }
            }

            $this->newLine();
            $this->info('Historical payment repair completed.');

            $this->table(
                ['Result', 'Count'],
                [
                    ['Payments Updated', $stats['updated']],
                    ['Payments Created', $stats['created']],
                    ['Old Payment Rows Removed', $stats['removed']],
                    ['Payments Already Correct', $stats['unchanged']],
                    ['Missing Invoices Skipped', $stats['missing_invoice']],
                ]
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('Historical payment repair failed.');
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Repair ONLY existing historical invoices and invoice_items.
     *
     * Source of invoice/items:
     * existing orders + order_items via InvoiceGeneratorService.
     *
     * Historical finance:
     * workbook rows aggregated per customer/month.
     *
     * This mode does NOT create SCRs, draft orders, daily orders,
     * or change orders/order_items.
     */
    private function repairInvoicesOnly(
        array $customers,
        InvoiceGeneratorService $invoiceGenerator,
        int $zoneId,
        int $subscriptionTypeId
    ): int {
        $monthPayloads = $this->buildMonthPayloads($customers);
        ksort($monthPayloads);

        $processed = 0;
        $missing = 0;
        $formulaMismatches = 0;

        $this->newLine();
        $this->warn('INVOICE REPAIR MODE - HISTORICAL SHEET TRUTH');
        $this->line('invoice_items: rebuilt from existing orders/order_items.');
        $this->line('invoice financial header: original workbook finance values.');
        $this->line('SCRs, draft orders, orders and order_items are NOT touched.');
        $this->line('Existing inward_payments are NOT modified.');
        $this->newLine();

        try {
            foreach ($monthPayloads as $month => $customersForMonth) {
                if (!$this->repairMonthAllowed($month)) {
                    continue;
                }

                $monthStart = Carbon::createFromFormat(
                    'Y-m-d',
                    $month . '-01'
                )->startOfMonth();

                $monthEndExclusive = $monthStart->copy()->addMonth();

                foreach ($customersForMonth as $customerId => $sheetFinance) {
                    $customerId = (int) $customerId;

                    if (!$this->repairCustomerAllowed($customerId)) {
                        continue;
                    }

                    $invoiceNumber = sprintf(
                        'MILK-%s-Z%s-S%s-U%s',
                        str_replace('-', '', $month),
                        $zoneId,
                        $subscriptionTypeId,
                        $customerId
                    );

                    $existingInvoice = DB::table('invoices')
                        ->where('number', $invoiceNumber)
                        ->first();

                    if (!$existingInvoice) {
                        $this->warn("Missing existing invoice {$invoiceNumber}; skipped.");
                        $missing++;
                        continue;
                    }

                    /*
                     * Rebuild THIS customer's invoice_items from the already
                     * verified orders/order_items. The normal generator may
                     * temporarily update the invoice header; we immediately
                     * restore the historical sheet-truth finance below.
                     */
                    $invoiceGenerator->generateForReport(
                        $zoneId,
                        $subscriptionTypeId,
                        $monthStart->toDateString(),
                        $monthEndExclusive->toDateString(),
                        [$customerId]
                    );

                    $invoice = DB::table('invoices')
                        ->where('number', $invoiceNumber)
                        ->first();

                    if (!$invoice) {
                        throw new \RuntimeException(
                            "Invoice {$invoiceNumber} missing after item rebuild."
                        );
                    }

                    $subtotal = round(
                        (float) ($sheetFinance['sheet_current_dues'] ?? 0),
                        2
                    );

                    $deliveryFee = round(
                        (float) ($sheetFinance['delivery_fee'] ?? 0),
                        2
                    );

                    $previousDues = round(
                        (float) ($sheetFinance['sheet_previous_dues'] ?? 0),
                        2
                    );

                    $amountPaid = round(
                        (float) ($sheetFinance['amount_paid'] ?? 0),
                        2
                    );

                    $sheetClosingDues = round(
                        max(
                            0,
                            (float) ($sheetFinance['sheet_closing_dues'] ?? 0)
                        ),
                        2
                    );

                    $currentMonthTotal = round(
                        $subtotal + $deliveryFee,
                        2
                    );

                    $grandTotal = round(
                        $previousDues + $currentMonthTotal,
                        2
                    );

                    $calculatedClosingDues = round(
                        max(0, $grandTotal - $amountPaid),
                        2
                    );

                    $closingDifference = round(
                        $calculatedClosingDues - $sheetClosingDues,
                        2
                    );

                    if (abs($closingDifference) > 0.01) {
                        $formulaMismatches++;
                    }

                    $paymentStatus = match (true) {
                        $sheetClosingDues <= 0 => 'paid',
                        $amountPaid > 0 => 'partial',
                        default => 'unpaid',
                    };

                    $meta = $this->decodeJsonObject($invoice->meta ?? null);

                    $meta = array_merge($meta, [
                        'import_source' => self::IMPORT_SOURCE,
                        'historical_import' => true,
                        'month' => $month,
                        'zone_id' => $zoneId,
                        'subscription_type_id' => $subscriptionTypeId,

                        'sheet_delivery_count' => round(
                            (float) ($sheetFinance['sheet_delivery_count'] ?? 0),
                            2
                        ),
                        'sheet_delivery_fee' => $deliveryFee,
                        'sheet_current_dues' => $subtotal,
                        'sheet_previous_dues' => $previousDues,
                        'sheet_amount_paid' => $amountPaid,
                        'sheet_closing_dues' => $sheetClosingDues,

                        'applied_current_dues' => $subtotal,
                        'applied_previous_dues' => $previousDues,
                        'calculated_closing_dues' => $calculatedClosingDues,
                        'closing_due_difference' => $closingDifference,

                        'current_dues_source' => 'historical_workbook_formula',
                        'delivery_fee_source' => 'historical_workbook_formula',
                        'repair_source' => 'sheet_truth_finance_items_from_orders',
                        'repaired_at' => now()->toDateTimeString(),
                    ]);

                    DB::table('invoices')
                        ->where('id', $invoice->id)
                        ->update([
                            'subtotal' => $subtotal,

                            /*
                             * Preserve the historical schema convention used
                             * by myInvoices(): opening/previous due is stored
                             * in Unpaid_dues for Jan-Jun historical invoices.
                             */
                            'Unpaid_dues' => $previousDues,

                            'delivery_fee' => $deliveryFee,
                            'total' => $currentMonthTotal,
                            'grand_total' => $grandTotal,
                            'payment_status' => $paymentStatus,
                            'meta' => json_encode($meta),
                            'updated_at' => now(),
                        ]);

                    $processed++;

                    $this->line(sprintf(
                        '%s U%s: subtotal %.2f + fee %.2f + prev %.2f = grand %.2f; paid %.2f; close %.2f',
                        $month,
                        $customerId,
                        $subtotal,
                        $deliveryFee,
                        $previousDues,
                        $grandTotal,
                        $amountPaid,
                        $sheetClosingDues
                    ));
                }
            }

            $this->newLine();
            $this->info('Historical invoice repair completed.');

            $this->table(
                ['Result', 'Count'],
                [
                    ['Invoices Repaired', $processed],
                    ['Missing Existing Invoices', $missing],
                    ['Finance Formula Mismatches', $formulaMismatches],
                    ['Historical Payments Modified', 0],
                ]
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('Historical invoice repair failed.');
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Safe preview for --repair-invoices --dry-run.
     * No database writes and InvoiceGeneratorService is NOT called.
     */
    private function showInvoiceRepairDryRun(
        array $customers,
        int $zoneId,
        int $subscriptionTypeId
    ): int {
        $monthPayloads = $this->buildMonthPayloads($customers);
        ksort($monthPayloads);

        $rows = [];

        foreach ($monthPayloads as $month => $customersForMonth) {
            if (!$this->repairMonthAllowed($month)) {
                continue;
            }

            $monthStart = Carbon::createFromFormat(
                'Y-m-d',
                $month . '-01'
            )->startOfMonth();

            $monthEndExclusive = $monthStart->copy()->addMonth();

            foreach ($customersForMonth as $customerId => $sheetFinance) {
                $customerId = (int) $customerId;

                if (!$this->repairCustomerAllowed($customerId)) {
                    continue;
                }

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

                $orderItemsSubtotal = round(
                    (float) DB::table('order_items as oi')
                        ->join('orders as o', 'o.id', '=', 'oi.order_id')
                        ->join('variants as v', 'v.variant_id', '=', 'oi.variant_id')
                        ->join('products as p', 'p.product_id', '=', 'v.product_id')
                        ->join(
                            'subscription_sub_types as sst',
                            'sst.slug',
                            '=',
                            'p.product_sub_type'
                        )
                        ->whereNotNull('oi.actuals_date')
                        ->where('o.zone_id', $zoneId)
                        ->where('o.customer_id', $customerId)
                        ->where('sst.subscription_type_id', $subscriptionTypeId)
                        ->where('oi.actuals_date', '>=', $monthStart->toDateString())
                        ->where('oi.actuals_date', '<', $monthEndExclusive->toDateString())
                        ->sum('oi.line_total'),
                    2
                );

                $subtotal = round(
                    (float) ($sheetFinance['sheet_current_dues'] ?? 0),
                    2
                );

                $deliveryFee = round(
                    (float) ($sheetFinance['delivery_fee'] ?? 0),
                    2
                );

                $previousDues = round(
                    (float) ($sheetFinance['sheet_previous_dues'] ?? 0),
                    2
                );

                $amountPaid = round(
                    (float) ($sheetFinance['amount_paid'] ?? 0),
                    2
                );

                $sheetClosingDues = round(
                    max(
                        0,
                        (float) ($sheetFinance['sheet_closing_dues'] ?? 0)
                    ),
                    2
                );

                $grandTotal = round(
                    $previousDues + $subtotal + $deliveryFee,
                    2
                );

                $calculatedClosing = round(
                    max(0, $grandTotal - $amountPaid),
                    2
                );

                $rows[] = [
                    $month,
                    $customerId,
                    $invoice?->billing_name ?? 'MISSING',
                    number_format($orderItemsSubtotal, 2, '.', ''),
                    number_format($subtotal, 2, '.', ''),
                    number_format($deliveryFee, 2, '.', ''),
                    number_format($previousDues, 2, '.', ''),
                    number_format($amountPaid, 2, '.', ''),
                    number_format($grandTotal, 2, '.', ''),
                    number_format($sheetClosingDues, 2, '.', ''),
                    number_format(
                        $calculatedClosing - $sheetClosingDues,
                        2,
                        '.',
                        ''
                    ),
                ];
            }
        }

        $this->newLine();
        $this->info('INVOICE REPAIR DRY RUN - HISTORICAL SHEET TRUTH');
        $this->line('NO DATABASE WRITES');

        $this->table(
            [
                'Month',
                'User',
                'Customer',
                'OrderItems',
                'Sheet Subtotal',
                'Sheet Fee',
                'Sheet Prev',
                'Sheet Paid',
                'Grand',
                'Sheet Close',
                'Close Diff',
            ],
            $rows
        );

        $this->newLine();
        $this->line('OrderItems is audit only and will rebuild invoice_items.');
        $this->line('Invoice header finance comes from the original workbook formulas.');
        $this->line('Close Diff should be 0.00.');

        return self::SUCCESS;
    }

    private function computeRepairDeliveryFee(
        int $deliveryCount,
        ?int $productId,
        ?int $variantId,
        ?int $customerId
    ): float {
        $rule = $this->findRepairDeliveryFeeRule(
            $productId,
            $variantId,
            $customerId
        );

        if (!$rule || empty($rule->fee_formula)) {
            return 0.0;
        }

        return $this->evaluateRepairFeeFormula(
            (string) $rule->fee_formula,
            ['qty' => max($deliveryCount, 0)]
        );
    }

    private function findRepairDeliveryFeeRule(
        ?int $productId,
        ?int $variantId,
        ?int $customerId
    ): ?object {
        if (!$productId) {
            return null;
        }

        return DB::table('delivery_fee_rules')
            ->where('is_active', 1)
            ->where('product_id', $productId)
            ->where(function ($q) use ($variantId) {
                if ($variantId) {
                    $q->where('variant_id', $variantId)
                        ->orWhereNull('variant_id');
                } else {
                    $q->whereNull('variant_id');
                }
            })
            ->where(function ($q) use ($customerId) {
                if ($customerId) {
                    $q->where('customer_id', $customerId)
                        ->orWhereNull('customer_id');
                } else {
                    $q->whereNull('customer_id');
                }
            })
            ->orderByRaw("
                CASE
                    WHEN customer_id IS NOT NULL AND variant_id IS NOT NULL THEN 4
                    WHEN customer_id IS NOT NULL AND variant_id IS NULL THEN 3
                    WHEN customer_id IS NULL AND variant_id IS NOT NULL THEN 2
                    ELSE 1
                END DESC
            ")
            ->orderByDesc('priority')
            ->first();
    }

    private function evaluateRepairFeeFormula(
        string $formula,
        array $context
    ): float {
        $qty = (float) ($context['qty'] ?? 0);
        $normalized = preg_replace('/\s+/', '', strtolower($formula));

        if (
            preg_match(
                '/[^0-9qtyfloor\+\-\*\/\(\)\?\:\<\>\=\!\.\s]/',
                $normalized
            )
        ) {
            throw new \RuntimeException('Unsafe fee formula detected.');
        }

        $expr = str_ireplace('qty', '$qty', $formula);

        try {
            $result = (function () use ($expr, $qty) {
                return eval('return ' . $expr . ';');
            })();
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Fee formula evaluation failed: ' . $e->getMessage(),
                0,
                $e
            );
        }

        return round((float) $result, 2);
    }

    private function repairCustomerAllowed(int $customerId): bool
    {
        $filter = $this->option('customer_id');

        if ($filter === null || $filter === '') {
            return true;
        }

        return $customerId === (int) $filter;
    }

    private function repairMonthAllowed(string $month): bool
    {
        $filter = trim((string) ($this->option('month') ?? ''));

        if ($filter === '') {
            return true;
        }

        if (!preg_match('/^\d{4}-\d{2}$/', $filter)) {
            throw new \InvalidArgumentException(
                '--month must be in YYYY-MM format, for example --month=2026-06'
            );
        }

        return $month === $filter;
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
        int $subscriptionTypeId,
        bool $repairOnly = false
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
            if ($repairOnly && !$this->repairMonthAllowed($month)) {
                continue;
            }

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
                if ($repairOnly && !$this->repairCustomerAllowed((int) $customerId)) {
                    continue;
                }

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
                    $repairOnly,
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

                    if (!$repairOnly && $amountPaid > 0) {
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

                /*
                 * In the original H1 workbook, one customer can have multiple
                 * product rows in the same month. Historical finance values
                 * are row-level, so aggregate every row for that customer/month.
                 */
                $result[$month][$customerId] = [
                    'sheet_delivery_count' => $this->sumMoney($rows, 'delivery_count'),
                    'delivery_fee' => $this->sumMoney($rows, 'delivery_fee'),
                    'sheet_current_dues' => $this->sumMoney($rows, 'this_month_dues'),
                    'sheet_previous_dues' => $this->sumMoney($rows, 'previous_dues'),
                    'amount_paid' => $this->sumMoney($rows, 'payment'),
                    'sheet_closing_dues' => $this->sumMoney($rows, 'closing_dues'),
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

    private function sumMoney(array $rows, string $field): float
    {
        $total = 0.0;

        foreach ($rows as $row) {
            $total += round((float) ($row[$field] ?? 0), 2);
        }

        return round($total, 2);
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
