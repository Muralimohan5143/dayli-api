<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('order_type', [
                'subscription',
                'on_demand',
                'manual',
                'temporary',
                'csv_import',
                'shopify',
            ])
            ->default('subscription')
            ->after('shopify_id'); // ✅ EXACT POSITION
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('order_type');
        });
    }
};
