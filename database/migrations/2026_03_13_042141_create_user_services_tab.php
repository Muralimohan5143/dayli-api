<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_services', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('role_name', 50); // customer, vendor, workman
            $table->string('service_handle', 100)->nullable(); // milk, delivery-boy, plumber
            $table->unsignedBigInteger('subscription_type_id')->nullable();
            $table->unsignedBigInteger('zone_id')->nullable();

            $table->enum('status', [
                'pending',
                'under_review',
                'approved',
                'rejected',
                'inactive',
                'suspended',
            ])->default('pending');

            $table->boolean('is_active')->default(true);

            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('role_name');
            $table->index('service_handle');
            $table->index('subscription_type_id');
            $table->index('zone_id');
            $table->index('status');

            $table->unique([
                'user_id',
                'role_name',
                'service_handle',
                'subscription_type_id',
                'zone_id',
            ], 'user_services_unique_combo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_services');
    }
};
