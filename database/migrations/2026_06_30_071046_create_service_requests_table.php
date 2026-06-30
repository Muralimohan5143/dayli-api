<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('zone_id')->nullable();

            $table->string('title')->nullable();
            $table->text('ai_summary')->nullable();

            $table->json('request_json')->nullable();
            $table->json('attachments_json')->nullable();

            $table->date('preferred_date')->nullable();
            $table->time('preferred_time_from')->nullable();
            $table->time('preferred_time_to')->nullable();

            $table->text('address')->nullable();
            $table->string('nagar')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->enum('status', [
                'draft',
                'posted',
                'customer_review',
                'assigned',
                'provider_confirmed',
                'provider_enroute',
                'in_progress',
                'completed',
                'cancelled',
            ])->default('draft');

            $table->unsignedBigInteger('primary_provider_id')->nullable();
            $table->unsignedBigInteger('secondary_provider_id')->nullable();
            $table->unsignedBigInteger('current_provider_id')->nullable();

            $table->unsignedInteger('assignment_attempts')->default(0);
            $table->unsignedInteger('no_show_count')->default(0);
            $table->boolean('auto_reassign_enabled')->default(true);
            $table->timestamp('last_assignment_at')->nullable();

            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['service_id', 'status']);
            $table->index(['zone_id', 'status']);
            $table->index(['current_provider_id', 'status']);

            $table->foreign('service_id')
                ->references('service_id')
                ->on('services')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
