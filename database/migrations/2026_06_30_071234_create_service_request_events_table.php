<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_request_events', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger('service_request_id');

            // Who performed the action
            $table->unsignedBigInteger('actor_id')->nullable();

            /*
                customer
                provider
                system
                admin
                ai
            */
            $table->string('actor_type', 50)->nullable();

            /*
                request_posted
                response_received
                provider_selected
                provider_confirmed
                provider_declined
                no_show
                reassigned
                job_started
                job_completed
                cancelled
            */
            $table->string('event_type', 100);

            // Flexible payload
            $table->json('event_json')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['service_request_id', 'created_at']);
            $table->index(['event_type']);
            $table->index(['actor_id', 'actor_type']);

            $table->foreign('service_request_id')
                ->references('id')
                ->on('service_requests')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_request_events');
    }
};
