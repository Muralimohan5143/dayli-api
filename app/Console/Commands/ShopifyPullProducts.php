<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ShopifySyncV3;

class ShopifyPullProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Example:
     *   php artisan shopify:pull-products --since="2025-08-01T00:00:00Z" --page=50
     */
    protected $signature = 'shopify:pull-products 
                            {--since= : Optional ISO8601 timestamp (e.g., 2025-08-01T00:00:00Z)} 
                            {--page=100 : Page size for GraphQL pagination}
                            {--dry-run : Do not write to DB}';
    /**
     * The console command description.
     */
    protected $description = 'Pull products + variants from Shopify into local DB (products, variants).';

    public function handle(ShopifySyncV3 $sync): int
    {
        $since   = $this->option('since') ?: null;
        $page    = (int) $this->option('page');
        $dryRun  = $this->option('dry-run');

        $this->info("Pulling products from Shopify...");
        if ($since) {
            $this->info("  Filter: updated_at >= {$since}");
        }
        $this->info("  Page size: {$page}");
        $this->info("  Mode: " . ($dryRun ? 'DRY RUN' : 'LIVE'));

        try {
            $count = $sync->pullProducts($since, $page, $dryRun);
            $this->info(
                $dryRun
                    ? "✅ Dry run complete. {$count} variants would be upserted."
                    : "✅ Sync complete. Upserted {$count} variants."
            );
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
