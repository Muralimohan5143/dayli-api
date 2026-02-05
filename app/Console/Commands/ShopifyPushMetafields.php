<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ShopifyMetafieldService;

class ShopifyPushMetafields extends Command
{
    protected $signature = 'shopify:push-metafields {file}';
    protected $description = 'Push product type/subtype metafields to Shopify from Excel';

    public function handle(ShopifyMetafieldService $service)
    {
        $file = $this->argument('file');

        if (!file_exists($file)) {
            $this->error("File not found: $file");
            return Command::FAILURE;
        }

        $this->info("Processing: $file");

        $results = $service->pushFromExcel($file);

        $ok = collect($results)->where('status', 'ok')->count();
        $notFound = collect($results)->where('status', 'not_found')->count();
        $errors = collect($results)->whereIn('status', ['error', 'user_errors'])->count();

        $this->table(
            ['Handle', 'Type', 'Subtype', 'Status', 'Detail'],
            array_map(fn($r) => [
                $r['handle'], $r['type'], $r['subtype'], $r['status'], $r['detail']
            ], $results)
        );

        $this->info("Done. OK: $ok, Not Found: $notFound, Errors: $errors");

        return Command::SUCCESS;
    }
}
