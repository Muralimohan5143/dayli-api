<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {

            // Drop foreign key first
            $table->dropForeign(['order_id']);

            // Drop the order_id column
            $table->dropColumn('order_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {

            $table->unsignedBigInteger('order_id')->nullable()->after('id');

            $table->foreign('order_id')
                ->references('id')
                ->on('orders')
                ->cascadeOnDelete();
        });
    }
};
