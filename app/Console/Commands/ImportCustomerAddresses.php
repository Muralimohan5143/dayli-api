<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

class ImportCustomerAddresses extends Command
{
    /**
     * Usage examples:
     *
     * php artisan customers:import-addresses "C:\Users\mandl\Downloads\milk_supply_H1_2026.xlsx" --dry-run
     * php artisan customers:import-addresses "C:\Users\mandl\Downloads\milk_supply_H1_2026.xlsx"
     * php artisan customers:import-addresses "/path/to/milk_supply_H1_2026.xlsx" --force
     */
    protected $signature = 'customers:import-addresses
        {file : Full path of the Excel workbook}
        {--sheet=Customers : Worksheet name}
        {--city=Kurnool : City saved for imported addresses}
        {--state=Andhra Pradesh : State saved for imported addresses}
        {--country=India : Country saved for imported addresses}
        {--dry-run : Validate and show summary without writing to the database}
        {--force : Update an existing default address}
        {--include-inactive : Include rows whose isActive value is not y/yes/1/true}';

    protected $description = 'Import customer addresses from only the Customers worksheet into the addresses table';

    private const USER_TYPE = User::class;

    public function handle(): int
    {
        ini_set('memory_limit', '512M');
        $file = (string) $this->argument('file');
        $sheetName = trim((string) $this->option('sheet')) ?: 'Customers';
        $city = trim((string) $this->option('city')) ?: 'Kurnool';
        $state = trim((string) $this->option('state')) ?: 'Andhra Pradesh';
        $country = trim((string) $this->option('country')) ?: 'India';
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $includeInactive = (bool) $this->option('include-inactive');

        if (!is_file($file)) {
            $this->error("Workbook not found: {$file}");
            return self::FAILURE;
        }

        try {
            $reader = IOFactory::createReaderForFile($file);
            $reader->setReadDataOnly(true);
            $reader = IOFactory::createReaderForFile($file);
            $reader->setReadDataOnly(true);

            // Load ONLY Customers sheet
            $reader->setLoadSheetsOnly(['Customers']);

            $spreadsheet = $reader->load($file);
        } catch (Throwable $e) {
            $this->error('Unable to open workbook: ' . $e->getMessage());
            return self::FAILURE;
        }

        $worksheet = $spreadsheet->getSheetByName($sheetName);

        if ($worksheet === null) {
            $this->error("Worksheet '{$sheetName}' was not found.");
            $this->line('Available sheets: ' . implode(', ', $spreadsheet->getSheetNames()));
            return self::FAILURE;
        }

        /*
         * Customers sheet columns:
         * D = isActive
         * F = Name
         * G = Phone No.
         * H = Address/locality
         * I = Plot No
         * Q = Clean/bilingual Address
         */
        $highestRow = $worksheet->getHighestDataRow();

        $stats = [
            'sheet_rows' => max(0, $highestRow - 1),
            'active_rows' => 0,
            'inactive_rows_skipped' => 0,
            'rows_without_phone' => 0,
            'unique_customers' => 0,
            'users_matched' => 0,
            'users_missing' => 0,
            'addresses_created' => 0,
            'addresses_updated' => 0,
            'existing_skipped' => 0,
            'no_address_data' => 0,
            'errors' => 0,
        ];

        /*
         * Build one record per phone because the Customers sheet can contain
         * several milk-product rows for the same customer.
         */
        $customers = [];

        for ($row = 2; $row <= $highestRow; $row++) {
            $isActive = $this->cleanText($worksheet->getCell("D{$row}")->getValue());

            if (!$includeInactive && !$this->isActiveValue($isActive)) {

                $this->line("Row {$row} => isActive = [{$isActive}]");

                $stats['inactive_rows_skipped']++;
                continue;
            }

            $stats['active_rows']++;

            $phone = $this->normalizePhone(
                $worksheet->getCell("G{$row}")->getFormattedValue()
            );

            if ($phone === null) {
                $stats['rows_without_phone']++;
                continue;
            }

            $name = $this->cleanText($worksheet->getCell("F{$row}")->getValue());
            $rawAddress = $this->cleanText($worksheet->getCell("H{$row}")->getValue());
            $plotNo = $this->cleanPlotNo($worksheet->getCell("I{$row}")->getFormattedValue());
            $finalAddress = $this->firstAddressLine(
                $this->cleanText($worksheet->getCell("Q{$row}")->getCalculatedValue())
            );

            $nagar = $this->chooseNagar($rawAddress, $finalAddress);
            $line1 = $plotNo !== null ? "Plot/Flat No. {$plotNo}" : null;

            // Keep a distinct final address in line2 only when it adds information.
            $line2 = null;
            if (
                $this->isUsefulAddress($finalAddress)
                && !$this->sameText($finalAddress, $nagar)
            ) {
                $line2 = $finalAddress;
            }

            $incoming = [
                'phone' => $phone,
                'name' => $name,
                'line1' => $line1,
                'line2' => $line2,
                'nagar' => $nagar,
                'source_row' => $row,
            ];

            if (!isset($customers[$phone])) {
                $customers[$phone] = $incoming;
                continue;
            }

            // Merge better/missing values from duplicate product rows.
            foreach (['name', 'line1', 'line2', 'nagar'] as $field) {
                if (
                    $this->isBlank($customers[$phone][$field] ?? null)
                    && !$this->isBlank($incoming[$field] ?? null)
                ) {
                    $customers[$phone][$field] = $incoming[$field];
                }
            }
        }

        $stats['unique_customers'] = count($customers);

        /*
         * Build an in-memory phone map to avoid one user query per row.
         * We compare by the final 10 digits, so +91, spaces and hyphens are safe.
         */
        $usersByPhone = [];

        User::query()
            ->select(['id', 'phone', 'zone_id', 'name'])
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById(500, function ($users) use (&$usersByPhone): void {
                foreach ($users as $user) {
                    $phone = $this->normalizePhone($user->phone);

                    if ($phone !== null && !isset($usersByPhone[$phone])) {
                        $usersByPhone[$phone] = $user;
                    }
                }
            });

        $missingUsers = [];
        $errorRows = [];

        $runImport = function () use (
            $customers,
            $usersByPhone,
            $city,
            $state,
            $country,
            $dryRun,
            $force,
            &$stats,
            &$missingUsers,
            &$errorRows
        ): void {
            foreach ($customers as $phone => $customer) {
                $user = $usersByPhone[$phone] ?? null;

                if ($user === null) {
                    $stats['users_missing']++;
                    $missingUsers[] = [
                        $phone,
                        $customer['name'] ?: '-',
                        $customer['nagar'] ?: '-',
                        $customer['source_row'],
                    ];
                    continue;
                }

                $stats['users_matched']++;

                if (
                    $this->isBlank($customer['line1'])
                    && $this->isBlank($customer['line2'])
                    && $this->isBlank($customer['nagar'])
                ) {
                    $stats['no_address_data']++;
                    continue;
                }

                try {
                    $existing = DB::table('addresses')
                        ->where('addressable_type', self::USER_TYPE)
                        ->where('addressable_id', $user->id)
                        ->where('is_default', 1)
                        ->whereNull('deleted_at')
                        ->orderBy('id')
                        ->first();

                    $data = [
                        'zone_id' => $user->zone_id,
                        'line1' => $customer['line1'],
                        'line2' => $customer['line2'],
                        'nagar' => $customer['nagar'],
                        'city' => $city,
                        'state' => $state,
                        'country' => $country,
                        'pincode' => null,
                        'lat' => null,
                        'lng' => null,
                        'is_default' => 1,
                        'updated_at' => now(),
                    ];

                    if ($existing !== null) {
                        if (!$force) {
                            $stats['existing_skipped']++;
                            continue;
                        }

                        if (!$dryRun) {
                            DB::table('addresses')
                                ->where('id', $existing->id)
                                ->update($data);
                        }

                        $stats['addresses_updated']++;
                        continue;
                    }

                    if (!$dryRun) {
                        DB::table('addresses')->insert([
                            'addressable_type' => self::USER_TYPE,
                            'addressable_id' => $user->id,
                            ...$data,
                            'created_at' => now(),
                            'deleted_at' => null,
                        ]);
                    }

                    $stats['addresses_created']++;
                } catch (Throwable $e) {
                    $stats['errors']++;
                    $errorRows[] = [
                        $phone,
                        $customer['name'] ?: '-',
                        $customer['source_row'],
                        $e->getMessage(),
                    ];
                }
            }
        };

        if ($dryRun) {
            $runImport();
        } else {
            DB::transaction($runImport);
        }

        $this->newLine();
        $this->info(
            $dryRun
                ? 'Dry run completed. No database changes were made.'
                : 'Customer address import completed.'
        );

        $this->table(
            ['Result', 'Count'],
            [
                ['Sheet rows', $stats['sheet_rows']],
                ['Active rows processed', $stats['active_rows']],
                ['Inactive rows skipped', $stats['inactive_rows_skipped']],
                ['Rows without valid phone', $stats['rows_without_phone']],
                ['Unique customers', $stats['unique_customers']],
                ['Users matched', $stats['users_matched']],
                ['Users missing', $stats['users_missing']],
                ['Addresses created', $stats['addresses_created']],
                ['Addresses updated', $stats['addresses_updated']],
                ['Existing addresses skipped', $stats['existing_skipped']],
                ['Customers without address data', $stats['no_address_data']],
                ['Errors', $stats['errors']],
            ]
        );

        if ($missingUsers !== []) {
            $this->newLine();
            $this->warn('Customers whose phone number did not match a user:');
            $this->table(
                ['Phone', 'Name', 'Address', 'Excel row'],
                array_slice($missingUsers, 0, 30)
            );

            if (count($missingUsers) > 30) {
                $this->line('...and ' . (count($missingUsers) - 30) . ' more.');
            }
        }

        if ($errorRows !== []) {
            $this->newLine();
            $this->error('Import errors:');
            $this->table(
                ['Phone', 'Name', 'Excel row', 'Error'],
                array_slice($errorRows, 0, 20)
            );
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function normalizePhone(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value);

        if (!is_string($digits) || $digits === '') {
            return null;
        }

        if (strlen($digits) > 10) {
            $digits = substr($digits, -10);
        }

        return strlen($digits) === 10 ? $digits : null;
    }

    private function cleanText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = preg_replace('/\s+/u', ' ', trim((string) $value));

        return is_string($text) && $text !== '' ? $text : null;
    }

    private function firstAddressLine(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $parts = preg_split('/[\r\n]+/u', $value);
        $first = $parts[0] ?? null;

        return $this->cleanText($first);
    }

    private function cleanPlotNo(mixed $value): ?string
    {
        $value = $this->cleanText($value);

        if ($value === null) {
            return null;
        }

        $normalized = strtolower(trim($value));

        if (
            $normalized === ''
            || str_contains($normalized, '?')
            || in_array($normalized, ['0', '0.0', '-', 'na', 'n/a', 'none'], true)
        ) {
            return null;
        }

        if (preg_match('/^\d+\.0+$/', $normalized)) {
            $normalized = strstr($normalized, '.', true);
        }

        return $normalized !== '' ? $normalized : null;
    }

    private function chooseNagar(?string $rawAddress, ?string $finalAddress): ?string
    {
        if ($this->isUsefulAddress($rawAddress)) {
            return $rawAddress;
        }

        if ($this->isUsefulAddress($finalAddress)) {
            return $finalAddress;
        }

        return null;
    }

    private function isUsefulAddress(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        $normalized = strtolower(trim($value));

        return !in_array($normalized, [
            '',
            'f',
            '?',
            '??',
            '-',
            'na',
            'n/a',
            'none',
            'null',
            'unknown',
            'unknown1',
            'unknown2',
        ], true);
    }

    private function isActiveValue(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        return in_array(strtolower(trim($value)), [
            'y',
            'yes',
            '1',
            'true',
            'active',
        ], true);
    }

    private function sameText(?string $left, ?string $right): bool
    {
        if ($left === null || $right === null) {
            return false;
        }

        return mb_strtolower(trim($left)) === mb_strtolower(trim($right));
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || trim((string) $value) === '';
    }
}
