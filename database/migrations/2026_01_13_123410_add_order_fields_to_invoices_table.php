<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {

            // NEW COLUMN: order_type
            $table->enum('order_type', [
                'subscription',
                'on_demand',
                'manual',
                'temporary',
                'csv_import',
                'shopify'
            ])->nullable()->after('order_id');

            // NEW COLUMN: order_start_date
            $table->date('order_start_date')->nullable()->after('order_type');

            // NEW COLUMN: order_end_date
            $table->date('order_end_date')->nullable()->after('order_start_date');

            // NEW COLUMN: delivery_fee
            $table->decimal('delivery_fee', 10, 2)
                ->default(0)
                ->after('discount');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'order_type',
                'order_start_date',
                'order_end_date',
                'delivery_fee'
            ]);
        });
    }
};
