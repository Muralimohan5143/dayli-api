<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE draft_order_items 
            MODIFY status ENUM('active','paused','cancelled','void') 
            NOT NULL DEFAULT 'active'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE draft_order_items 
            MODIFY status ENUM('active','paused','cancelled') 
            NOT NULL DEFAULT 'active'
        ");
    }
};
