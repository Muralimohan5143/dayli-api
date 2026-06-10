<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('my_day_routine_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('routine_id')
                ->constrained('my_day_routines')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->date('log_date');

            $table->enum('status', [
                'pending',
                'completed',
                'skipped',
            ])->default('pending');

            $table->timestamp('completed_at')->nullable();

            $table->text('note')->nullable();

            $table->timestamps();

            $table->unique(['routine_id', 'log_date']);

            $table->index(['user_id', 'log_date']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('my_day_routine_logs');
    }
};
