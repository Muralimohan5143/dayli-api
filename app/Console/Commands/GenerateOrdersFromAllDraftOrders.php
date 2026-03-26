<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Events\DraftOrderReadyForOrders;

class GenerateOrdersFromAllDraftOrders extends Command
{
    protected $signature = 'orders:generate-all {--chunk=100}';
    protected $description = 'Generate orders for all existing draft orders';

    public function handle()
    {
        $chunk = (int)$this->option('chunk');

        $this->info("Starting order generation...");

        DB::table('draft_orders')
            ->orderBy('id')
            ->chunk($chunk, function ($rows) {
                foreach ($rows as $row) {
                    event(new DraftOrderReadyForOrders($row->id));

                    $this->line("Dispatched draft_order_id: {$row->id}");
                }
            });

        $this->info("Done dispatching all draft orders.");
    }
}
