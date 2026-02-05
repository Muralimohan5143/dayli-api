<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('zone_product_variants');
        Schema::create('zone_product_variants', function (Blueprint $t) {
            $t->id();

            // Plain IDs (no foreign keys to avoid PK name mismatches)
            $t->unsignedBigInteger('zone_id');
            $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('variant_id')->nullable();

            // Simple flag
            $t->boolean('is_active')->default(true);

            $t->timestamps();
            $t->softDeletes();

            // Uniqueness per zone+product+variant
            $t->unique(['zone_id','product_id','variant_id'], 'uq_zone_product_variant');

            // Helpful indexes for lookups
            $t->index(['zone_id'], 'idx_zpv_zone');
            $t->index(['product_id'], 'idx_zpv_product');
            $t->index(['variant_id'], 'idx_zpv_variant');
            $t->index(['is_active'], 'idx_zpv_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zone_product_variants');
    }
};
