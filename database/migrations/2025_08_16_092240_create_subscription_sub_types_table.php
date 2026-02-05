<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscription_sub_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_type_id')
                  ->constrained('subscription_types')
                  ->cascadeOnDelete();
            $table->string('name');                  // e.g. "Leafy Veg"
            $table->string('slug')->unique();        // e.g. "leafy_veg"  (global-unique)
            $table->enum('status', ['active','inactive'])->default('active');
            $table->timestamps();

            $table->index(['subscription_type_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('subscription_sub_types');
    }
};
