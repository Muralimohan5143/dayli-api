<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            // user mapping
            $table->unsignedBigInteger('user_id')->index();

            // core content
            $table->string('title');
            $table->text('body')->nullable();

            // structured payload (order_id, type, etc)
            $table->json('data')->nullable();

            // read status
            $table->boolean('is_read')->default(false)->index();

            // type (optional but useful)
            $table->string('type')->nullable()->index();
            // examples: order_created, delivery_assigned, payment_done

            // source (optional)
            $table->string('source')->nullable();
            // ex: system, admin, ops_engine

            // timestamps
            $table->timestamps();

            // optional future support
            $table->timestamp('read_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
