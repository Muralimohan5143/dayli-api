<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('my_day_routines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('title');

            $table->text('description')->nullable();

            $table->string('icon', 50)->nullable();

            $table->enum('frequency_type', [
                'daily',
                'weekdays',
                'weekends',
                'weekly',
                'custom',
            ])->default('daily');

            $table->json('days_of_week')->nullable();

            $table->time('time_of_day')->nullable();

            $table->timestamp('remind_at')->nullable();

            $table->boolean('is_active')->default(true);

            $table->unsignedInteger('current_streak')->default(0);

            $table->unsignedInteger('best_streak')->default(0);

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->softDeletes();

            $table->index(['user_id', 'is_active']);
            $table->index(['user_id', 'frequency_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('my_day_routines');
    }
};
