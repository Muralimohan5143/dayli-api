<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscription_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
            $table->foreignId('subscription_type_id')->constrained('subscription_types')->cascadeOnDelete();

            $table->enum('status', ['active','inactive'])->default('active');
            $table->date('available_from')->nullable();
            $table->date('available_to')->nullable();
            $table->boolean('is_default')->default(false);
            $table->json('meta')->nullable(); // extra knobs (e.g., UI labels, order windows)

            $table->timestamps();

            $table->unique(['zone_id','subscription_type_id'], 'subscription_zones_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_zones');
    }
};
