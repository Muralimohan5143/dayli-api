<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->date('delivery_date')
                ->nullable()
                ->after('zone_id');

            $table->enum('delivery_status', ['pending', 'delivered'])
                ->default('pending')
                ->after('delivery_date');

            $table->timestamp('delivered_at')
                ->nullable()
                ->after('delivery_status');

            $table->unsignedBigInteger('delivered_by')
                ->nullable()
                ->after('delivered_at');

            $table->index(['customer_id', 'delivery_date']);
            $table->index('delivery_status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['customer_id', 'delivery_date']);
            $table->dropIndex(['delivery_status']);

            $table->dropColumn([
                'delivery_date',
                'delivery_status',
                'delivered_at',
                'delivered_by',
            ]);
        });
    }
};
