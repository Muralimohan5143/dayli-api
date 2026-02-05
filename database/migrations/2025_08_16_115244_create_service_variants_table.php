<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_variants', function (Blueprint $table) {
            $table->bigIncrements('variant_id');

            $table->unsignedBigInteger('service_id');
            $table->string('title');
            $table->string('sku')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->string('currency', 10)->default('INR');
            $table->decimal('price', 10, 2)->default(0.00);
            $table->decimal('compare_at_price', 10, 2)->nullable();
            $table->boolean('taxable')->default(true);
            $table->unsignedInteger('max_parallel_jobs')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            // Unique + indexes
            $table->unique(['service_id', 'title'], 'svc_variants_service_title_uidx');
            $table->index(['service_id', 'price'], 'svc_variants_price_idx');

            // Foreign key
            $table->foreign('service_id')
                  ->references('service_id')
                  ->on('services')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_variants');
    }
};
