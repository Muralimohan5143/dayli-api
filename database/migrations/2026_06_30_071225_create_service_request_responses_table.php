<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_request_responses', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger('service_request_id');

            $table->unsignedBigInteger('provider_id');
            $table->unsignedBigInteger('provider_service_id')->nullable();

            $table->json('response_json')->nullable();

            $table->text('message')->nullable();

            $table->decimal('quoted_price', 10, 2)->nullable();

            $table->date('proposed_date')->nullable();
            $table->time('proposed_time_from')->nullable();
            $table->time('proposed_time_to')->nullable();

            $table->enum('status', [
                'submitted',
                'shortlisted',
                'primary_selected',
                'secondary_selected',
                'backup_queued',
                'assigned',
                'accepted',
                'declined',
                'expired',
                'no_show',
                'completed',
                'rejected',
                'withdrawn'
            ])->default('submitted');

            $table->timestamps();

            $table->index(['service_request_id', 'status']);
            $table->index(['provider_id', 'status']);
            $table->index(['provider_service_id']);
            $table->index(['quoted_price']);

            $table->foreign('service_request_id')
                ->references('id')
                ->on('service_requests')
                ->cascadeOnDelete();

            $table->foreign('provider_service_id')
                ->references('id')
                ->on('provider_services')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_request_responses');
    }
};
