<?php

namespace App\Console\Commands;

use App\Services\Imports\HistoricalImporterService;
use Illuminate\Console\Command;
use Throwable;

class PreviewHistoricalMilkImport extends Command
{
    protected $signature = 'milk:historical-preview
        {file : Full path to the Jan-Jun workbook}
        {--show-customers=10 : Number of grouped customers to display}
        {--show-errors=20 : Number of errors to display}';

    protected $description =
    'Preview and validate Jan-Jun 2026 milk historical workbook without writing to the database';

    public function handle(
        HistoricalImporterService $importer
    ): int {
        $file = (string) $this->argument('file');
        $showCustomers = max(
            0,
            (int) $this->option('show-customers')
        );

        $showErrors = max(
            0,
            (int) $this->option('show-errors')
        );

        try {
            $result = $importer->preview($file);
        } catch (Throwable $e) {
            $this->error('PREVIEW FAILED');
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $summary = $result['summary'];
        $customers = $result['customers'];

        $this->newLine();
        $this->info('Historical Milk Workbook Preview');
        $this->line('--------------------------------');

        $this->table(
            ['Metric', 'Value'],
            [
                ['Sheets found', count($summary['sheets_found'])],
                ['Sheets missing', count($summary['sheets_missing'])],
                ['Rows read', $summary['rows_read']],
                ['Rows accepted', $summary['rows_accepted']],
                ['Rows skipped', $summary['rows_skipped']],
                ['Duplicate phone rows', $summary['duplicate_phone_rows']],
                ['Customers grouped', $summary['customers_grouped']],
                ['Users matched', $summary['users_matched']],
                ['Users missing', $summary['users_missing']],
                ['Missing-phone rows', count($summary['missing_phone_rows'])],
                ['Errors', count($summary['errors'])],
            ]
        );

        $this->newLine();
        $this->info('Workbook sheets');

        foreach ($summary['sheets_found'] as $month => $sheet) {
            $this->line("  {$month}: {$sheet}");
        }

        foreach ($summary['sheets_missing'] as $month) {
            $this->warn("  {$month}: MISSING");
        }

        if ($showCustomers > 0) {
            $this->newLine();
            $this->info('Grouped customer sample');

            $rows = [];

            foreach (
                array_slice($customers, 0, $showCustomers, true)
                as $phone => $customer
            ) {
                $rows[] = [
                    $phone,
                    $customer['customer_name'],
                    $customer['user_id'] ?? 'NOT FOUND',
                    implode(', ', array_keys($customer['months'])),
                    count($customer['source_rows']),
                ];
            }

            $this->table(
                [
                    'Phone',
                    'Customer',
                    'User ID',
                    'Months',
                    'Source rows',
                ],
                $rows
            );
        }

        if (!empty($summary['missing_phone_rows'])) {
            $this->newLine();
            $this->warn('Rows with missing/invalid phone');

            $rows = [];

            foreach (
                array_slice(
                    $summary['missing_phone_rows'],
                    0,
                    $showErrors
                )
                as $error
            ) {
                $rows[] = [
                    $error['month'],
                    $error['sheet'],
                    $error['excel_row'],
                    $error['customer_name'],
                    $error['raw_phone'],
                ];
            }

            $this->table(
                [
                    'Month',
                    'Sheet',
                    'Excel row',
                    'Customer',
                    'Raw phone',
                ],
                $rows
            );
        }

        if (!empty($summary['unmatched_users'])) {
            $this->newLine();
            $this->warn('Customers not found in users table');

            $rows = [];

            foreach (
                array_slice(
                    $summary['unmatched_users'],
                    0,
                    $showErrors
                )
                as $user
            ) {
                $rows[] = [
                    $user['phone'],
                    $user['customer_name'],
                ];
            }

            $this->table(
                ['Phone', 'Customer'],
                $rows
            );
        }

        if (!empty($summary['errors'])) {
            $this->newLine();
            $this->error('Sheet errors');

            foreach (
                array_slice($summary['errors'], 0, $showErrors)
                as $error
            ) {
                $this->error(
                    "{$error['month']} / {$error['sheet']}: " .
                        $error['message']
                );
            }
        }

        $hasFailure =
            !empty($summary['sheets_missing']) ||
            !empty($summary['errors']);

        if ($hasFailure) {
            $this->newLine();
            $this->warn(
                'Preview completed with issues. No database records were written.'
            );

            return self::FAILURE;
        }

        $this->newLine();
        $this->info(
            'Phase 1 passed. No database records were written.'
        );

        return self::SUCCESS;
    }
}
