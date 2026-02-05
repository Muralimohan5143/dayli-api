<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('variants', function (Blueprint $table) {
            // PK matches dump: bigint unsigned, not auto-increment
            $table->unsignedBigInteger('variant_id')->primary();

            // FK to products.product_id (also unsigned, not AI)
            $table->unsignedBigInteger('product_id');

            $table->string('title')->nullable();
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();

            $table->string('option1')->nullable();
            $table->string('option2')->nullable();
            $table->string('option3')->nullable();

            $table->unsignedInteger('position')->default(1);

            $table->string('currency', 10)->default('INR');
            $table->decimal('price', 10, 2)->default(0.00);
            $table->decimal('compare_at_price', 10, 2)->nullable();

            $table->decimal('weight', 10, 3)->nullable();
            $table->string('weight_unit', 8)->nullable();

            $table->boolean('taxable')->default(true);
            $table->boolean('requires_shipping')->default(true);

            $table->string('inventory_management')->nullable();
            $table->string('inventory_policy')->default('deny');
            $table->integer('inventory_quantity')->default(0);

            $table->timestamps();

            // indexes from your dump
            $table->index(['product_id', 'position'], 'pv_product_position_idx');
            $table->index(['product_id', 'sku'], 'pv_product_sku_idx');

            // FK behavior per dump (CASCADE on delete & update)
            $table->foreign('product_id')
                  ->references('product_id')->on('products')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variants');
    }
};
