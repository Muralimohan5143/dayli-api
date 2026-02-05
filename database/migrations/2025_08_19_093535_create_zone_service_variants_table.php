<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('zone_service_variants');
        Schema::create('zone_service_variants', function (Blueprint $t) {
            $t->id();

            // Plain IDs (no foreign keys)
            $t->unsignedBigInteger('zone_id');
            $t->unsignedBigInteger('service_id');
            $t->unsignedBigInteger('variant_id')->nullable();

            $t->boolean('is_active')->default(true);

            $t->timestamps();
            $t->softDeletes();

            // Uniqueness per zone+service+variant
            $t->unique(['zone_id','service_id','variant_id'], 'uq_zone_service_variant');

            // Helpful indexes
            $t->index(['zone_id'], 'idx_zsv_zone');
            $t->index(['service_id'], 'idx_zsv_service');
            $t->index(['variant_id'], 'idx_zsv_variant');
            $t->index(['is_active'], 'idx_zsv_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zone_service_variants');
    }
};
