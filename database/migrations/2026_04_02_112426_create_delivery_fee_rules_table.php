<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('delivery_fee_rules', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->nullable();

            $table->decimal('fixed_fee', 10, 2)->nullable();
            $table->text('formula_fee')->nullable();

            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('product_id');
            $table->index('variant_id');
            $table->index(['product_id', 'variant_id']);
            $table->index(['is_active', 'priority']);

            // optional safety: one rule per product+variant pair
            $table->unique(['product_id', 'variant_id'], 'delivery_fee_rules_product_variant_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_fee_rules');
    }
};
