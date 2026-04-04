<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('outbox_reports', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('zone_manager_id');
            $table->string('report_type', 50);

            $table->unsignedBigInteger('subscription_type_id')->nullable();
            $table->unsignedBigInteger('service_type_id')->nullable();

            $table->enum('status', [
                'pending',
                'processing',
                'generated',
                'sent',
                'failed',
                'closed',
            ])->default('pending');

            $table->date('start_date');
            $table->date('end_date');

            $table->json('payload_json')->nullable();

            $table->dateTime('generated_at')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->dateTime('sent_at')->nullable();

            $table->timestamps();

            $table->index('zone_manager_id');
            $table->index('report_type');
            $table->index('subscription_type_id');
            $table->index('service_type_id');
            $table->index('status');
            $table->index(['start_date', 'end_date']);

            $table->unique([
                'zone_manager_id',
                'report_type',
                'subscription_type_id',
                'service_type_id',
                'start_date',
                'end_date',
            ], 'outbox_reports_unique_task');

            $table->foreign('zone_manager_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('subscription_type_id')
                ->references('id')
                ->on('subscription_types')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_reports');
    }
};
