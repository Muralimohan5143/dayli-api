<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('my_day_feed_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('interest_key', 50);

            $table->string('title');

            $table->string('subtitle')->nullable();

            $table->text('body')->nullable();

            $table->string('image_url')->nullable();

            $table->string('source_name', 100)->nullable();

            $table->string('source_url')->nullable();

            $table->json('payload_json')->nullable();

            $table->date('feed_date');

            $table->unsignedInteger('sort_order')->default(0);

            $table->boolean('is_read')->default(false);

            $table->timestamps();

            $table->index(['user_id', 'feed_date']);
            $table->index(['user_id', 'interest_key']);
            $table->index(['user_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('my_day_feed_items');
    }
};
