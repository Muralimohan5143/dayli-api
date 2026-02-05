<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscription_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();     // e.g., "Milk / Dairy"
            $table->string('slug')->nullable()->unique();
            $table->text('description')->nullable();
            $table->string('img_src')->nullable();
            $table->enum('status', ['active','inactive'])->default('active');
            $table->date('decommissioned_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_types');
    }
};
