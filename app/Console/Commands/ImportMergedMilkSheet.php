<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Imports\ImportMergedMilkSheetService;
use Illuminate\Support\Facades\DB;

class ImportMergedMilkSheet extends Command
{
    protected $signature = 'import:merged-milk-sheet 
                            {file : Path to Excel file}
                            {--zone_id=1}
                            {--by_user_id=1}
                            {--subscription_type_id=3}
                            {--vendor_id=}
                            {--dry-run}';

    protected $description = 'Import merged milk sheet into SCR, Draft Orders, and DOIs';

    // php artisan import:merged-milk-sheet "C:\Users\mandl\Downloads\New Microsoft Excel Worksheet.xlsx"

    public function handle(ImportMergedMilkSheetService $service)
    {
        $file = $this->argument('file');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return 1;
        }

        $this->info("Starting import...");
        $this->line("File: {$file}");

        // 👉 define product mapping here or move to config later
        $productMap = [
            'Vijaya-Gold' => [
                'product_id' => 101,
                'variant_id' => 1001,
                'price_snapshot' => 35,
            ],
            'Vijaya-TM' => [
                'product_id' => 102,
                'variant_id' => 1002,
                'price_snapshot' => 28,
            ],
            'Vijaya-TM-Small' => [
                'product_id' => 103,
                'variant_id' => 1003,
                'price_snapshot' => 10,
            ],
            'Arokya-Gold' => [
                'product_id' => 104,
                'variant_id' => 1004,
                'price_snapshot' => 37,
            ],
            // add remaining mappings
        ];

        $context = [
            'zone_id' => (int)$this->option('zone_id'),
            'by_user_id' => (int)$this->option('by_user_id'),
            'subscription_type_id' => (int)$this->option('subscription_type_id'),
            'vendor_id' => $this->option('vendor_id'),
            'product_map' => $productMap,
        ];

        if ($this->option('dry-run')) {
            $this->warn('Running in DRY RUN mode (no DB writes)');
            $this->simulate($service, $file, $context);
            return 0;
        }

        try {
            $result = $service->import($file, $context);

            $this->info("Import completed.");

            $this->table(
                ['Metric', 'Count'],
                [
                    ['Processed', $result['processed']],
                    ['SCR Created', $result['created_scr']],
                    ['Draft Orders', $result['created_do']],
                    ['DOIs Created', $result['created_doi']],
                    ['Skipped', $result['skipped']],
                ]
            );

            if (!empty($result['errors'])) {
                $this->warn("Errors:");
                foreach ($result['errors'] as $err) {
                    $this->line("Row {$err['row']} → {$err['message']}");
                }
            }
        } catch (\Throwable $e) {
            $this->error("Import failed: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    protected function simulate($service, $file, $context)
    {
        // run import but rollback
        DB::beginTransaction();

        $result = $service->import($file, $context);

        DB::rollBack();

        $this->info("Simulation completed (no data saved)");

        $this->table(
            ['Metric', 'Count'],
            [
                ['Processed', $result['processed']],
                ['SCR (simulated)', $result['created_scr']],
                ['DO (simulated)', $result['created_do']],
                ['DOI (simulated)', $result['created_doi']],
                ['Skipped', $result['skipped']],
            ]
        );

        if (!empty($result['errors'])) {
            $this->newLine();
            $this->warn('Row errors:');

            foreach ($result['errors'] as $error) {
                $this->line("Row {$error['row']} => {$error['message']}");
            }
        }
    }
}
