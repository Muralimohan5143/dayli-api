<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_request_assignments', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger('service_request_id');

            $table->unsignedBigInteger('provider_id');
            $table->unsignedBigInteger('provider_service_id')->nullable();
            $table->unsignedBigInteger('service_request_response_id')->nullable();

            /*
                1 = primary
                2 = secondary
                3,4,5 = fallback queue
            */
            $table->unsignedInteger('priority_order')->default(1);

            /*
                primary
                secondary
                backup
                auto_reassigned
            */
            $table->string('assignment_type', 50)->default('primary');

            /*
                assigned
                provider_confirmed
                provider_enroute
                in_progress
                completed
                declined
                expired
                no_show
                cancelled
            */
            $table->enum('status', [
                'assigned',
                'provider_confirmed',
                'provider_enroute',
                'in_progress',
                'completed',
                'declined',
                'expired',
                'no_show',
                'cancelled'
            ])->default('assigned');

            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('enroute_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->text('failure_reason')->nullable();

            $table->timestamps();

            $table->index(['service_request_id', 'status']);
            $table->index(['provider_id', 'status']);
            $table->index(['priority_order']);

            $table->foreign('service_request_id')
                ->references('id')
                ->on('service_requests')
                ->cascadeOnDelete();

            $table->foreign('provider_service_id')
                ->references('id')
                ->on('provider_services')
                ->nullOnDelete();

            $table->foreign('service_request_response_id')
                ->references('id')
                ->on('service_request_responses')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_request_assignments');
    }
};
