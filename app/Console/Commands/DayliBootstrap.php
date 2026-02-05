<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DayliBootstrap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dayli:bootstrap {--force : Run without confirmation prompts (useful in prod/CI)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fresh migrate, seed DB, pull Shopify products, clear caches, and restart queues';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Bootstrapping Dayli project...');

        // 1) Clear caches to avoid stale config/routes/views
        $this->callSilent('config:clear');
        $this->callSilent('cache:clear');
        $this->callSilent('route:clear');
        $this->callSilent('view:clear');
        $this->info('🧹 Cleared config, cache, routes, and views');

        // 2) Fresh migrate
        $this->call('migrate:fresh', [
            '--force' => $this->option('force'),
        ]);

    
        // 4) Pull Shopify products
        $this->call('shopify:pull-products');

        // 3) Seed DB
        $this->call('db:seed', [
            '--force' => $this->option('force'),
        ]);

        // 5) Restart queue workers
        $this->callSilent('queue:restart');
        $this->info('🔄 Queue workers restarted');

        $this->info('✅ Bootstrap complete!');

        return self::SUCCESS;
    }
}
