<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class Reseed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'image:seed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reseeds the image folders';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        File::copy(storage_path('app/public/avatars-seed/team-1.jpg'), storage_path('app/public/avatars/team-1.jpg'));
        File::copy(storage_path('app/public/avatars-seed/team-2.jpg'), storage_path('app/public/avatars/team-2.jpg'));
        File::copy(storage_path('app/public/avatars-seed/team-3.jpg'), storage_path('app/public/avatars/team-3.jpg'));
        File::copy(storage_path('app/public/items-seed/home-decor-1.jpg'), storage_path('app/public/items/home-decor-1.jpg'));
        File::copy(storage_path('app/public/items-seed/home-decor-2.jpg'), storage_path('app/public/items/home-decor-2.jpg'));
        File::copy(storage_path('app/public/items-seed/jordan.jpg'), storage_path('app/public/items/jordan.jpg'));
    }
}
