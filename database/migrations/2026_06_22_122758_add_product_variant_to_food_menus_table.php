<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_menus', function (Blueprint $table) {
            if (!Schema::hasColumn('food_menus', 'product_id')) {
                $table->unsignedBigInteger('product_id')->nullable()->after('meal_type');
            }

            if (!Schema::hasColumn('food_menus', 'variant_id')) {
                $table->unsignedBigInteger('variant_id')->nullable()->after('product_id');
            }

            $table->index(['product_id', 'variant_id'], 'food_menus_product_variant_index');
        });
    }

    public function down(): void
    {
        Schema::table('food_menus', function (Blueprint $table) {
            $table->dropIndex('food_menus_product_variant_index');

            if (Schema::hasColumn('food_menus', 'variant_id')) {
                $table->dropColumn('variant_id');
            }

            if (Schema::hasColumn('food_menus', 'product_id')) {
                $table->dropColumn('product_id');
            }
        });
    }
};
