<?php

namespace App\Console\Commands;

use App\Services\OrderMaterializer;
use Carbon\Carbon;
use Illuminate\Console\Command;

class MaterializeOrders extends Command
{
    protected $signature = 'orders:materialize {date? : YYYY-MM-DD; defaults to today}';
    protected $description = 'Materialize daily subscription orders from draft templates';

    public function handle(OrderMaterializer $materializer): int
    {
        $date = $this->argument('date') ?: now()->toDateString();
        $this->info("Materializing orders for {$date}...");
        $materializer->materializeForDate(Carbon::parse($date));
        $this->info('Done.');
        return self::SUCCESS;
    }
}
