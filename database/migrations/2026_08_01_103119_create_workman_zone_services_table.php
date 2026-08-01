<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workman_zone_services', function (Blueprint $table) {
            $table->id();

            $table->foreignId('workman_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('zone_id')
                ->constrained('zones')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('service_variant_id');

            $table->enum('status', [
                'pending',
                'under_review',
                'approved',
                'rejected',
                'inactive',
                'suspended',
            ])->default('pending')->index();

            $table->boolean('is_active')
                ->default(false)
                ->index();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')->nullable();

            $table->text('rejection_reason')->nullable();

            $table->boolean('is_preferred')
                ->default(false)
                ->index();

            $table->unsignedInteger('lead_time_mins')->nullable();

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->foreign('service_variant_id')
                ->references('variant_id')
                ->on('service_variants')
                ->cascadeOnDelete();

            $table->unique(
                [
                    'workman_id',
                    'zone_id',
                    'service_variant_id',
                ],
                'workman_zone_services_unique'
            );

            $table->index(
                [
                    'zone_id',
                    'service_variant_id',
                    'status',
                    'is_active',
                ],
                'workman_zone_services_lookup'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workman_zone_services');
    }
};
