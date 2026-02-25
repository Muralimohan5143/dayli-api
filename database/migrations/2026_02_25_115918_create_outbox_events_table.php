// database/migrations/2026_02_25_000000_create_outbox_events_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('outbox_events', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('event_type', 100); // e.g. vendor_supply_entered
            $table->string('aggregate_type', 50)->nullable(); // e.g. order
            $table->unsignedBigInteger('aggregate_id')->nullable(); // e.g. orders.id

            $table->unsignedBigInteger('user_id')->nullable(); // vendor user id
            $table->json('payload'); // data for processing

            $table->enum('status', ['pending', 'processing', 'retry', 'success', 'failed'])
                ->default('pending');

            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('max_attempts')->default(12);

            $table->timestamp('available_at')->nullable()->index(); // when it can be retried
            $table->timestamp('locked_at')->nullable();
            $table->string('lock_token', 64)->nullable()->index();

            $table->text('last_error')->nullable();
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            // optional: idempotency key (recommended)
            $table->string('idempotency_key', 120)->nullable()->unique();
            $table->index(['status', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
    }
};
