<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('outbox_events', function (Blueprint $table) {
            $table->bigIncrements('id');

            // identity
            $table->string('event_type', 120);
            $table->string('aggregate_type', 80)->nullable();
            $table->unsignedBigInteger('aggregate_id')->nullable();
            $table->uuid('correlation_id')->nullable();
            $table->string('idempotency_key', 191)->nullable()->unique();

            // scheduling + execution
            $table->dateTime('scheduled_at')->index();
            $table->enum('status', ['pending', 'processing', 'retrying', 'succeeded', 'failed', 'dead'])
                ->default('pending')
                ->index();

            $table->unsignedTinyInteger('priority')->default(5)->index(); // 1 high, 9 low
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('max_attempts')->default(10);

            $table->dateTime('locked_at')->nullable()->index();
            $table->string('locked_by', 120)->nullable();

            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();

            $table->longText('last_error')->nullable();

            $table->json('payload');
            $table->json('result')->nullable();

            $table->enum('notify_on', ['none', 'failure', 'always'])->default('failure');

            $table->timestamps();

            $table->index(['status', 'scheduled_at', 'priority'], 'idx_due');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
    }
};
