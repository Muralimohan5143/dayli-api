<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('draft_order_items', function (Blueprint $table) {
            $table->enum('status', ['active', 'paused', 'cancelled'])
                ->default('active')
                ->after('end_date');
        });
    }

    public function down(): void
    {
        Schema::table('draft_order_items', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
