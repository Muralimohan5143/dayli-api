<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_fee_rules', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_id')->nullable()->after('variant_id');
            $table->string('title')->nullable()->after('customer_id');

            $table->index('customer_id');
            $table->index(['customer_id', 'product_id', 'variant_id']);
        });
    }

    public function down(): void
    {
        Schema::table('delivery_fee_rules', function (Blueprint $table) {
            $table->dropIndex(['customer_id']);
            $table->dropIndex(['customer_id', 'product_id', 'variant_id']);

            $table->dropColumn(['customer_id', 'title']);
        });
    }
};
