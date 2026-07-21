<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SeedVendorMilkSupply extends Command
{

    // file for generate vendor milk supply data for Vishnu and Bhaskar into roles, user_services, SCR, draft_orders, and DOI
    protected $signature = 'dayli:seed-vendor-milk-supply
                        {--dry-run : Show what would be inserted/updated without saving}
                        {--from=2026-04-01 : Start date}
                       {--to=2026-05-19 : End date}
                        {--log-file= : Custom log file path inside storage/logs}';

    protected $description = 'Seed vendor milk supply data for Vishnu and Bhaskar into roles, user_services, SCR, draft_orders, and DOI';

    private bool $dryRun = false;
    private string $startDate;
    private string $endDate;
    private string $today;
    private string $logChannel = 'daily';

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $this->startDate = (string) $this->option('from');
        $this->endDate = (string) $this->option('to');
        $this->today = Carbon::today()->toDateString();

        $customLogFile = $this->option('log-file');
        if ($customLogFile) {
            config([
                'logging.channels.vendor_seed_custom' => [
                    'driver' => 'single',
                    'path' => storage_path('logs/' . ltrim($customLogFile, '/\\')),
                    'level' => 'debug',
                ],
            ]);
            $this->logChannel = 'vendor_seed_custom';
        }

        $this->log('START vendor seed', [
            'dry_run' => $this->dryRun,
            'from' => $this->startDate,
            'to' => $this->endDate,
            'today' => $this->today,
        ]);

        $vendorRoleId = 5;
        $vendorMilkRoleId = 6;
        $subscriptionTypeId = 3;
        $zoneId = 1;

        $vendors = [
            [
                'name' => 'Bhasker sir',

                'user_lookup_names' => [
                    'Bhasker sir',
                    'Bhaskar sir',
                    'Bhasker',
                    'Bhaskar',
                ],

                'service_handle' => 'milk-supplier',
                'title' => 'Bhaskar Milk Supply',
                'daily_items' => [
                    '2026-04-01' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 5.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 1.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-04-02' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 5.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 1.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-04-03' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 2.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 0.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-04-04' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 2.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 0.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-04-05' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 1.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-04-06' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 0.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-04-07' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 1.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-04-08' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 0.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-04-09' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 1.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-04-10' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 0.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-04-11' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 1.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-04-12' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 0.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-04-13' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 1.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-04-14' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 0.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-04-15' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 1.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-04-16' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 0.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-04-17' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 1.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-04-18' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 0.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-04-19' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 1.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-04-20' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 0.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-04-21' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 1.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-04-22' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 0.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-04-23' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 1.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-04-24' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 0.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-04-25' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 1.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-04-26' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 0.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-04-27' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 1.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-04-28' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 0.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-04-29' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 1.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-04-30' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 0.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-05-01' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 1.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-05-02' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 1.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-05-03' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 0.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-05-04' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 1.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-05-05' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 1.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-05-06' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 1.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-05-07' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 1.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-05-08' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 1.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-05-09' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 1.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-05-10' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 1.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-05-11' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 1.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-05-12' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 1.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-05-13' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 5.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 1.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-05-14' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 5.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 1.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-05-15' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 5.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 1.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-05-16' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 5.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 1.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-05-17' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 5.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 1.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-05-18' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 5.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 1.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-05-19' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 5.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 1.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-05-20' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 5.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 1.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                ],
            ],
            [
                'name' => 'Vishnu Vardhan Reddy',

                'user_lookup_names' => [
                    'Vishnu Vardhan Reddy',
                    'Vishnu Vardhan',
                    'Vishnu',
                ],

                'service_handle' => 'milk-supplier',
                'title' => 'Vishnu Milk Supply',
                'daily_items' => [
                    '2026-04-01' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 31.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 6.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 2.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 0.00, 'price' => 10.00],
                    ],
                    '2026-04-02' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 34.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 6.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 2.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 0.00, 'price' => 10.00],
                    ],
                    '2026-04-03' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 35.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 8.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 0.00, 'price' => 10.00],
                    ],
                    '2026-04-04' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 34.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 9.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 0.00, 'price' => 10.00],
                    ],
                    '2026-04-05' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 32.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 9.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 0.00, 'price' => 10.00],
                    ],
                    '2026-04-06' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 36.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 9.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 0.00, 'price' => 10.00],
                    ],
                    '2026-04-07' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 30.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 9.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 0.00, 'price' => 10.00],
                    ],
                    '2026-04-08' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 36.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 9.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 0.00, 'price' => 10.00],
                    ],
                    '2026-04-09' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 34.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 8.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 0.00, 'price' => 10.00],
                    ],
                    '2026-04-10' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 35.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 9.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 0.00, 'price' => 10.00],
                    ],
                    '2026-04-11' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 35.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 8.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 3.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 0.00, 'price' => 10.00],
                    ],
                    '2026-04-12' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 37.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 9.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 2.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 0.00, 'price' => 10.00],
                    ],
                    '2026-04-13' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 33.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 7.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 0.00, 'price' => 10.00],
                    ],
                    '2026-04-14' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 39.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 8.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 2.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 0.00, 'price' => 10.00],
                    ],
                    '2026-04-15' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 33.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 9.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 2.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 0.00, 'price' => 10.00],
                    ],
                    '2026-04-16' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 36.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 8.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 2.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 0.00, 'price' => 10.00],
                    ],
                    '2026-04-17' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 35.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 8.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 2.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 0.00, 'price' => 10.00],
                    ],
                    '2026-04-18' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 37.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 8.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 2.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 0.00, 'price' => 10.00],
                    ],
                    '2026-04-19' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 34.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 8.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 0.00, 'price' => 10.00],
                    ],
                    '2026-04-20' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 34.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 8.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 0.00, 'price' => 10.00],
                    ],
                    '2026-04-21' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 29.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 8.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 0.00, 'price' => 10.00],
                    ],
                    '2026-04-22' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 29.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 8.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 2.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 4.00, 'price' => 10.00],
                    ],
                    '2026-04-23' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 35.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 8.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 4.00, 'price' => 10.00],
                    ],
                    '2026-04-24' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 30.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 9.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 4.00, 'price' => 10.00],
                    ],
                    '2026-04-25' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 32.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 7.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 4.00, 'price' => 10.00],
                    ],
                    '2026-04-26' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 34.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 7.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 2.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 4.00, 'price' => 10.00],
                    ],
                    '2026-04-27' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 34.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 8.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 4.00, 'price' => 10.00],
                    ],
                    '2026-04-28' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 32.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 6.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 4.00, 'price' => 10.00],
                    ],
                    '2026-04-29' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 35.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 6.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 4.00, 'price' => 10.00],
                    ],
                    '2026-04-30' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 30.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 9.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 4.00, 'price' => 10.00],
                    ],
                    '2026-05-01' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 32.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 8.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 4.00, 'price' => 10.00],
                    ],
                    '2026-05-02' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 36.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 9.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 4.00, 'price' => 10.00],
                    ],
                    '2026-05-03' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 36.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 10.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 4.00, 'price' => 10.00],
                    ],
                    '2026-05-04' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 36.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 11.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 4.00, 'price' => 10.00],
                    ],
                    '2026-05-05' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 37.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 11.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 4.00, 'price' => 10.00],
                    ],
                    '2026-05-06' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 36.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 11.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 4.00, 'price' => 10.00],
                    ],
                    '2026-05-07' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 34.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 11.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 4.00, 'price' => 10.00],
                    ],
                    '2026-05-08' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 33.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 12.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 4.00, 'price' => 10.00],
                    ],
                    '2026-05-09' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 32.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 13.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 4.00, 'price' => 10.00],
                    ],
                    '2026-05-10' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 38.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 12.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 4.00, 'price' => 10.00],
                    ],
                    '2026-05-11' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 40.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 10.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 0.00, 'price' => 10.00],
                    ],
                    '2026-05-12' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 37.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 11.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 0.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 0.00, 'price' => 10.00],
                    ],
                    '2026-05-13' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 31.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 9.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 0.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 0.00, 'price' => 10.00],
                    ],
                    '2026-05-14' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 35.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 10.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 4.00, 'price' => 10.00],
                    ],
                    '2026-05-15' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 32.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 12.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 4.00, 'price' => 10.00],
                    ],
                    '2026-05-16' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 40.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 9.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 4.00, 'price' => 10.00],
                    ],
                    '2026-05-17' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 37.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 10.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 4.00, 'price' => 10.00],
                    ],
                    '2026-05-18' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 40.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 11.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 4.00, 'price' => 10.00],
                    ],
                    '2026-05-19' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 37.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 11.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 2.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 4.00, 'price' => 10.00],
                    ],
                    '2026-05-20' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 40.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 11.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                        ['product_id' => 10339468935442, 'variant_id' => 52217769394450, 'label' => 'Vijaya Butter Milk', 'qty' => 4.00, 'price' => 10.00],
                    ],
                ],
            ],
        ];

        foreach ($vendors as $vendor) {
            $this->line('');

            $user = $this->resolveVendorUser(
                vendorName: $vendor['name'],
                lookupNames: $vendor['user_lookup_names']
            );

            $vendorUserId = (int) $user->id;

            $this->info(
                "Processing vendor: {$vendor['name']} " .
                    "(resolved user_id={$vendorUserId})"
            );

            $this->log('vendor user resolved', [
                'vendor_name' => $vendor['name'],
                'user_id' => $vendorUserId,
                'database_name' => $user->name ?? null,
                'database_display_name' => $user->display_name ?? null,
                'phone' => $user->phone ?? null,
            ]);

            DB::beginTransaction();

            try {
                $this->seedRoles(
                    $vendorUserId,
                    $vendorRoleId,
                    $vendorMilkRoleId
                );

                $this->seedUserService(
                    userId: $vendorUserId,
                    serviceHandle: $vendor['service_handle'],
                    subscriptionTypeId: $subscriptionTypeId,
                    zoneId: $zoneId
                );

                $scrId = $this->seedScr(
                    userId: $vendorUserId,
                    subscriptionTypeId: $subscriptionTypeId,
                    zoneId: $zoneId
                );

                $draftOrderId = $this->seedDraftOrder(
                    scrId: $scrId,
                    vendorId: $vendorUserId,
                    zoneId: $zoneId,
                    title: $vendor['title']
                );

                $this->linkScrToDraftOrder(
                    $scrId,
                    $draftOrderId
                );

                $this->seedMergedDoiRanges(
                    draftOrderId: $draftOrderId,
                    vendorId: $vendorUserId,
                    dailyItems: $vendor['daily_items']
                );

                if ($this->dryRun) {
                    DB::rollBack();
                    $this->warn("DRY RUN: rolled back {$vendor['name']}");
                    $this->log('DRY RUN rollback', [
                        'vendor_id' => $vendorUserId,
                    ]);
                } else {
                    DB::commit();
                    $this->info("Committed: {$vendor['name']}");
                    $this->log('Committed vendor seed', [
                        'vendor_id' => $vendorUserId,
                    ]);
                }
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error("Failed for {$vendor['name']}: " . $e->getMessage());
                $this->log('FAILED vendor seed', [
                    'vendor_id' => $vendorUserId,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        $this->line('');
        $this->info($this->dryRun ? 'Dry run completed.' : 'Vendor seed completed.');
        $this->log('END vendor seed', ['dry_run' => $this->dryRun]);

        return self::SUCCESS;
    }

    private function resolveVendorUser(
        string $vendorName,
        array $lookupNames
    ): object {
        $lookupNames = array_values(
            array_unique(
                array_filter(
                    array_map(
                        fn($name) => trim((string) $name),
                        $lookupNames
                    )
                )
            )
        );

        if (empty($lookupNames)) {
            throw new \RuntimeException(
                "No user lookup names configured for {$vendorName}."
            );
        }

        /*
     * First attempt:
     * Exact match against name/display_name.
     */
        $exactMatches = DB::table('users')
            ->where(function ($query) use ($lookupNames) {
                foreach ($lookupNames as $lookupName) {
                    $query
                        ->orWhereRaw(
                            'LOWER(TRIM(name)) = ?',
                            [mb_strtolower($lookupName)]
                        )
                        ->orWhereRaw(
                            'LOWER(TRIM(display_name)) = ?',
                            [mb_strtolower($lookupName)]
                        );
                }
            })
            ->select([
                'id',
                'name',
                'display_name',
                'phone',
            ])
            ->get();

        if ($exactMatches->count() === 1) {
            return $exactMatches->first();
        }

        if ($exactMatches->count() > 1) {
            $matchedUsers = $exactMatches
                ->map(
                    fn($user) =>
                    "id={$user->id}, " .
                        "name={$user->name}, " .
                        "display_name={$user->display_name}"
                )
                ->implode(' | ');

            throw new \RuntimeException(
                "Multiple exact users found for {$vendorName}: " .
                    $matchedUsers
            );
        }

        /*
     * Second attempt:
     * Partial matching for minor spelling differences.
     */
        $partialMatches = DB::table('users')
            ->where(function ($query) use ($lookupNames) {
                foreach ($lookupNames as $lookupName) {
                    $query
                        ->orWhere('name', 'like', '%' . $lookupName . '%')
                        ->orWhere(
                            'display_name',
                            'like',
                            '%' . $lookupName . '%'
                        );
                }
            })
            ->select([
                'id',
                'name',
                'display_name',
                'phone',
            ])
            ->get();

        if ($partialMatches->count() === 1) {
            return $partialMatches->first();
        }

        if ($partialMatches->count() > 1) {
            $matchedUsers = $partialMatches
                ->map(
                    fn($user) =>
                    "id={$user->id}, " .
                        "name={$user->name}, " .
                        "display_name={$user->display_name}"
                )
                ->implode(' | ');

            throw new \RuntimeException(
                "Multiple possible users found for {$vendorName}: " .
                    $matchedUsers
            );
        }

        throw new \RuntimeException(
            "Vendor user not found in users table: {$vendorName}. " .
                'Checked names: ' .
                implode(', ', $lookupNames)
        );
    }

    private function seedRoles(int $userId, int $vendorRoleId, int $vendorMilkRoleId): void
    {
        foreach ([$vendorRoleId, $vendorMilkRoleId] as $roleId) {
            $exists = DB::table('model_has_roles')
                ->where('role_id', $roleId)
                ->where('model_type', 'App\\Models\\User')
                ->where('model_id', $userId)
                ->exists();

            if ($exists) {
                $this->line("  role exists: role_id={$roleId}");
                $this->log('role exists', compact('userId', 'roleId'));
                continue;
            }

            $this->line("  role insert: role_id={$roleId}");
            $this->log('role insert', compact('userId', 'roleId'));

            if (! $this->dryRun) {
                DB::table('model_has_roles')->insert([
                    'role_id' => $roleId,
                    'model_type' => 'App\\Models\\User',
                    'model_id' => $userId,
                ]);
            }
        }
    }

    private function seedUserService(
        int $userId,
        string $serviceHandle,
        int $subscriptionTypeId,
        int $zoneId
    ): void {
        $existing = DB::table('user_services')
            ->where('user_id', $userId)
            ->where('role_name', 'vendor')
            ->where('service_handle', $serviceHandle)
            ->where('subscription_type_id', $subscriptionTypeId)
            ->where('zone_id', $zoneId)
            ->first();

        $payload = [
            'status' => 'approved',
            'is_active' => 1,
            'approved_by' => null,
            'approved_at' => now(),
            'rejection_reason' => null,
            'meta' => json_encode([
                'seeded' => true,
                'seed_type' => 'vendor_supply',
            ]),
            'updated_at' => now(),
        ];

        if ($existing) {
            $row = [
                'id' => $existing->id,
                'user_id' => $userId,
                'role_name' => 'vendor',
                'service_handle' => $serviceHandle,
                'subscription_type_id' => $subscriptionTypeId,
                'zone_id' => $zoneId,
                ...$payload,
            ];

            $this->line("  user_service update: id={$existing->id}");
            $this->log('user_service update full', $row);

            if (! $this->dryRun) {
                DB::table('user_services')->where('id', $existing->id)->update($payload);
            }
            return;
        }

        $row = [
            'user_id' => $userId,
            'role_name' => 'vendor',
            'service_handle' => $serviceHandle,
            'subscription_type_id' => $subscriptionTypeId,
            'zone_id' => $zoneId,
            ...$payload,
            'created_at' => now(),
        ];

        $this->line("  user_service insert: user_id={$userId}");
        $this->log('user_service insert full', $row);

        if (! $this->dryRun) {
            DB::table('user_services')->insert($row);
        }
    }

    private function seedScr(int $userId, int $subscriptionTypeId, int $zoneId): int
    {
        $existing = DB::table('sub_change_requests')
            ->where('for_user_id', $userId)
            ->where('by_user_id', $userId)
            ->where('party_type', 'supplier')
            ->where('subscription_type_id', $subscriptionTypeId)
            ->where('zone_id', $zoneId)
            ->where('action', 'create')
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            $this->line("  SCR exists/update: id={$existing->id}");
            $this->log('scr update', ['id' => $existing->id, 'user_id' => $userId]);

            $updateRow = [
                'status' => 'approved',
                'approved_at' => now(),
                'invoice_cycle' => 'monthly',
                'meta' => json_encode([
                    'seeded' => true,
                    'seed_type' => 'vendor_supply',
                    'start_date' => $this->startDate,
                    'seeded_till' => $this->today,
                ]),
                'updated_at' => now(),
            ];

            $this->log('scr update full', [
                'id' => $existing->id,
                ...$updateRow,
            ]);

            if (! $this->dryRun) {
                DB::table('sub_change_requests')
                    ->where('id', $existing->id)
                    ->update($updateRow);
            }
            return (int) $existing->id;
        }

        $this->line("  SCR insert: user_id={$userId}");
        $this->log('scr insert', ['user_id' => $userId]);

        $row = [
            'for_user_id' => $userId,
            'by_user_id' => $userId,
            'party_type' => 'supplier',
            'from_id' => null,
            'draft_order_id' => null,
            'zone_id' => $zoneId,
            'subscription_type_id' => $subscriptionTypeId,
            'subtypes_json' => null,
            'custom_frequency_format' => null,
            'invoice_cycle' => 'monthly',
            'change_reason' => 'self_service',
            'action' => 'create',
            'status' => 'approved',
            'approved_by' => null,
            'approved_at' => now(),
            'priority' => 3,
            'payload' => json_encode([
                'seeded_for' => 'vendor_supply',
                'start_date' => $this->startDate,
                'seeded_till' => $this->today,
            ]),
            'meta' => json_encode([
                'seeded' => true,
                'seed_type' => 'vendor_supply',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $this->log('scr insert full', $row);

        if ($this->dryRun) {
            return -$userId;
        }

        return (int) DB::table('sub_change_requests')->insertGetId($row);
    }

    private function seedDraftOrder(int $scrId, int $vendorId, int $zoneId, string $title): int
    {
        $existing = DB::table('draft_orders')
            ->where('change_request_id', $scrId)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            $this->line("  draft_order update: id={$existing->id}");

            $updateRow = [
                'vendor_id' => $vendorId,
                'zone_id' => $zoneId,
                'cadence' => 'daily',
                'invoice_cycle' => 'monthly',
                'start_date' => $this->startDate,
                'end_date' => $this->endDate,
                'timezone' => 'Asia/Kolkata',
                'title' => $title,
                'meta' => json_encode([
                    'seeded' => true,
                    'party_type' => 'supplier',
                ]),
                'updated_at' => now(),
            ];

            $this->log('draft_order update full', [
                'id' => $existing->id,
                ...$updateRow,
            ]);

            if (! $this->dryRun) {
                DB::table('draft_orders')
                    ->where('id', $existing->id)
                    ->update($updateRow);
            }

            return (int) $existing->id;
        }

        $this->line("  draft_order insert: vendor_id={$vendorId}");

        $row = [
            'change_request_id' => $scrId,
            'customer_id' => null,
            'vendor_id' => $vendorId,
            'zone_id' => $zoneId,
            'cadence' => 'daily',
            'custom_frequency_format' => null,
            'invoice_cycle' => 'monthly',
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'status' => 'active',
            'locked_at' => null,
            'timezone' => 'Asia/Kolkata',
            'title' => $title,
            'pricing_policy' => null,
            'tax_policy' => null,
            'meta' => json_encode([
                'seeded' => true,
                'party_type' => 'supplier',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $this->log('draft_order insert full', $row);

        if ($this->dryRun) {
            return - ($vendorId + 100000);
        }

        return (int) DB::table('draft_orders')->insertGetId($row);
    }
    private function linkScrToDraftOrder(int $scrId, int $draftOrderId): void
    {
        $this->line("  SCR link draft_order_id={$draftOrderId}");
        $this->log('scr link draft order', compact('scrId', 'draftOrderId'));

        if (! $this->dryRun && $scrId > 0 && $draftOrderId > 0) {
            DB::table('sub_change_requests')
                ->where('id', $scrId)
                ->update([
                    'draft_order_id' => $draftOrderId,
                    'updated_at' => now(),
                ]);
        }
    }

    private function seedDoi(
        int $draftOrderId,
        int $vendorId,
        int $productId,
        int $variantId,
        float $qty,
        float $price,
        string $label
    ): void {
        $existing = DB::table('draft_order_items')
            ->where('draft_order_id', $draftOrderId)
            ->where('variant_id', $variantId)
            ->where('vendor_id', $vendorId)
            ->where('start_date', $this->startDate)
            ->first();

        $payload = [
            'original_item_id' => null,
            'change_action' => 'create',
            'draft_order_id' => $draftOrderId,
            'product_id' => $productId,
            'variant_id' => $variantId,
            'vendor_id' => $vendorId,
            'frequency_type' => $qty > 0 ? 'daily' : null,
            'qty' => $qty,
            'unit' => 'pcs',
            'price_snapshot' => $price,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'status' => $qty > 0 ? 'active' : 'paused',
            'supersedes_doi_id' => null,
            'created_from_action' => 'vendor_seed',
            'closed_by_action' => null,
            'meta' => json_encode([
                'seeded_for' => 'vendor_supply',
                'label' => $label,
                'is_zero_qty_seed' => $qty == 0,
            ]),
            'updated_at' => now(),
        ];

        $this->log('doi payload full', $payload);

        if ($existing) {
            $this->line("  DOI update: variant_id={$variantId}, qty={$qty}");
            $this->log('doi update full', [
                'id' => $existing->id,
                ...$payload,
            ]);

            if (! $this->dryRun) {
                DB::table('draft_order_items')->where('id', $existing->id)->update($payload);
            }
            return;
        }

        $this->line("  DOI insert: variant_id={$variantId}, qty={$qty}");
        $this->log('doi insert full', $payload);

        if (! $this->dryRun) {
            DB::table('draft_order_items')->insert([
                ...$payload,
                'created_at' => now(),
            ]);
        }
    }

    private function seedDoiRange(
        int $draftOrderId,
        int $vendorId,
        int $productId,
        int $variantId,
        float $qty,
        float $price,
        string $label,
        string $startDate,
        ?string $endDate,
        string $status
    ): void {
        $existing = DB::table('draft_order_items')
            ->where('draft_order_id', $draftOrderId)
            ->where('variant_id', $variantId)
            ->where('vendor_id', $vendorId)
            ->whereDate('start_date', $startDate)
            ->where(function ($q) use ($endDate) {
                if ($endDate === null) {
                    $q->whereNull('end_date');
                } else {
                    $q->whereDate('end_date', $endDate);
                }
            })
            ->first();

        $payload = [
            'original_item_id' => null,
            'change_action' => 'create',
            'draft_order_id' => $draftOrderId,
            'product_id' => $productId,
            'variant_id' => $variantId,
            'vendor_id' => $vendorId,
            'frequency_type' => $status === 'active' ? 'daily' : null,
            'qty' => $qty,
            'unit' => 'pcs',
            'price_snapshot' => $price,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => $status,
            'supersedes_doi_id' => null,
            'created_from_action' => 'vendor_seed',
            'closed_by_action' => null,
            'meta' => json_encode([
                'seeded_for' => 'vendor_supply',
                'label' => $label,
                'is_zero_qty_seed' => $qty == 0,
            ]),
            'updated_at' => now(),
        ];

        if ($existing) {
            if (! $this->dryRun) {
                DB::table('draft_order_items')->where('id', $existing->id)->update($payload);
            }
            return;
        }

        if (! $this->dryRun) {
            DB::table('draft_order_items')->insert([
                ...$payload,
                'created_at' => now(),
            ]);
        }
    }

    private function resolveProductVariant(
        string $label,
        int $fallbackProductId,
        int $fallbackVariantId
    ): array {
        /*
     * First preference:
     * Existing hardcoded IDs, only when both actually exist.
     */
        $existingVariant = DB::table('variants')
            ->where('variant_id', $fallbackVariantId)
            ->where('product_id', $fallbackProductId)
            ->first();

        if ($existingVariant) {
            return [
                'product_id' => (int) $existingVariant->product_id,
                'variant_id' => (int) $existingVariant->variant_id,
            ];
        }

        /*
     * Second preference:
     * Match by normalized label/title.
     */
        $normalizedLabel = mb_strtolower(trim($label));

        $matches = DB::table('variants as v')
            ->join(
                'products as p',
                'p.product_id',
                '=',
                'v.product_id'
            )
            ->where(function ($query) use ($normalizedLabel) {
                $query
                    ->whereRaw(
                        'LOWER(TRIM(v.title)) = ?',
                        [$normalizedLabel]
                    )
                    ->orWhereRaw(
                        'LOWER(TRIM(p.title)) = ?',
                        [$normalizedLabel]
                    )
                    ->orWhereRaw(
                        'LOWER(CONCAT(TRIM(p.title), " ", TRIM(v.title))) = ?',
                        [$normalizedLabel]
                    );
            })
            ->select([
                'p.product_id',
                'p.title as product_title',
                'v.variant_id',
                'v.title as variant_title',
            ])
            ->get();

        if ($matches->count() === 1) {
            $match = $matches->first();

            $this->line(
                "  resolved product: {$label} " .
                    "=> product_id={$match->product_id}, " .
                    "variant_id={$match->variant_id}"
            );

            return [
                'product_id' => (int) $match->product_id,
                'variant_id' => (int) $match->variant_id,
            ];
        }

        /*
     * Third preference:
     * Looser partial label lookup.
     */
        $searchWords = collect(
            preg_split('/[^a-zA-Z0-9]+/', $label)
        )
            ->map(fn($word) => trim($word))
            ->filter(fn($word) => mb_strlen($word) >= 3)
            ->values();

        $partialMatches = DB::table('variants as v')
            ->join(
                'products as p',
                'p.product_id',
                '=',
                'v.product_id'
            )
            ->where(function ($query) use ($searchWords) {
                foreach ($searchWords as $word) {
                    $query->orWhere(function ($subQuery) use ($word) {
                        $subQuery
                            ->where('p.title', 'like', '%' . $word . '%')
                            ->orWhere('v.title', 'like', '%' . $word . '%');
                    });
                }
            })
            ->select([
                'p.product_id',
                'p.title as product_title',
                'v.variant_id',
                'v.title as variant_title',
            ])
            ->distinct()
            ->get();

        if ($partialMatches->count() === 1) {
            $match = $partialMatches->first();

            $this->line(
                "  resolved product: {$label} " .
                    "=> product_id={$match->product_id}, " .
                    "variant_id={$match->variant_id}"
            );

            return [
                'product_id' => (int) $match->product_id,
                'variant_id' => (int) $match->variant_id,
            ];
        }

        if ($partialMatches->count() > 1) {
            $possibleMatches = $partialMatches
                ->map(
                    fn($row) =>
                    "product_id={$row->product_id}, " .
                        "product={$row->product_title}, " .
                        "variant_id={$row->variant_id}, " .
                        "variant={$row->variant_title}"
                )
                ->implode(' | ');

            throw new \RuntimeException(
                "Multiple product/variant matches found for '{$label}': " .
                    $possibleMatches
            );
        }

        throw new \RuntimeException(
            "Product/variant not found for '{$label}'. " .
                "Old product_id={$fallbackProductId}, " .
                "old variant_id={$fallbackVariantId}"
        );
    }

    private function seedMergedDoiRanges(
        int $draftOrderId,
        int $vendorId,
        array $dailyItems
    ): void {
        $byVariant = [];
        foreach ($dailyItems as $date => $items) {
            foreach ($items as $item) {
                $resolvedItem = $this->resolveProductVariant(
                    label: (string) $item['label'],
                    fallbackProductId: (int) $item['product_id'],
                    fallbackVariantId: (int) $item['variant_id'],
                );

                $variantKey = (string) $resolvedItem['variant_id'];

                $byVariant[$variantKey][] = [
                    'date' => $date,
                    'product_id' => $resolvedItem['product_id'],
                    'variant_id' => $resolvedItem['variant_id'],
                    'qty' => (float) $item['qty'],
                    'price' => (float) $item['price'],
                    'label' => (string) $item['label'],
                    'status' => ((float) $item['qty'] > 0)
                        ? 'active'
                        : 'paused',
                ];
            }
        }
        foreach ($byVariant as $variantRows) {
            usort($variantRows, fn($a, $b) => strcmp($a['date'], $b['date']));

            $current = null;

            foreach ($variantRows as $row) {
                if ($current === null) {
                    $current = [
                        'start_date' => $row['date'],
                        'end_date' => $row['date'],
                        'product_id' => $row['product_id'],
                        'variant_id' => $row['variant_id'],
                        'qty' => $row['qty'],
                        'price' => $row['price'],
                        'label' => $row['label'],
                        'status' => $row['status'],
                    ];
                    continue;
                }

                $prevEnd = \Carbon\Carbon::parse($current['end_date']);
                $thisDate = \Carbon\Carbon::parse($row['date']);

                $isContinuous = $prevEnd->copy()->addDay()->toDateString() === $thisDate->toDateString();
                $sameQty = (float) $current['qty'] === (float) $row['qty'];
                $samePrice = (float) $current['price'] === (float) $row['price'];
                $sameStatus = $current['status'] === $row['status'];

                if ($isContinuous && $sameQty && $samePrice && $sameStatus) {
                    $current['end_date'] = $row['date'];
                } else {
                    $this->seedDoiRange(
                        draftOrderId: $draftOrderId,
                        vendorId: $vendorId,
                        productId: $current['product_id'],
                        variantId: $current['variant_id'],
                        qty: $current['qty'],
                        price: $current['price'],
                        label: $current['label'],
                        startDate: $current['start_date'],
                        endDate: $current['end_date'],
                        status: $current['status']
                    );

                    $current = [
                        'start_date' => $row['date'],
                        'end_date' => $row['date'],
                        'product_id' => $row['product_id'],
                        'variant_id' => $row['variant_id'],
                        'qty' => $row['qty'],
                        'price' => $row['price'],
                        'label' => $row['label'],
                        'status' => $row['status'],
                    ];
                }
            }

            if ($current !== null) {
                $finalEndDate = $current['status'] === 'active' ? null : $current['end_date'];

                $this->seedDoiRange(
                    draftOrderId: $draftOrderId,
                    vendorId: $vendorId,
                    productId: $current['product_id'],
                    variantId: $current['variant_id'],
                    qty: $current['qty'],
                    price: $current['price'],
                    label: $current['label'],
                    startDate: $current['start_date'],
                    endDate: $finalEndDate,
                    status: $current['status']
                );
            }
        }
    }
    private function log(string $message, array $context = []): void
    {
        Log::channel($this->logChannel)->info('[dayli:seed-vendor-milk-supply] ' . $message, $context);
    }
}
