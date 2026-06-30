<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('provider_services', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('provider_id');
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('variant_id')->nullable();

            $table->text('description')->nullable();
            $table->decimal('starting_price', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['provider_id', 'service_id', 'variant_id'], 'provider_service_unique');

            $table->index('provider_id');
            $table->index('service_id');
            $table->index('variant_id');

            $table->foreign('service_id')
                ->references('service_id')
                ->on('services')
                ->cascadeOnDelete();

            $table->foreign('variant_id')
                ->references('variant_id')
                ->on('service_variants')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_services');
    }
};
