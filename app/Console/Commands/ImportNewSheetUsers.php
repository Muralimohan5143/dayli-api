<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

use PhpOffice\PhpSpreadsheet\IOFactory;

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
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheet($sheetIndex);

        // A,B,C.. keys
        $rows = $sheet->toArray(null, true, true, true);

        if (count($rows) < 2) {
            $this->error("XLSX has no data rows.");
            return self::FAILURE;
        }

        for ($i = 0; $i < 15; $i++) {
            array_shift($rows); // skip till row 16
        }
        $headerRow = array_shift($rows);
        $headersByCol = [];
        foreach ($headerRow as $col => $val) {
            $headersByCol[$col] = $this->normKey((string)$val);
        }

        // Required columns
        $colName  = $this->findCol($headersByCol, 'name');
        $colPhone = $this->findCol($headersByCol, 'phone_number');
        $colAddr  = $this->findCol($headersByCol, 'address')
            ?? $this->findCol($headersByCol, 'address_full');

        if (!$colName || !$colPhone || !$colAddr) {
            $this->error("Missing required columns. Found headers: " . implode(', ', array_values($headersByCol)));
            return self::FAILURE;
        }

        $this->info("XLSX columns: name={$colName}, phone={$colPhone}, address={$colAddr}");

        return $this->processUsers(
            $rows,
            fn($r) => (string)($r[$colName] ?? ''),
            fn($r) => (string)($r[$colPhone] ?? ''),
            fn($r) => (string)($r[$colAddr] ?? ''),
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
                    $user = DB::table('users')->where('phone', $phoneNorm)->first();

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
                            'phone' => $phoneNorm,
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

    private function findCol(array $headersByCol, string $wantKey): ?string
    {
        foreach ($headersByCol as $col => $key) {
            if ($key === $wantKey) return $col;
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
