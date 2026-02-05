<?php

namespace App\Console\Commands;

use App\Services\ShopifySync;
use Illuminate\Console\Command;

class ShopifyPull extends Command
{
    protected $signature = 'shopify:pull {--since=} {--page=100}';
    protected $description = 'Pull products + variants (and prices) from Shopify into local DB';

    public function handle(ShopifySync $sync): int
    {
        $since = $this->option('since'); // e.g., 2025-08-01
        $page  = (int) $this->option('page');

        $this->info("Syncing from Shopify (since=" . ($since ?: 'ALL') . ", page={$page})...");
        $count = $sync->pullProducts($since, $page);
        $this->info("Done. Upserted/checked {$count} variants.");

        return self::SUCCESS;
    }
}
