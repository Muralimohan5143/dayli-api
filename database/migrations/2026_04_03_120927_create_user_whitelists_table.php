<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_whitelists', function (Blueprint $table) {
            $table->id();

            $table->string('name')->nullable();
            $table->string('phone')->unique(); // primary identifier
            $table->string('email')->nullable();

            $table->string('role'); // admin, vendor, delivery-boy, etc
            $table->unsignedBigInteger('zone_id')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamp('approved_at')->nullable();

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index('phone');
            $table->index('role');
            $table->index('zone_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_whitelists');
    }
};
