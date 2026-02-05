<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->bigIncrements('service_id');

            $table->string('title');
            $table->string('service_type');          // e.g., laundry, housekeeping, repair
            $table->string('handle')->unique();      // slug/handle
            $table->text('description')->nullable();
            $table->string('category')->nullable();  // optional grouping

            $table->json('tags')->nullable();
            $table->boolean('requires_booking')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('img_src')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            // Secondary indexes (match your dump)
            $table->index(['service_type', 'is_active'], 'services_type_active_idx');
            $table->index('category', 'services_category_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
