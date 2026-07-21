<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\Imports\HistoricalImporterService;

class ImportHistoricalMilk extends Command
{
    /**
     * Example:
     *
     * php artisan milk:historical-import storage/app/milk.xlsx --dry-run
     */

    protected $signature = '
        milk:historical-import
        {file : Path to workbook}
        {--dry-run : Parse only. Do not write database}
    ';

    protected $description =
    'Import historical milk workbook into Orders and Invoices';

    private HistoricalImporterService $importer;

    public function handle(HistoricalImporterService $importer)
    {
        $this->importer = $importer;

        $file = $this->argument('file');

        if (!is_file($file)) {
            $this->error("Workbook not found:");

            $this->line($file);

            return self::FAILURE;
        }

        $this->info('');
        $this->info('Reading workbook...');
        $this->info('');

        $preview = $this->importer->preview($file);

        $summary = $preview['summary'];
        $customers = $preview['customers'];

        /*
         * Dry run
         */
        if ($this->option('dry-run')) {

            $this->newLine();

            $this->info('DRY RUN SUCCESS');

            $this->table(
                ['Metric', 'Value'],
                [
                    ['Customers', count($customers)],
                    ['Monthly Groups', $summary['monthly_groups'] ?? 0],

                    ['Rows Accepted', $summary['rows_accepted']],
                    ['Rows Skipped', $summary['rows_skipped']],

                    ['Users Matched', $summary['users_matched']],
                    ['Users Missing', $summary['users_missing']],

                    ['Products Mapped', $summary['products_mapped'] ?? 0],
                    ['Products Unmapped', count($summary['products_unmapped'] ?? [])],

                    ['Total Delivered Qty', $summary['total_delivered_quantity'] ?? 0],
                    ['Zero Delivery Rows', $summary['zero_delivery_rows'] ?? 0],
                ]
            );

            if (!empty($summary['day_columns_detected'])) {

                $this->newLine();
                $this->info('Day Columns Detected');

                foreach ($summary['day_columns_detected'] as $month => $count) {
                    $this->line("{$month} : {$count}");
                }
            }

            if (!empty($summary['products_unmapped'])) {

                $this->newLine();
                $this->warn('Unmapped Products');

                foreach (array_keys($summary['products_unmapped']) as $product) {
                    $this->line("- {$product}");
                }
            }

            $this->newLine();
            $this->info('Sheets Found');

            foreach ($summary['sheets_found'] ?? [] as $month => $sheetName) {
                $this->line("{$month} : {$sheetName}");
            }

            if (!empty($summary['sheets_missing'])) {
                $this->newLine();
                $this->warn('Sheets Missing');

                foreach ($summary['sheets_missing'] as $month) {
                    $this->line("- {$month}");
                }
            }

            if (!empty($summary['errors'])) {
                $this->newLine();
                $this->error('Sheet Errors');

                foreach ($summary['errors'] as $error) {
                    $this->line(
                        ($error['month'] ?? 'unknown') .
                            ' | ' .
                            ($error['sheet'] ?? 'unknown') .
                            ' | ' .
                            ($error['message'] ?? 'unknown error')
                    );
                }
            }

            return self::SUCCESS;
        }

        /*
 * Actual import
 *
 * Creates:
 * - one order for each customer/month
 * - order items from actual delivered quantities
 * - one invoice for each customer/month
 * - matching invoice items
 *
 * Does NOT create subscriptions or draft orders.
 */

        DB::beginTransaction();

        try {
            $this->newLine();
            $this->info('Starting database import...');

            $now = now();

            $createdOrders = 0;
            $createdOrderItems = 0;
            $createdInvoices = 0;
            $createdInvoiceItems = 0;

            $skippedExisting = 0;
            $skippedNoUser = 0;
            $skippedEmptyMonths = 0;
            $skippedUnmappedRows = 0;
            $skippedZeroQuantityRows = 0;

            foreach ($customers as $customer) {
                $customerId = $customer['user_id'] ?? null;

                /*
         * Customer must already exist in users table.
         */
                if (!$customerId) {
                    $skippedNoUser++;
                    continue;
                }

                foreach ($customer['months'] as $month => $monthData) {
                    $rows = $monthData['rows'] ?? [];

                    /*
             * Deterministic unique numbers prevent duplicate imports.
             *
             * Example:
             * HIST-MILK-202601-U11343
             */
                    $monthKey = str_replace('-', '', $month);

                    $orderNumber =
                        'HIST-MILK-' .
                        $monthKey .
                        '-U' .
                        $customerId;

                    $invoiceNumber =
                        'HIST-INV-' .
                        $monthKey .
                        '-U' .
                        $customerId;

                    /*
             * If this customer/month was already imported,
             * skip it safely.
             */
                    $orderAlreadyExists = DB::table('orders')
                        ->where('number', $orderNumber)
                        ->exists();

                    $invoiceAlreadyExists = DB::table('invoices')
                        ->where('number', $invoiceNumber)
                        ->exists();

                    if ($orderAlreadyExists || $invoiceAlreadyExists) {
                        $skippedExisting++;
                        continue;
                    }

                    $items = [];

                    foreach ($rows as $row) {
                        $mapping = $row['product_mapping'] ?? null;

                        /*
                 * Example: Eenadu-Newspaper is currently unmapped.
                 */
                        if (!$mapping) {
                            $skippedUnmappedRows++;
                            continue;
                        }

                        $quantity = (int) round(
                            (float) ($row['delivered_quantity'] ?? 0)
                        );

                        if ($quantity <= 0) {
                            $skippedZeroQuantityRows++;
                            continue;
                        }

                        $unitPrice = round(
                            (float) ($mapping['unit_price'] ?? 0),
                            2
                        );

                        $lineTotal = round(
                            $quantity * $unitPrice,
                            2
                        );

                        $items[] = [
                            'product_id' => $mapping['product_id'],
                            'variant_id' => $mapping['variant_id'],
                            'title' => $mapping['title'],
                            'variant' => $mapping['variant'],
                            'quantity' => $quantity,
                            'unit_price' => $unitPrice,
                            'line_total' => $lineTotal,

                            'source_row' => [
                                'sheet' => $row['sheet'] ?? null,
                                'excel_row' => $row['excel_row'] ?? null,
                                'product_name' => $row['product_name'] ?? null,
                                'ask_quantity' => $row['ask_quantity'] ?? 0,
                                'delivered_days' => $row['delivered_days'] ?? 0,
                                'daily_quantities' =>
                                $row['daily_quantities'] ?? [],
                            ],
                        ];
                    }

                    /*
             * Do not create empty orders or invoices.
             */
                    if (empty($items)) {
                        $skippedEmptyMonths++;
                        continue;
                    }

                    $subtotal = round(
                        array_sum(
                            array_column($items, 'line_total')
                        ),
                        2
                    );

                    /*
             * Monthly financial values may appear on multiple
             * product rows. Use the maximum value instead of
             * adding duplicate values together.
             */
                    $previousDues = $this->maximumMoney(
                        $rows,
                        'previous_dues'
                    );

                    $thisMonthDues = $this->maximumMoney(
                        $rows,
                        'this_month_dues'
                    );

                    $payment = $this->maximumMoney(
                        $rows,
                        'payment'
                    );

                    $closingDues = $this->maximumMoney(
                        $rows,
                        'closing_dues'
                    );

                    /*
             * Use workbook monthly dues when available.
             * Otherwise use the calculated product subtotal.
             */
                    $monthlyBill = $thisMonthDues > 0
                        ? $thisMonthDues
                        : $subtotal;

                    /*
             * Any amount above product subtotal is retained as
             * delivery fee/adjustment for historical accounting.
             */
                    $deliveryFee = max(
                        0,
                        round($monthlyBill - $subtotal, 2)
                    );

                    $grandTotal = round(
                        $previousDues + $monthlyBill,
                        2
                    );

                    $paymentStatus = match (true) {
                        $closingDues <= 0 => 'paid',
                        $payment > 0 => 'partial',
                        default => 'unpaid',
                    };

                    $firstRow = $rows[0] ?? [];

                    $billingName =
                        $customer['customer_name']
                        ?? $firstRow['customer_name']
                        ?? null;

                    $address =
                        $firstRow['address']
                        ?? null;

                    $phone =
                        $customer['database_phone']
                        ?? $customer['phone']
                        ?? null;

                    $monthStart = $month . '-01';
                    $monthEnd = $monthData['payment_date'];

                    /*
             * Create historical order.
             */
                    $orderId = DB::table('orders')->insertGetId([
                        'order_type' => 'csv_import',

                        'name' => $orderNumber,
                        'number' => $orderNumber,

                        'customer_id' => $customerId,

                        'delivery_date' => $monthEnd,
                        'delivery_status' => 'delivered',
                        'delivered_at' => $monthEnd . ' 23:59:59',

                        'status' => 'fulfilled',
                        'confirmed' => 1,
                        'closed' => 1,

                        'requires_shipping' => 1,
                        'taxes_included' => 0,
                        'tax_exempt' => 0,
                        'test' => 0,

                        'unpaid' => $closingDues > 0 ? 1 : 0,

                        'total_refunded' => 0,
                        'total_outstanding' => $closingDues,
                        'total_received' => $payment,

                        'current_total' => $subtotal,
                        'current_tax' => 0,
                        'current_discounts' => 0,
                        'current_shipping' => $deliveryFee,
                        'current_subtotal' => $subtotal,

                        'financial_status' => $paymentStatus,
                        'display_financial_status' => $paymentStatus,
                        'financial_status_label' => ucfirst($paymentStatus),

                        'fulfillment_status' => 'fulfilled',
                        'display_fulfillment_status' => 'fulfilled',
                        'fulfillment_status_label' => 'Fulfilled',

                        'cancelled' => 0,

                        'phone' => $phone,

                        'item_count' => array_sum(
                            array_column($items, 'quantity')
                        ),

                        'line_items_subtotal_price' => $subtotal,
                        'shipping_price' => $deliveryFee,
                        'total_refunded_amount' => 0,
                        'total_net_amount' => $monthlyBill,

                        'currency' => 'INR',
                        'currency_code' => 'INR',
                        'presentment_currency_code' => 'INR',

                        'shipping_address' => json_encode([
                            'name' => $billingName,
                            'address1' => $address,
                            'phone' => $phone,
                        ]),

                        'shipping_address_json' => json_encode([
                            'name' => $billingName,
                            'address1' => $address,
                            'phone' => $phone,
                        ]),

                        'billing_address_json' => json_encode([
                            'name' => $billingName,
                            'address1' => $address,
                            'phone' => $phone,
                        ]),

                        'subtotal' => $subtotal,
                        'tax' => 0,
                        'discount' => 0,
                        'total' => $monthlyBill,

                        'source_name' => 'milk_historical_h1_2026',

                        'note' =>
                        'Historical milk import for ' .
                            $month,

                        'meta' => json_encode([
                            'import_source' =>
                            'milk_historical_h1_2026',

                            'historical_import' => true,
                            'month' => $month,

                            'previous_dues' => $previousDues,
                            'this_month_dues' => $thisMonthDues,
                            'payment' => $payment,
                            'closing_dues' => $closingDues,

                            'calculated_product_subtotal' => $subtotal,
                            'monthly_bill' => $monthlyBill,
                            'delivery_fee_or_adjustment' => $deliveryFee,
                        ]),

                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $createdOrders++;

                    /*
             * Create order items.
             */
                    foreach ($items as $item) {
                        DB::table('order_items')->insert([
                            'order_id' => $orderId,

                            'product_id' => $item['product_id'],
                            'variant_id' => $item['variant_id'],

                            'title' => $item['title'],
                            'variant' => $item['variant'],

                            'quantity' => $item['quantity'],
                            'unit_price' => $item['unit_price'],
                            'line_total' => $item['line_total'],

                            'actuals_date' => $monthEnd,

                            'meta' => json_encode([
                                'import_source' =>
                                'milk_historical_h1_2026',

                                'historical_import' => true,
                                'month' => $month,
                                'source_row' => $item['source_row'],
                            ]),

                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);

                        $createdOrderItems++;
                    }

                    /*
             * Create historical invoice.
             */
                    $invoiceId = DB::table('invoices')->insertGetId([
                        'user_id' => $customerId,
                        'customer_id' => $customerId,

                        'order_type' => 'csv_import',

                        'order_start_date' => $monthStart,
                        'order_end_date' => $monthEnd,

                        'billing_name' => $billingName,

                        'billing_address_json' => json_encode([
                            'name' => $billingName,
                            'address1' => $address,
                            'phone' => $phone,
                        ]),

                        'currency' => 'INR',

                        'number' => $invoiceNumber,
                        'invoice_number' => $invoiceNumber,
                        'invoice_date' => $monthEnd,

                        'status' => 'issued',
                        'payment_status' => $paymentStatus,
                        'gst_status' => 'unfiled',

                        'subtotal' => $subtotal,
                        'Unpaid_dues' => $closingDues,

                        'tax' => 0,
                        'tax_total' => 0,
                        'discount' => 0,

                        'delivery_fee' => $deliveryFee,

                        'total' => $monthlyBill,
                        'grand_total' => $grandTotal,

                        'meta' => json_encode([
                            'import_source' =>
                            'milk_historical_h1_2026',

                            'historical_import' => true,
                            'month' => $month,

                            'order_id' => $orderId,
                            'order_number' => $orderNumber,

                            'previous_dues' => $previousDues,
                            'this_month_dues' => $thisMonthDues,
                            'payment_received' => $payment,
                            'closing_dues' => $closingDues,

                            'calculated_product_subtotal' => $subtotal,
                            'monthly_bill' => $monthlyBill,
                            'delivery_fee_or_adjustment' => $deliveryFee,
                        ]),

                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $createdInvoices++;

                    /*
             * Create invoice items matching order items.
             */
                    foreach ($items as $item) {
                        DB::table('invoice_items')->insert([
                            'invoice_id' => $invoiceId,

                            'title' => trim(
                                $item['title'] .
                                    (
                                        !empty($item['variant'])
                                        ? ' - ' . $item['variant']
                                        : ''
                                    )
                            ),

                            'quantity' => $item['quantity'],
                            'unit_price' => $item['unit_price'],
                            'line_total' => $item['line_total'],

                            'meta' => json_encode([
                                'import_source' =>
                                'milk_historical_h1_2026',

                                'historical_import' => true,
                                'month' => $month,

                                'order_id' => $orderId,
                                'product_id' => $item['product_id'],
                                'variant_id' => $item['variant_id'],
                                'source_row' => $item['source_row'],
                            ]),

                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);

                        $createdInvoiceItems++;
                    }
                }
            }

            DB::commit();

            $this->newLine();
            $this->info('Historical import completed.');

            $this->table(
                ['Result', 'Count'],
                [
                    ['Orders Created', $createdOrders],
                    ['Order Items Created', $createdOrderItems],
                    ['Invoices Created', $createdInvoices],
                    ['Invoice Items Created', $createdInvoiceItems],
                    ['Existing Months Skipped', $skippedExisting],
                    ['Customers Without User Skipped', $skippedNoUser],
                    ['Empty Months Skipped', $skippedEmptyMonths],
                    ['Unmapped Rows Skipped', $skippedUnmappedRows],
                    ['Zero Quantity Rows Skipped', $skippedZeroQuantityRows],
                ]
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();

            $this->newLine();
            $this->error('Historical import failed.');
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function maximumMoney(
        array $rows,
        string $field
    ): float {
        $values = [];

        foreach ($rows as $row) {
            $values[] = round(
                (float) ($row[$field] ?? 0),
                2
            );
        }

        if (empty($values)) {
            return 0.0;
        }

        return max($values);
    }
}
