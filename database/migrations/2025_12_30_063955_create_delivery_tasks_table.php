<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('delivery_tasks', function (Blueprint $table) {
            $table->id();

            // task identity
            $table->string('delivery_task');
            // example: "Milk Delivery – Morning", "Vegetables – Zone A"

            // who executes
            $table->unsignedBigInteger('delivery_exec_id')->index();

            // zone
            $table->unsignedBigInteger('zone_id')->index();

            // workflow
            $table->enum('status', [
                'today',
                'pending',
                'in_progress',
                'completed',
                'failed'
            ])->default('today')->index();

            // task window
            $table->date('start_date')->index();
            $table->date('end_date')->nullable()->index();

            // optional meta
            $table->json('meta')->nullable();

            $table->timestamps();

            // FKs (optional but recommended)
            $table->foreign('delivery_exec_id')->references('id')->on('users')->cascadeOnDelete();
            // $table->foreign('zone_id')->references('id')->on('zones');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_tasks');
    }
};
