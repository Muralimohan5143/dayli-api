<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;


class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\SyncDayliShopifyCustomers::class,
        \App\Console\Commands\ImportMilkCsv::class,
        \App\Console\Commands\GenerateDailyOrders::class, // ✅ ADD THIS
        \App\Console\Commands\OpsDispatchDueEvents::class, // ✅ ADD THIS
        \App\Console\Commands\SeedDemoMilkSupplies::class,
        \App\Console\Commands\ImportMergedMilkSheet::class,
        \App\Console\Commands\GenerateOrdersFromAllDraftOrders::class,
        \App\Console\Commands\SeedVendorMilkSupply::class,

    ];
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $hour = config('app.hour');
        $min = config('app.min');
        $scheduledInterval = $hour !== '' ? (($min !== '' && $min != 0) ?  $min . ' */' . $hour . ' * * *' : '0 */' . $hour . ' * * *') : '*/' . $min . ' * * * *';
        if (config('app.is_demo')) {
            $schedule->command('migrate:fresh --seed')->cron($scheduledInterval);
            $schedule->command('image:seed')->cron($scheduledInterval);
        }
        // ✅ Production: auto-create daily pending orders at 5:00 AM
        $schedule->command('dayli:generate-daily-orders')
            ->dailyAt('05:00')
            ->withoutOverlapping();



        $schedule->command('ops:dispatch-due --batch=50 --lock-ttl=10')
            ->everyMinute()
            ->withoutOverlapping(1); // 1 minute overlap lock

        $schedule->command('reports:generate-outbox')->monthlyOn(1, '05:30');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
    // protected $commands = [
    //     \App\Console\Commands\MigrateUserAddresses::class,
    // ];


    // protected $commands = [
    //     \App\Console\Commands\MaterializeOrders::class,
    // ];


}
