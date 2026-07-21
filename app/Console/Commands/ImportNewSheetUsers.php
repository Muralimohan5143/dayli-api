<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class ImportNewSheetUsers extends Command
{
    protected $signature = 'import:new-sheet-users
        {file_path : Full path to XLSX/CSV}
        {--dry-run : Do not write to DB}
        {--default-zone= : Set users.zone_id to this if provided}
        {--sheet=0 : XLSX sheet index (0-based)}
        {--dedupe=first : first|last (for duplicates in file)}
    ';



    //php artisan import:new-sheet-users "C:\Users\mandl\Downloads\New Microsoft Excel Worksheet.xlsx" --default-zone=1
    // this file is for importing UNIQUE users from a sheet with columns: display_name/name=Name, phone=Phone Number, address=Address Full (into users table only). It does not handle orders or other related data. Use with --dry-run first to verify column mapping and data parsing. For large files, consider splitting into smaller chunks. Always backup your database before running imports.

    //command for insert users table
    // php artisan import:new-sheet-users "C:\Users\mandl\work\flutter projects\users_updated.xlsx" --default-zone=1
    protected $description = "Import UNIQUE users from sheet: display_name/name=Name, phone=Phone Number, address=Address Full (into users table only).";

    public function handle(): int
    {
        $path = (string) $this->argument('file_path');
        $dryRun = (bool) $this->option('dry-run');

        $defaultZone = $this->option('default-zone');
        $defaultZone = ($defaultZone !== null && $defaultZone !== '') ? (int)$defaultZone : null;

        $sheetIndex = (int) $this->option('sheet');
        $dedupeMode = strtolower((string)$this->option('dedupe'));
        if (!in_array($dedupeMode, ['first', 'last'], true)) $dedupeMode = 'first';

        if (!is_file($path)) {
            $this->error("File not found: {$path}");
            return self::FAILURE;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        try {
            if (in_array($ext, ['xlsx', 'xls'], true)) {
                return $this->importFromXlsx($path, $sheetIndex, $dryRun, $defaultZone, $dedupeMode);
            }

            // CSV fallback (works only for proper CSV)
            return $this->importFromCsv($path, $dryRun, $defaultZone, $dedupeMode);
        } catch (Throwable $e) {
            $this->error("IMPORT FAILED: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function importFromXlsx(string $path, int $sheetIndex, bool $dryRun, ?int $defaultZone, string $dedupeMode): int
    {
        /** @var Xlsx $reader */
        $reader = new Xlsx();

        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        $reader->setIncludeCharts(false);

        // Read worksheet metadata without loading all cell data
        $worksheetInfo = $reader->listWorksheetInfo($path);

        $sheetNames = array_map(
            fn(array $sheet): string => $sheet['worksheetName'],
            $worksheetInfo
        );

        if (!isset($sheetNames[$sheetIndex])) {
            $this->error("Invalid sheet index: {$sheetIndex}");
            $this->line("Available sheets:");

            foreach ($sheetNames as $index => $sheetName) {
                $this->line("{$index} = {$sheetName}");
            }

            return self::FAILURE;
        }

        $sheetName = $sheetNames[$sheetIndex];

        $this->info("Loading sheet {$sheetIndex}: {$sheetName}");

        // setLoadSheetsOnly requires the sheet NAME
        $reader->setLoadSheetsOnly([$sheetName]);

        $spreadsheet = $reader->load($path);

        // Since only one sheet was loaded, its new index is 0
        $sheet = $spreadsheet->getSheet(0);

        // Read rows without calculating formulas.
        // Preserve Excel column letters: A, B, C...
        $rows = [];

        foreach ($sheet->getRowIterator() as $row) {
            $currentRow = [];

            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(true);

            foreach ($cellIterator as $cell) {
                $currentRow[$cell->getColumn()] =
                    $this->safeCellValue($cell);
            }

            $rows[$row->getRowIndex()] = $currentRow;
        }

        if (count($rows) < 2) {
            $this->error("XLSX has no data rows.");
            return self::FAILURE;
        }

        $headerRowNumber = null;
        $headersByCol = [];

        // Search the first 100 worksheet rows for a probable header row.
        foreach ($rows as $excelRowNumber => $candidateRow) {
            if ($excelRowNumber > 100) {
                break;
            }

            $candidateHeaders = [];

            foreach ($candidateRow as $col => $value) {
                $candidateHeaders[$col] = $this->normKey((string) $value);
            }

            $hasName = $this->findAnyCol($candidateHeaders, [
                'name',
                'customer_name',
                'display_name',
                'subscriber_name',
            ]);

            $hasPhone = $this->findAnyCol($candidateHeaders, [
                'phone',
                'phone_number',
                'phone_no',
                'mobile',
                'mobile_number',
                'contact_number',
            ]);

            $hasAddress = $this->findAnyCol($candidateHeaders, [
                'address',
                'address_full',
                'full_address',
                'delivery_address',
                'location',
            ]);

            if ($hasName !== null && $hasPhone !== null) {
                $headerRowNumber = $excelRowNumber;
                $headersByCol = $candidateHeaders;
                break;
            }
        }

        if ($headerRowNumber === null) {
            $this->error('Unable to detect the customer header row.');
            $this->line('First worksheet rows detected:');

            foreach (array_slice($rows, 0, 25, true) as $rowNumber => $row) {
                $values = array_filter(
                    array_map(
                        fn($value) => trim((string) $value),
                        $row
                    ),
                    fn($value) => $value !== ''
                );

                $this->line(
                    "Row {$rowNumber}: " .
                        implode(' | ', array_slice($values, 0, 15))
                );
            }

            return self::FAILURE;
        }

        $this->info("Detected header row: {$headerRowNumber}");

        // Remove header and every row above it.
        $rows = array_filter(
            $rows,
            fn($rowNumber) => $rowNumber > $headerRowNumber,
            ARRAY_FILTER_USE_KEY
        );

        // Required columns
        $colName = $this->findAnyCol($headersByCol, [
            'name',
            'customer_name',
            'display_name',
            'subscriber_name',
        ]);

        $colPhone = $this->findAnyCol($headersByCol, [
            'phone',
            'phone_number',
            'phone_no',
            'mobile',
            'mobile_number',
            'contact_number',
        ]);

        $colAddr = $this->findAnyCol($headersByCol, [
            'address',
            'address_full',
            'full_address',
            'delivery_address',
            'location',
        ]);

        if ($colName === null || $colPhone === null) {
            $this->error(
                'Missing required name or phone column. Found headers: ' .
                    implode(', ', array_filter(array_values($headersByCol)))
            );

            return self::FAILURE;
        }

        // Address may be absent in some historical sheets.
        if ($colAddr === null) {
            $this->warn('Address column was not found. Users will be imported without an address.');
        }

        $this->info(
            'XLSX columns: ' .
                "name={$colName}, " .
                "phone={$colPhone}, " .
                'address=' . ($colAddr ?? 'not found')
        );
        $this->info("XLSX columns: name={$colName}, phone={$colPhone}, address={$colAddr}");

        return $this->processUsers(
            $rows,
            fn($r) => (string)($r[$colName] ?? ''),
            fn($r) => (string)($r[$colPhone] ?? ''),
            fn($r) => $colAddr !== null
                ? (string) ($r[$colAddr] ?? '')
                : '',
            $dryRun,
            $defaultZone,
            $dedupeMode
        );
    }

    private function importFromCsv(string $path, bool $dryRun, ?int $defaultZone, string $dedupeMode): int
    {
        $fh = fopen($path, 'r');
        if (!$fh) {
            $this->error("Unable to open CSV: {$path}");
            return self::FAILURE;
        }

        $header = fgetcsv($fh);
        if (!$header || count($header) < 2) {
            fclose($fh);
            $this->error("CSV header parse failed. Use XLSX export.");
            return self::FAILURE;
        }

        $headerKeys = array_map(fn($h) => $this->normKey((string)$h), $header);

        $iName  = array_search('name', $headerKeys, true);
        $iPhone = array_search('phone_number', $headerKeys, true);
        $iAddr  = array_search('address_full', $headerKeys, true);

        if ($iName === false || $iPhone === false || $iAddr === false) {
            fclose($fh);
            $this->error("Missing required columns in CSV. Found: " . implode(', ', $headerKeys));
            return self::FAILURE;
        }

        $rows = [];
        while (($row = fgetcsv($fh)) !== false) {
            $rows[] = $row;
        }
        fclose($fh);

        return $this->processUsers(
            $rows,
            fn($r) => (string)($r[$iName] ?? ''),
            fn($r) => (string)($r[$iPhone] ?? ''),
            fn($r) => (string)($r[$iAddr] ?? ''),
            $dryRun,
            $defaultZone,
            $dedupeMode
        );
    }

    private function processUsers(
        array $rows,
        callable $getName,
        callable $getPhone,
        callable $getAddr,
        bool $dryRun,
        ?int $defaultZone,
        string $dedupeMode
    ): int {
        $createdUsers = 0;
        $updatedUsers = 0;
        $skipped = 0;
        $dupInFile = 0;

        // phone => ['name'=>..., 'address'=>...]
        $byPhone = [];

        // 1) Build unique list by phone (first or last wins)
        $rowNum = 1;
        foreach ($rows as $row) {
            $rowNum++;

            $nameRaw = trim((string)$getName($row));
            $phoneRaw = trim((string)$getPhone($row));
            $addrRaw = trim((string)$getAddr($row));

            $phoneNorm = $this->normalizePhone($phoneRaw);
            if ($nameRaw === '' || $phoneNorm === '') {
                $this->warn(
                    "Skipped row {$rowNum}: "
                        . "name='{$nameRaw}' "
                        . "phone='{$phoneRaw}' "
                        . "normalized='{$phoneNorm}'"
                );

                $skipped++;
                continue;
            }

            if (isset($byPhone[$phoneNorm])) {
                $dupInFile++;
                if ($dedupeMode === 'first') {
                    continue; // keep existing
                }
                // last wins: overwrite
            }

            $byPhone[$phoneNorm] = [
                'name' => $this->cleanName($nameRaw),
                'address' => $this->cleanAddress($addrRaw),
            ];
        }

        // 2) Process unique users
        $rowOut = 1;
        foreach ($byPhone as $phoneNorm => $payload) {
            $rowOut++;

            $name = $payload['name'];
            $address = $payload['address'];

            if ($dryRun) {
                $this->line("Row {$rowOut} DRY-RUN: name={$name}, phone={$phoneNorm}, zone_id=" . ($defaultZone ?? 'null') . ", address={$address}");
                continue;
            }

            try {
                DB::transaction(function () use (
                    $name,
                    $phoneNorm,
                    $address,
                    $defaultZone,
                    &$createdUsers,
                    &$updatedUsers
                ) {
                    $phone10 = $phoneNorm;
                    $phone91 = '+91' . $phone10;

                    $user = DB::table('users')
                        ->where('phone', $phone10)
                        ->orWhere('phone', $phone91)
                        ->orWhere('phone_normalized', $phone10)
                        ->orWhere('phone_normalized', $phone91)
                        ->orWhere('natural_key', $phone10)
                        ->orWhere('natural_key', $phone91)
                        ->first();

                    if ($user) {
                        $update = [
                            // put in both fields for safety
                            'display_name' => $name ?: $user->display_name,
                            'name' => $name ?: $user->name,
                            'updated_at' => now(),
                        ];

                        if ($defaultZone !== null) {
                            $update['zone_id'] = $defaultZone;
                        }

                        if ($address !== '') {
                            $update['address'] = $address;
                        }

                        DB::table('users')->where('id', $user->id)->update($update);
                        $updatedUsers++;
                    } else {
                        $insert = [
                            'display_name' => $name,
                            'name' => $name,
                            'phone' => '+91' . $phoneNorm,

                            'password' => bcrypt(Str::random(16)),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];

                        if ($defaultZone !== null) {
                            $insert['zone_id'] = $defaultZone;
                        }

                        if ($address !== '') {
                            $insert['address'] = $address;
                        }

                        DB::table('users')->insert($insert);
                        $createdUsers++;
                    }
                });
            } catch (Throwable $e) {
                $skipped++;
                $this->error("Phone {$phoneNorm}: FAILED - " . $e->getMessage());
            }
        }

        $this->info("Done.");
        $this->info("Users created: {$createdUsers}");
        $this->info("Users updated: {$updatedUsers}");
        $this->info("Duplicates skipped in file (same phone): {$dupInFile}");
        $this->info("Rows skipped/failed: {$skipped}");

        return self::SUCCESS;
    }

    private function safeCellValue(Cell $cell): mixed
    {
        if ($cell->getDataType() === DataType::TYPE_FORMULA) {
            // Use Excel's previously saved/cached result.
            // Do not ask PhpSpreadsheet to calculate the formula.
            $cachedValue = $cell->getOldCalculatedValue();

            if ($cachedValue !== null && $cachedValue !== '') {
                return $cachedValue;
            }

            return null;
        }

        return $cell->getValue();
    }

    private function findCol(array $headersByCol, string $wantKey): ?string
    {
        foreach ($headersByCol as $col => $key) {
            if ($key === $wantKey) return $col;
        }
        return null;
    }

    private function findAnyCol(array $headersByCol, array $possibleKeys): ?string
    {
        foreach ($possibleKeys as $key) {
            $column = $this->findCol($headersByCol, $key);

            if ($column !== null) {
                return $column;
            }
        }

        return null;
    }

    private function normKey(string $h): string
    {
        $h = trim($h);
        $h = str_replace(["\r", "\n", "\t"], ' ', $h);
        $h = preg_replace('/\s+/', ' ', $h);
        $h = trim($h, "\"' ");
        $h = strtolower($h);
        $h = preg_replace('/[^a-z0-9]+/', '_', $h);
        return trim($h, '_');
    }

    private function normalizePhone(string $phone): string
    {
        $p = trim($phone);

        // handle Excel scientific notation
        if ($p !== '' && preg_match('/e\+?/i', $p)) {
            $p = sprintf('%.0f', (float) $p);
        }

        $digits = preg_replace('/\D+/', '', $p);

        // keep last 10 digits (+91 etc)
        if (strlen($digits) > 10) {
            $digits = substr($digits, -10);
        }

        return (strlen($digits) === 10) ? $digits : '';
    }

    private function cleanAddress(string $addr): string
    {
        $addr = trim($addr);
        if ($addr === '') return '';

        // normalize weird newlines/extra spaces
        $addr = str_replace(["\r\n", "\n", "\r"], ' ', $addr);
        $addr = preg_replace('/\s+/', ' ', $addr);

        // remove trailing ", 0" or ", ?" junk if present in your sheet
        $addr = preg_replace('/,\s*(0|\?{1,3})\s*$/u', '', $addr);

        return trim($addr);
    }

    private function cleanName(string $name): string
    {
        $name = trim($name);
        if ($name === '') return '';

        // remove obvious mojibake/control chars
        $name = preg_replace('/[^\P{C}]+/u', '', $name);
        $name = preg_replace('/\s+/', ' ', $name);

        return trim($name);
    }
}
