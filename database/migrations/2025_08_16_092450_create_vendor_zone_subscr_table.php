<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vendor_zone_subscr', function (Blueprint $table) {
            $table->id();

            // vendor is a user with role 'vendor-*'
            $table->foreignId('vendor_id')->constrained('users')->cascadeOnDelete();

            $table->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
            $table->foreignId('subscription_type_id')->constrained('subscription_types')->cascadeOnDelete();

            $table->enum('status', ['active','inactive'])->default('active');
            $table->boolean('is_preferred')->default(false);
            $table->unsignedInteger('lead_time_mins')->nullable(); // optional SLA-like hint
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(['vendor_id','zone_id','subscription_type_id'], 'vendor_zone_subscr_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_zone_subscr');
    }
};
