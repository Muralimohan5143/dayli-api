<?php

namespace App\Services\Imports;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;


class HistoricalImporterService
{
    private const IMPORT_SOURCE = 'milk_historical_h1_2026';

    private const MONTHS = [
        '2026-01' => [
            'aliases' => [
                'JAN26',
                'Jan26',
                'Jan 26',
                'January 26',
                'Jan 2026',
                'January 2026',
            ],
            'payment_date' => '2026-01-31',
        ],

        '2026-02' => [
            'aliases' => [
                'Feb26',
                'Feb 26',
                'February 26',
                'Feb 2026',
                'February 2026',
            ],
            'payment_date' => '2026-02-28',
        ],

        '2026-03' => [
            'aliases' => [
                'Mar26',
                'Mar 26',
                'March 26',
                'Mar 2026',
                'March 2026',
            ],
            'payment_date' => '2026-03-31',
        ],

        '2026-04' => [
            'aliases' => [
                'Apr26',
                'Apr 26',
                'April 26',
                'Apr 2026',
                'April 2026',
            ],
            'payment_date' => '2026-04-30',
        ],

        '2026-05' => [
            'aliases' => [
                'May26',
                'May 26',
                'May 2026',
            ],
            'payment_date' => '2026-05-31',
        ],

        '2026-06' => [
            'aliases' => [
                'Jun26',
                'Jun 26',
                'June 26',
                'Jun 2026',
                'June 2026',
            ],
            'payment_date' => '2026-06-30',
        ],
    ];

    /**
     * Phase 1 only:
     *
     * - Read workbook
     * - Locate Jan–Jun sheets
     * - Detect headers
     * - Normalize phone numbers
     * - Group rows by phone
     * - Match existing users
     *
     * No database writes.
     */
    public function preview(string $filePath): array
    {
        if (!is_file($filePath)) {
            throw new RuntimeException("Workbook not found: {$filePath}");
        }

        $reader = IOFactory::createReaderForFile($filePath);

        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        $reader->setIncludeCharts(false);

        $spreadsheet = $reader->load($filePath);

        /*
         * Jan-Mar monthly sheets do not consistently contain phone numbers.
         * Build a lookup from the workbook's Customers sheet first.
         */
        $customerDirectory = $this->buildCustomerDirectory(
            $spreadsheet->getAllSheets()
        );

        $customers = [];
        $summary = [
            'source' => self::IMPORT_SOURCE,
            'file' => $filePath,
            'sheets_found' => [],
            'sheets_missing' => [],
            'rows_read' => 0,
            'rows_accepted' => 0,
            'rows_skipped' => 0,
            'duplicate_phone_rows' => 0,
            'customers_grouped' => 0,
            'users_matched' => 0,
            'users_missing' => 0,
            'missing_phone_rows' => [],
            'unmatched_users' => [],

            // Collect unique milk/product names from Excel.
            'product_names' => [],
            'products_mapped' => 0,
            'products_unmapped' => [],

            // Actual delivery totals calculated from Day 1 ... Day 31.
            'monthly_groups' => 0,
            'total_delivered_quantity' => 0.0,
            'zero_delivery_rows' => 0,
            'day_columns_detected' => [],

            'errors' => [],
        ];
        foreach (self::MONTHS as $month => $monthConfig) {
            $sheet = $this->findMonthSheet(
                $spreadsheet->getAllSheets(),
                $monthConfig['aliases']
            );

            if (!$sheet) {
                $summary['sheets_missing'][] = $month;
                continue;
            }

            $summary['sheets_found'][$month] = $sheet->getTitle();

            try {
                $monthRows = $this->readMonthSheet(
                    $sheet,
                    $month,
                    $summary,
                    $customerDirectory
                );

                foreach ($monthRows as $parsedRow) {
                    $phone = $parsedRow['phone'];

                    if (isset($customers[$phone])) {
                        $summary['duplicate_phone_rows']++;
                    }

                    if (!isset($customers[$phone])) {
                        $user = $this->findUserByPhone($phone);

                        $customers[$phone] = [
                            'phone' => $phone,
                            'user_id' => $user?->id,
                            'database_phone' => $user?->phone,
                            'customer_name' => $parsedRow['customer_name'],
                            'months' => [],
                            'source_rows' => [],
                        ];

                        if ($user) {
                            $summary['users_matched']++;
                        } else {
                            $summary['users_missing']++;
                            $summary['unmatched_users'][] = [
                                'phone' => $phone,
                                'customer_name' => $parsedRow['customer_name'],
                            ];
                        }
                    }

                    /*
                     * A customer may have multiple rows in one month:
                     * milk + curd + newspaper, etc.
                     *
                     * Therefore keep an array of rows for each month.
                     */
                    $customers[$phone]['months'][$month] ??= [
                        'month' => $month,
                        'payment_date' => self::MONTHS[$month]['payment_date'],
                        'rows' => [],
                    ];

                    $customers[$phone]['months'][$month]['rows'][] = $parsedRow;

                    $customers[$phone]['source_rows'][] = [
                        'month' => $month,
                        'sheet' => $parsedRow['sheet'],
                        'excel_row' => $parsedRow['excel_row'],
                    ];
                }
            } catch (\Throwable $e) {
                $summary['errors'][] = [
                    'month' => $month,
                    'sheet' => $sheet->getTitle(),
                    'message' => $e->getMessage(),
                ];
            }
        }

        ksort($customers);

        foreach ($customers as &$customer) {
            ksort($customer['months']);
        }
        unset($customer);

        $summary['customers_grouped'] = count($customers);
        $summary['monthly_groups'] = array_sum(
            array_map(
                fn(array $customer) => count($customer['months']),
                $customers
            )
        );
        $summary['total_delivered_quantity'] = round(
            (float) $summary['total_delivered_quantity'],
            4
        );

        /*
 * Temporary debug output:
 * Show every unique product name found in the workbook.
 */
        // dump([
        //     'distinct_workbook_product_names' =>
        //     array_keys($summary['product_names']),
        // ]);

        return [
            'summary' => $summary,
            'customers' => $customers,
        ];
    }

    private function readMonthSheet(
        Worksheet $sheet,
        string $month,
        array &$summary,
        array $customerDirectory
    ): array {
        /*
         * Numeric indexes are easier when workbook columns vary.
         */
        $rows = [];

        foreach ($sheet->getRowIterator() as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(true);

            $currentRow = [];

            foreach ($cellIterator as $cell) {
                $columnIndex =
                    Coordinate::columnIndexFromString(
                        $cell->getColumn()
                    ) - 1;

                $currentRow[$columnIndex] =
                    $this->safeCellValue($cell);
            }

            $rows[$row->getRowIndex() - 1] = $currentRow;
        }
        $headerRowIndex = $this->findHeaderRowIndex($rows);

        if ($headerRowIndex === null) {
            throw new RuntimeException(
                "Could not locate header row in sheet {$sheet->getTitle()}"
            );
        }

        $header = $rows[$headerRowIndex];

        // $columns = $this->detectColumns($header);


        /*
 * In Apr–Jun sheets, the Day 1 ... Day 31 headings are
 * located in the row immediately above the main header row.
 *
 * Merge both header rows by column so the importer supports:
 *
 * Jan–Mar: day headings in the same header row
 * Apr–Jun: day headings in the previous header row
 */
        $previousHeader = $rows[$headerRowIndex - 1] ?? [];

        $combinedHeader = [];


        $allColumnIndexes = array_unique(
            array_merge(
                array_keys($previousHeader),
                array_keys($header)
            )
        );

        foreach ($allColumnIndexes as $columnIndex) {
            $previousValue = $this->cleanText(
                $previousHeader[$columnIndex] ?? ''
            );

            $currentValue = $this->cleanText(
                $header[$columnIndex] ?? ''
            );

            $combinedHeader[$columnIndex] = trim(
                $previousValue . ' ' . $currentValue
            );
        }

        $columns = $this->detectColumns($combinedHeader);

        // if ($month === '2026-02') {
        //     dump($columns);
        // }



        $dayColumns = $this->detectDayColumns(
            $combinedHeader,
            $month
        );

        if (empty($dayColumns)) {
            throw new RuntimeException(
                "No Day 1 ... Day 31 columns found in sheet {$sheet->getTitle()}"
            );
        }

        $summary['day_columns_detected'][$month] = count($dayColumns);

        // dump([
        //     'month' => $month,
        //     'sheet' => $sheet->getTitle(),
        //     'header_excel_row' => $headerRowIndex + 1,
        //     'header' => $header,
        //     'detected_columns' => $columns,
        // ]);

        $parsedRows = [];

        foreach (
            array_slice($rows, $headerRowIndex + 1, null, true)
            as $zeroBasedIndex => $row
        ) {
            $excelRow = $zeroBasedIndex + 1;
            $summary['rows_read']++;

            $name = $this->cleanText(
                $this->cell($row, $columns['customer_name'])
            );

            $address = $this->cleanText(
                $this->cell($row, $columns['address'])
            );

            $rawPhone = $this->cleanText(
                $this->cell($row, $columns['phone'])
            );

            $phone = $this->normalizePhone($rawPhone);

            /*
             * Old monthly sheets may not contain phone numbers.
             * Resolve the phone through the Customers worksheet.
             */
            if ($phone === '' && $name !== '') {
                $phone = $this->resolvePhoneFromDirectory(
                    $customerDirectory,
                    $name,
                    $address
                );
            }

            if ($this->isEmptyRow($row)) {
                $summary['rows_skipped']++;
                continue;
            }

            if ($name === '' && $phone === '') {
                $summary['rows_skipped']++;
                continue;
            }

            if ($phone === '') {
                $summary['rows_skipped']++;

                $summary['missing_phone_rows'][] = [
                    'month' => $month,
                    'sheet' => $sheet->getTitle(),
                    'excel_row' => $excelRow,
                    'customer_name' => $name,
                    'raw_phone' => $rawPhone,
                ];

                continue;
            }

            $productName = $this->cleanText(
                $this->cell($row, $columns['product'])
            );

            $productMapping = $this->mapHistoricalProduct($productName);

            if ($productName !== '') {
                $summary['product_names'][$productName] = true;

                if ($productMapping === null) {
                    $summary['products_unmapped'][$productName] = true;
                } else {
                    $summary['products_mapped']++;
                }
            }

            // if ($productName !== '') {
            //     $summary['product_names'][$productName] = true;
            // }

            /*
             * Ask Count is the requested daily quantity.
             * Historical order quantity must come from the actual Day columns.
             */
            $askQuantity = $this->numericValue(
                $this->cell($row, $columns['quantity'])
            );

            $delivery = $this->calculateDeliveredQuantity(
                $row,
                $dayColumns
            );

            $deliveredQuantity = $delivery['quantity'];

            if ($deliveredQuantity <= 0) {
                $summary['zero_delivery_rows']++;
            }

            $summary['total_delivered_quantity'] += $deliveredQuantity;

            $sheetDeliveryCount = $this->numericValue(
                $this->cell($row, $columns['delivery_count'])
            );

            $deliveryFee = $this->moneyValue(
                $this->cell($row, $columns['delivery_fee'])
            );

            $previousDues = $this->moneyValue(
                $this->cell($row, $columns['previous_dues'])
            );

            $thisMonthDues = $this->moneyValue(
                $this->cell($row, $columns['this_month_dues'])
            );

            $payment = $this->moneyValue(
                $this->cell($row, $columns['payment'])
            );

            $closingDues = $this->moneyValue(
                $this->cell($row, $columns['closing_dues'])
            );

            // if ($name === 'Sreenu') {
            //     dump([
            //         'month' => $month,
            //         'delivery_fee' => $deliveryFee ?? null,
            //         'previous_dues' => $previousDues,
            //         'this_month_dues' => $thisMonthDues,
            //         'payment' => $payment,
            //         'closing_dues' => $closingDues,
            //     ]);
            // }

            $parsedRows[] = [
                'month' => $month,
                'sheet' => $sheet->getTitle(),
                'excel_row' => $excelRow,

                'customer_name' => $name,
                'phone' => $phone,
                'raw_phone' => $rawPhone,
                'address' => $address,

                'product_name' => $productName,
                'product_mapping' => $productMapping,

                // Keep both values so dry-run output can verify the import.
                'ask_quantity' => $askQuantity,
                'quantity' => $deliveredQuantity,
                'delivered_quantity' => $deliveredQuantity,
                'delivered_days' => $delivery['delivered_days'],
                'daily_quantities' => $delivery['daily_quantities'],

                'delivery_count' => $sheetDeliveryCount,
                'delivery_fee' => $deliveryFee,
                'previous_dues' => $previousDues,
                'this_month_dues' => $thisMonthDues,
                'payment' => $payment,
                'closing_dues' => $closingDues,

                /*
                 * Keep original row for troubleshooting.
                 * This will not be stored in the database.
                 */
                'raw_row' => $row,
            ];

            $summary['rows_accepted']++;
        }

        return $parsedRows;
    }

    /**
     * Detect Day 1 ... Day 31 columns from the monthly sheet header.
     */
    private function detectDayColumns(array $header, string $month): array
    {
        $detected = [];
        [$year, $monthNumber] = array_map('intval', explode('-', $month));
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $monthNumber, $year);

        foreach ($header as $index => $rawValue) {
            $day = $this->extractDayNumberFromHeader($rawValue, $year, $monthNumber);

            if ($day === null || $day < 1 || $day > $daysInMonth) {
                continue;
            }

            $detected[$day] ??= (int) $index;
        }

        ksort($detected);
        return $detected;
    }

    private function extractDayNumberFromHeader(mixed $rawValue, int $year, int $month): ?int
    {
        if ($rawValue instanceof \DateTimeInterface) {
            return ((int) $rawValue->format('Y') === $year && (int) $rawValue->format('n') === $month)
                ? (int) $rawValue->format('j')
                : null;
        }

        if (is_int($rawValue) || is_float($rawValue)) {
            $numeric = (float) $rawValue;

            if (floor($numeric) === $numeric && $numeric >= 1 && $numeric <= 31) {
                return (int) $numeric;
            }

            if ($numeric > 31) {
                try {
                    $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($numeric);
                    if ((int) $date->format('Y') === $year && (int) $date->format('n') === $month) {
                        return (int) $date->format('j');
                    }
                } catch (\Throwable) {
                    return null;
                }
            }

            return null;
        }

        $text = $this->cleanText($rawValue);
        if ($text === '') {
            return null;
        }

        $normalized = $this->normalizeHeader($text);

        if (preg_match('/^(?:day|d|date)_?0?([1-9]|[12][0-9]|3[01])$/', $normalized, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/^0?([1-9]|[12][0-9]|3[01])$/', $normalized, $matches)) {
            return (int) $matches[1];
        }

        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'm/d/Y', 'd.m.Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $text);
            if ($date instanceof \DateTimeImmutable && (int) $date->format('Y') === $year && (int) $date->format('n') === $month) {
                return (int) $date->format('j');
            }
        }

        return null;
    }

    private function calculateDeliveredQuantity(array $row, array $dayColumns): array
    {
        $total = 0.0;
        $deliveredDays = 0;
        $dailyQuantities = [];

        foreach ($dayColumns as $day => $columnIndex) {
            $qty = $this->numericValue($this->cell($row, $columnIndex));

            if ($qty < 0) {
                $qty = 0.0;
            }

            $dailyQuantities[(int) $day] = $qty;

            if ($qty > 0) {
                $deliveredDays++;
                $total += $qty;
            }
        }

        return [
            'quantity' => round($total, 4),
            'delivered_days' => $deliveredDays,
            'daily_quantities' => $dailyQuantities,
        ];
    }

    private function findHeaderRowIndex(array $rows): ?int
    {
        /*
         * A monthly worksheet can contain a summary table above the
         * real customer table. Detect the customer table by requiring
         * Name + Address + Milk/Product. Phone is optional because the
         * older monthly sheets do not always contain it.
         */
        foreach (array_slice($rows, 0, 80, true) as $index => $row) {
            $normalized = array_map(
                fn($value) => $this->normalizeHeader((string) $value),
                $row
            );

            $hasName = $this->containsAnyHeader($normalized, [
                'name',
                'customer',
                'customer_name',
            ]);

            $hasAddress = $this->containsAnyHeader($normalized, [
                'address',
                'address_full',
                'delivery_address',
                'location',
            ]);

            $hasProduct = $this->containsAnyHeader($normalized, [
                'milk',
                'milk_type',
                'product',
                'product_name',
                'item',
                'item_name',
                'subscription',
            ]);

            if ($hasName && $hasAddress && $hasProduct) {
                return $index;
            }
        }

        return null;
    }

    private function detectColumns(array $header): array
    {
        $normalized = [];

        foreach ($header as $index => $value) {
            $normalized[$index] = $this->normalizeHeader((string) $value);
        }

        $columns = [
            'customer_name' => $this->findColumn($normalized, [
                'name',
                'customer',
                'customer_name',
            ]),

            'phone' => $this->findColumn($normalized, [
                'phone',
                'phone_no',
                'phone_number',
                'mobile',
                'mobile_number',
                'contact_number',
            ]),

            'address' => $this->findColumn($normalized, [
                'address',
                'address_full',
                'delivery_address',
                'location',
            ]),

            'product' => $this->findColumn($normalized, [
                'milk',
                'product',
                'product_name',
                'item',
                'item_name',
                'subscription',
            ]),

            'quantity' => $this->findColumn($normalized, [
                'quantity',
                'qty',
                'count',
                'ask_count',
                'monthly_count',
            ]),

            'delivery_count' => $this->findColumn($normalized, [
                'delivery_count',
                'deliveries',
                'delivered_count',
                'no_of_deliveries',
                'number_of_deliveries',
            ]),

            'delivery_fee' => $this->findColumn($normalized, [
                'delivery_fee',
                'delivery_fees',
                'delivery_charge',
                'delivery_charges',
                'delivery_amount',
                'service_fee',
            ]),

            'previous_dues' => $this->findColumn($normalized, [
                'previous_dues',
                'previous_due',
                'prev_dues',
                'prev_due',
                'opening_due',
                'opening_dues',
                'old_due',
                'old_dues',
            ]),

            'this_month_dues' => $this->findColumn($normalized, [
                'this_month_dues',
                'this_month_due',
                'monthly_dues',
                'monthly_due',
                'current_dues',
                'current_due',
                'bill_amount',
            ]),

            'payment' => $this->findColumn($normalized, [
                'payment',
                'paid',
                'paid_amount',
                'payment_received',
                'received',
                'amount_paid',
            ]),

            'closing_dues' => $this->findColumn($normalized, [
                'closing_dues',
                'closing_due',
                'balance',
                'balance_due',
                'final_due',
                'final_dues',
                'total_due',
                'total_dues',
            ]),
        ];

        if ($columns['customer_name'] === null) {
            throw new RuntimeException('Customer name column not found.');
        }

        if ($columns['address'] === null) {
            throw new RuntimeException('Address column not found.');
        }

        if ($columns['product'] === null) {
            throw new RuntimeException('Milk/product column not found.');
        }

        return $columns;
    }

    private function buildCustomerDirectory(array $sheets): array
    {
        $customerSheet = null;

        foreach ($sheets as $sheet) {
            if ($this->normalizeHeader($sheet->getTitle()) === 'customers') {
                $customerSheet = $sheet;
                break;
            }
        }

        if (!$customerSheet) {
            throw new RuntimeException(
                'Customers worksheet not found. It is required to resolve phone numbers for older monthly sheets.'
            );
        }

        $rows = [];

        foreach ($customerSheet->getRowIterator() as $row) {
            $currentRow = [];

            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(true);

            foreach ($cellIterator as $cell) {
                $columnIndex =
                    Coordinate::columnIndexFromString(
                        $cell->getColumn()
                    ) - 1;

                $currentRow[$columnIndex] =
                    $this->safeCellValue($cell);
            }

            $rows[$row->getRowIndex() - 1] = $currentRow;
        }

        $headerRowIndex = null;

        foreach (array_slice($rows, 0, 30, true) as $index => $row) {
            $normalized = array_map(
                fn($value) => $this->normalizeHeader((string) $value),
                $row
            );

            $hasName = $this->containsAnyHeader($normalized, [
                'name',
                'customer_name',
                'display_name',
            ]);

            $hasPhone = $this->containsAnyHeader($normalized, [
                'phone',
                'phone_no',
                'phone_number',
                'mobile',
                'mobile_number',
            ]);

            $hasAddress = $this->containsAnyHeader($normalized, [
                'address',
                'address_full',
                'delivery_address',
                'location',
            ]);

            if ($hasName && $hasPhone && $hasAddress) {
                $headerRowIndex = $index;
                break;
            }
        }

        if ($headerRowIndex === null) {
            throw new RuntimeException(
                'Could not detect the header row in the Customers worksheet.'
            );
        }

        $columns = $this->detectCustomerDirectoryColumns(
            $rows[$headerRowIndex]
        );

        $directory = [
            'by_name_address' => [],
            'by_unique_name' => [],
        ];

        $phonesByName = [];

        foreach (
            array_slice($rows, $headerRowIndex + 1, null, true)
            as $row
        ) {
            $name = $this->cleanText(
                $this->cell($row, $columns['customer_name'])
            );

            $address = $this->cleanText(
                $this->cell($row, $columns['address'])
            );

            $phone = $this->normalizePhone(
                $this->cell($row, $columns['phone'])
            );

            if ($name === '' || $phone === '') {
                continue;
            }

            $nameKey = $this->normalizeLookupText($name);
            $addressKey = $this->normalizeLookupText($address);

            if ($nameKey === '') {
                continue;
            }

            if ($addressKey !== '') {
                $directory['by_name_address'][$nameKey . '|' . $addressKey] = $phone;
            }

            $phonesByName[$nameKey][$phone] = true;
        }

        /*
         * Name-only matching is safe only when that normalized name maps
         * to one unique phone in the Customers worksheet.
         */
        foreach ($phonesByName as $nameKey => $phones) {
            if (count($phones) === 1) {
                $directory['by_unique_name'][$nameKey] =
                    array_key_first($phones);
            }
        }

        return $directory;
    }

    private function detectCustomerDirectoryColumns(array $header): array
    {
        $normalized = [];

        foreach ($header as $index => $value) {
            $normalized[$index] =
                $this->normalizeHeader((string) $value);
        }

        $columns = [
            'customer_name' => $this->findColumn($normalized, [
                'name',
                'customer_name',
                'display_name',
            ]),

            'phone' => $this->findColumn($normalized, [
                'phone',
                'phone_no',
                'phone_number',
                'mobile',
                'mobile_number',
            ]),

            'address' => $this->findColumn($normalized, [
                'address',
                'address_full',
                'delivery_address',
                'location',
            ]),
        ];

        foreach ($columns as $key => $column) {
            if ($column === null) {
                throw new RuntimeException(
                    "Customers worksheet {$key} column not found."
                );
            }
        }

        return $columns;
    }

    private function resolvePhoneFromDirectory(
        array $directory,
        string $name,
        string $address
    ): string {
        $nameKey = $this->normalizeLookupText($name);
        $addressKey = $this->normalizeLookupText($address);

        if ($nameKey === '') {
            return '';
        }

        if ($addressKey !== '') {
            $combinedKey = $nameKey . '|' . $addressKey;

            if (isset($directory['by_name_address'][$combinedKey])) {
                return $directory['by_name_address'][$combinedKey];
            }
        }

        return $directory['by_unique_name'][$nameKey] ?? '';
    }

    private function normalizeLookupText(string $value): string
    {
        $value = $this->cleanText($value);
        $value = mb_strtolower($value, 'UTF-8');

        /*
         * Keep letters and numbers from English/Telugu and remove
         * punctuation or spacing differences.
         */
        $value = preg_replace('/[^\p{L}\p{N}]+/u', '', $value);

        return trim($value);
    }

    private function findMonthSheet(
        array $sheets,
        array $aliases
    ): ?Worksheet {
        foreach ($sheets as $sheet) {
            $title = $this->normalizeHeader($sheet->getTitle());

            foreach ($aliases as $alias) {
                if ($title === $this->normalizeHeader($alias)) {
                    return $sheet;
                }
            }
        }

        /*
         * Fallback: sheet title may contain extra words such as
         * "Milk January 2026".
         */
        foreach ($sheets as $sheet) {
            $title = $this->normalizeHeader($sheet->getTitle());

            foreach ($aliases as $alias) {
                $normalizedAlias = $this->normalizeHeader($alias);

                if (
                    $normalizedAlias !== '' &&
                    str_contains($title, $normalizedAlias)
                ) {
                    return $sheet;
                }
            }
        }

        return null;
    }

    private function findColumn(
        array $normalizedHeaders,
        array $aliases
    ): ?int {
        foreach ($aliases as $alias) {
            $alias = $this->normalizeHeader($alias);

            foreach ($normalizedHeaders as $index => $header) {
                if ($header === $alias) {
                    return (int) $index;
                }
            }
        }

        /*
         * Second pass handles headers such as:
         * "Payment Received April"
         */
        foreach ($aliases as $alias) {
            $alias = $this->normalizeHeader($alias);

            foreach ($normalizedHeaders as $index => $header) {
                if (
                    $alias !== '' &&
                    $header !== '' &&
                    (
                        str_contains($header, $alias) ||
                        str_contains($alias, $header)
                    )
                ) {
                    return (int) $index;
                }
            }
        }

        return null;
    }

    private function containsAnyHeader(
        array $headers,
        array $aliases
    ): bool {
        return $this->findColumn($headers, $aliases) !== null;
    }

    private function findUserByPhone(string $phone): ?object
    {
        return DB::table('users')
            ->where(function ($query) use ($phone) {
                $query
                    ->where('phone', $phone)
                    ->orWhere('phone', '+91' . $phone)
                    ->orWhere('phone', '91' . $phone)
                    ->orWhere('phone', '0' . $phone)
                    ->orWhere('phone_normalized', $phone)
                    ->orWhere('natural_key', $phone);
            })
            ->select([
                'id',
                'display_name',
                'name',
                'phone',
            ])
            ->first();
    }

    private function normalizePhone(mixed $value): string
    {
        $phone = trim((string) $value);

        /*
         * Handle Excel scientific notation.
         */
        if (
            $phone !== '' &&
            preg_match('/e[+-]?\d+/i', $phone)
        ) {
            $phone = sprintf('%.0f', (float) $phone);
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if (strlen($digits) > 10) {
            $digits = substr($digits, -10);
        }

        return strlen($digits) === 10 ? $digits : '';
    }

    private function normalizeHeader(string $value): string
    {
        $value = trim($value);
        $value = str_replace(["\r", "\n", "\t"], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '_', $value);

        return trim($value, '_');
    }

    private function cleanText(mixed $value): string
    {
        $value = trim((string) $value);
        $value = str_replace(["\r", "\n", "\t"], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim($value);
    }

    private function numericValue(mixed $value): float
    {
        $value = $this->cleanNumericCell($value);

        return is_numeric($value)
            ? round((float) $value, 4)
            : 0.0;
    }

    private function moneyValue(mixed $value): float
    {
        $value = $this->cleanNumericCell($value);

        return is_numeric($value)
            ? round((float) $value, 2)
            : 0.0;
    }

    private function cleanNumericCell(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        $value = trim((string) $value);

        /*
         * Spreadsheet errors must not become money.
         */
        if (
            $value === '' ||
            str_starts_with($value, '#')
        ) {
            return 0;
        }

        /*
         * Remove currency signs, commas and whitespace.
         */
        $value = str_replace(
            ['₹', ',', ' ', "\xc2\xa0"],
            '',
            $value
        );

        /*
         * Parentheses represent a negative value:
         * (500) => -500
         */
        if (
            str_starts_with($value, '(') &&
            str_ends_with($value, ')')
        ) {
            $value = '-' . trim($value, '()');
        }

        return $value;
    }

    private function cell(array $row, ?int $column): mixed
    {
        if ($column === null) {
            return null;
        }

        return $row[$column] ?? null;
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function safeCellValue(Cell $cell): mixed
    {
        if ($cell->getDataType() === DataType::TYPE_FORMULA) {
            /*
         * Use the result saved by Excel without asking
         * PhpSpreadsheet to recalculate the formula.
         */
            $cachedValue = $cell->getOldCalculatedValue();

            if (
                $cachedValue !== null &&
                $cachedValue !== ''
            ) {
                return $cachedValue;
            }

            /*
         * No cached result is available.
         * Return blank so formula text does not become
         * customer name, phone or money.
         */
            return null;
        }

        return $cell->getValue();
    }


    /**
     * Build the operational subscription history for one customer.
     *
     * Output:
     * - one plan per mapped product/variant
     * - create/pause/resume/modify segments across Jan-Jun 2026
     * - final segment remains open-ended so the normal daily-order command
     *   can continue the live workflow after the historical period.
     */
    public function buildSubscriptionPlans(array $customer): array
    {
        $productDays = [];

        foreach ($customer['months'] ?? [] as $month => $monthData) {
            foreach ($monthData['rows'] ?? [] as $row) {
                $mapping = $row['product_mapping'] ?? null;

                if (!$mapping) {
                    continue;
                }

                $productId = (int) $mapping['product_id'];
                $variantId = (int) $mapping['variant_id'];
                $productKey = $productId . ':' . $variantId;

                $productDays[$productKey] ??= [
                    'mapping' => $mapping,
                    'source_rows' => [],
                    'daily' => [],
                ];

                $productDays[$productKey]['source_rows'][] = [
                    'month' => $month,
                    'sheet' => $row['sheet'] ?? null,
                    'excel_row' => $row['excel_row'] ?? null,
                    'product_name' => $row['product_name'] ?? null,
                ];

                foreach ($row['daily_quantities'] ?? [] as $day => $qty) {
                    $date = Carbon::createFromFormat(
                        '!Y-m-d',
                        sprintf('%s-%02d', $month, (int) $day)
                    );

                    $dateKey = $date->toDateString();

                    /*
                     * If the same product appears more than once on the same
                     * customer/day, add the quantities instead of losing data.
                     */
                    $productDays[$productKey]['daily'][$dateKey] =
                        round(
                            (float) ($productDays[$productKey]['daily'][$dateKey] ?? 0)
                                + max(0, (float) $qty),
                            4
                        );
                }
            }
        }

        $plans = [];

        foreach ($productDays as $productKey => $productData) {
            $dailyStates = [];

            /*
             * Build one continuous Jan 1-Jun 30 timeline.
             * Missing product rows/days are treated as paused quantity 0.
             */
            for (
                $date = Carbon::create(2026, 1, 1);
                $date->lte(Carbon::create(2026, 6, 30));
                $date->addDay()
            ) {
                $qty = round(
                    (float) ($productData['daily'][$date->toDateString()] ?? 0),
                    4
                );

                $dailyStates[] = [
                    'date' => $date->copy(),
                    'qty' => $qty,
                    'flag' => $qty > 0 ? 1 : 0,
                ];
            }

            $segments = $this->buildHistoricalSegments($dailyStates);

            if (empty($segments)) {
                continue;
            }

            $previousQty = null;

            foreach ($segments as $index => &$segment) {
                $segment['change_action'] = $this->determineHistoricalAction(
                    $index,
                    (float) $segment['qty'],
                    $previousQty
                );

                $previousQty = (float) $segment['qty'];
            }
            unset($segment);

            $plans[] = [
                'key' => $productKey,
                'mapping' => $productData['mapping'],
                'source_rows' => $productData['source_rows'],
                'segments' => $segments,
            ];
        }

        return $plans;
    }

    /**
     * Resolve the price that was valid on the segment start date.
     * Falls back to the current variant price and then the workbook mapping.
     */
    public function resolveVariantPriceForHistoricalDate(
        int $productId,
        int $variantId,
        Carbon $date,
        ?float $fallback = null
    ): ?float {
        $dateTime = $date->copy()->endOfDay()->toDateTimeString();

        $history = DB::table('variant_price_history')
            ->where('product_id', $productId)
            ->where('variant_id', $variantId)
            ->where('effective_from', '<=', $dateTime)
            ->where(function ($query) use ($dateTime) {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $dateTime);
            })
            ->orderByDesc('effective_from')
            ->first();

        if ($history && $history->price !== null) {
            return (float) $history->price;
        }

        $variant = DB::table('variants')
            ->where('variant_id', $variantId)
            ->first();

        if ($variant && $variant->price !== null) {
            return (float) $variant->price;
        }

        return $fallback;
    }

    private function buildHistoricalSegments(array $dailyStates): array
    {
        if (empty($dailyStates)) {
            return [];
        }

        $firstPositiveIndex = null;

        foreach ($dailyStates as $index => $day) {
            if ((float) $day['qty'] > 0) {
                $firstPositiveIndex = $index;
                break;
            }
        }

        /*
         * A completely unused product does not create a DOI.
         */
        if ($firstPositiveIndex === null) {
            return [];
        }

        $dailyStates = array_slice($dailyStates, $firstPositiveIndex);

        $segments = $this->buildHistoricalPatternSegments($dailyStates);

        return $this->splitHistoricalSegmentsByActualQty(
            $segments,
            $dailyStates
        );
    }

    private function buildHistoricalPatternSegments(array $dailyStates): array
    {
        $segments = [];
        $count = count($dailyStates);
        $index = 0;

        while ($index < $count) {
            $currentFlag = (int) $dailyStates[$index]['flag'];

            /*
             * Pause run: 0 0 0 ...
             */
            if ($currentFlag === 0) {
                $start = $dailyStates[$index]['date']->copy();
                $endIndex = $index;

                while (
                    $endIndex + 1 < $count
                    && (int) $dailyStates[$endIndex + 1]['flag'] === 0
                ) {
                    $endIndex++;
                }

                $segments[] = [
                    'start_date' => $start,
                    'end_date' => $dailyStates[$endIndex]['date']->copy(),
                    'qty' => 0.0,
                    'frequency_type' => null,
                    'pattern_start_value' => null,
                    'pattern_kind' => 'pause',
                ];

                $index = $endIndex + 1;
                continue;
            }

            $start = $dailyStates[$index]['date']->copy();

            /*
             * Candidate daily run: 1 1 1 ...
             */
            $dailyEnd = $index;

            while (
                $dailyEnd + 1 < $count
                && (int) $dailyStates[$dailyEnd + 1]['flag'] === 1
            ) {
                $dailyEnd++;
            }

            $dailyLength = $dailyEnd - $index + 1;

            /*
             * Candidate alternate run: 1 0 1 0 ...
             */
            $alternateEnd = $index;
            $expected = 0;

            while ($alternateEnd + 1 < $count) {
                $nextFlag = (int) $dailyStates[$alternateEnd + 1]['flag'];

                if ($nextFlag !== $expected) {
                    break;
                }

                $alternateEnd++;
                $expected = $expected === 1 ? 0 : 1;
            }

            $alternateLength = $alternateEnd - $index + 1;

            if (
                $alternateLength >= 2
                && $alternateLength > $dailyLength
            ) {
                $segments[] = [
                    'start_date' => $start,
                    'end_date' => $dailyStates[$alternateEnd]['date']->copy(),
                    'qty' => 1.0,
                    'frequency_type' => 'alternate_days',
                    'pattern_start_value' => 1,
                    'pattern_kind' => 'alternate',
                ];

                $index = $alternateEnd + 1;
                continue;
            }

            $segments[] = [
                'start_date' => $start,
                'end_date' => $dailyStates[$dailyEnd]['date']->copy(),
                'qty' => 1.0,
                'frequency_type' => 'daily',
                'pattern_start_value' => 1,
                'pattern_kind' => 'daily',
            ];

            $index = $dailyEnd + 1;
        }

        /*
         * The final operational state stays open-ended:
         * active => future daily orders continue
         * paused => future daily orders remain stopped
         */
        if (!empty($segments)) {
            $segments[count($segments) - 1]['end_date'] = null;
        }

        return $segments;
    }

    private function splitHistoricalSegmentsByActualQty(
        array $segments,
        array $dailyStates
    ): array {
        $result = [];
        $dayMap = [];

        foreach ($dailyStates as $day) {
            $dayMap[$day['date']->toDateString()] = [
                'qty' => (float) $day['qty'],
                'flag' => (int) $day['flag'],
            ];
        }

        foreach ($segments as $segment) {
            if ((float) $segment['qty'] === 0.0) {
                $result[] = $segment;
                continue;
            }

            $start = $segment['start_date']->copy();
            $end = $segment['end_date']
                ? $segment['end_date']->copy()
                : end($dailyStates)['date']->copy();

            $currentStart = null;
            $currentQty = null;
            $lastIncludedDate = null;

            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $day = $dayMap[$date->toDateString()] ?? [
                    'qty' => 0.0,
                    'flag' => 0,
                ];

                if ((int) $day['flag'] === 0) {
                    continue;
                }

                $actualQty = (float) $day['qty'];

                if ($currentStart === null) {
                    $currentStart = $date->copy();
                    $currentQty = $actualQty;
                    $lastIncludedDate = $date->copy();
                    continue;
                }

                if ($actualQty !== $currentQty) {
                    $result[] = [
                        'start_date' => $currentStart->copy(),
                        'end_date' => $lastIncludedDate->copy(),
                        'qty' => $currentQty,
                        'frequency_type' => $segment['frequency_type'],
                        'pattern_start_value' =>
                        $segment['pattern_start_value'] ?? null,
                        'pattern_kind' =>
                        $segment['pattern_kind'] ?? null,
                    ];

                    $currentStart = $date->copy();
                    $currentQty = $actualQty;
                }

                $lastIncludedDate = $date->copy();
            }

            if ($currentStart !== null) {
                $result[] = [
                    'start_date' => $currentStart->copy(),
                    'end_date' => $lastIncludedDate?->copy(),
                    'qty' => $currentQty,
                    'frequency_type' => $segment['frequency_type'],
                    'pattern_start_value' =>
                    $segment['pattern_start_value'] ?? null,
                    'pattern_kind' =>
                    $segment['pattern_kind'] ?? null,
                ];
            }
        }

        if (!empty($result) && !empty($segments)) {
            $lastOriginal = end($segments);

            if (($lastOriginal['end_date'] ?? null) === null) {
                $result[count($result) - 1]['end_date'] = null;
            }
        }

        return $result;
    }

    private function determineHistoricalAction(
        int $index,
        float $currentQty,
        ?float $previousQty
    ): string {
        if ($index === 0) {
            return 'create';
        }

        if ($currentQty == 0.0) {
            return 'pause';
        }

        if ($previousQty == 0.0 && $currentQty > 0) {
            return 'resume';
        }

        return 'modify';
    }

    private function mapHistoricalProduct(string $excelName): ?array
    {
        $normalized = strtolower(trim($excelName));

        $normalized = preg_replace(
            '/[^a-z0-9]+/',
            '-',
            $normalized
        );

        $normalized = trim($normalized, '-');

        return match ($normalized) {
            'vijaya-gold' => [
                'product_id' => 8383403720978,
                'variant_id' => 52149488976146,
                'title' => 'Vijaya Gold Milk(500 ml)',
                'variant' => 'Vijaya Gold Milk(500 ml)',
                'unit_price' => 37.00,
            ],

            'vijaya-tm' => [
                'product_id' => 8425366782226,
                'variant_id' => 45623554146578,
                'title' => 'Vijaya Toned Milk(500ml)',
                'variant' => 'Vijaya Toned Milk(500ml)',
                'unit_price' => 30.00,
            ],

            'vijaya-tm-small' => [
                'product_id' => 8425366782226,
                'variant_id' => 52148028047634,
                'title' => 'Vijaya Toned Milk(500ml)',
                'variant' => 'Vijaya Toned Milk Small',
                'unit_price' => 10.00,
            ],

            'hatsun-curd' => [
                'product_id' => 8409961103634,
                'variant_id' => 45560024826130,
                'title' => 'Hatsun Curd',
                'variant' => 'Hatsun Curd (Big, 400 g)',
                'unit_price' => 40.00,
            ],

            'hatsun-curd-small' => [
                'product_id' => 8409961103634,
                'variant_id' => 52149601829138,
                'title' => 'Hatsun Curd',
                'variant' => 'Hatsun Curd (Small, 110 g)',
                'unit_price' => 10.00,
            ],

            'vijaya-curd' => [
                'product_id' => 8421025218834,
                'variant_id' => 45608528314642,
                'title' => 'Vijaya Curd',
                'variant' => 'Vijaya Curd(500 ml)',
                'unit_price' => 35.00,
            ],

            'vijaya-curd-small' => [
                'product_id' => 8421025218834,
                'variant_id' => 51886976270610,
                'title' => 'Vijaya Curd',
                'variant' => 'Vijaya Curd Small',
                'unit_price' => 10.00,
            ],

            'vijaya-curd-bucket' => [
                'product_id' => 8421025218834,
                'variant_id' => 52213194490130,
                'title' => 'Vijaya Curd',
                'variant' => 'Vijaya Curd 5kg Tub',
                'unit_price' => 320.00,
            ],

            'vijaya-buttermilk-small' => [
                'product_id' => 10339468935442,
                'variant_id' => 52217769394450,
                'title' => 'Vijaya Butter Milk',
                'variant' => 'Vijaya Butter Milk',
                'unit_price' => 10.00,
            ],

            /*
       /*
 * Confirmed mappings
 */
            'arokya-gold' => [
                'product_id' => 8383403917586,
                'variant_id' => 45490819596562,
                'title' => 'Arokya Gold(500 ml)',
                'variant' => 'Arokya Gold(500 ml)',
                'unit_price' => 40.00,
            ],

            'arokya-tm',
            'arokya-tm-small' => [
                'product_id' => 8425394307346,
                'variant_id' => 52148034765074,
                'title' => 'Arokya toned Milk(500 ml)',
                'variant' => 'Arokya TM Small',
                'unit_price' => 10.00,
            ],

            /*
 * Still not mapped.
 */
            'sangam-gold',
            'eenadu-newspaper' => null,

            default => null,
        };
    }
}
