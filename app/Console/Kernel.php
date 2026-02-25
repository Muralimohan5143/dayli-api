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
        \App\Console\Commands\OutboxWorkCommand::class,   // ✅ ADD THIS
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

        // ✅ Outbox worker
        $schedule->command('outbox:work --limit=100')
            ->everyMinute()
            ->withoutOverlapping();
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
