<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->after('order_id');
            $table->unsignedBigInteger('variant_id')->nullable()->after('product_id');

            $table->string('product_url')->nullable()->after('brand');
            $table->string('image_url')->nullable()->after('product_url');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['product_id', 'variant_id', 'product_url', 'image_url']);
        });
    }
};
