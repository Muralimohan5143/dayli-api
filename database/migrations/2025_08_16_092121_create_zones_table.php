<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');                 // e.g., "Kurnool Checkpost Zone"
            $table->string('code')->unique();       // e.g., "zone_kurnool_checkpost"
            $table->text('nagars')->nullable();     // comma-separated list, manageable via UI
            $table->string('focal_pt')->nullable(); // "Nandyal Checkpost"
            $table->decimal('focal_lat', 10, 6)->nullable();
            $table->decimal('focal_lon', 10, 6)->nullable();
            $table->enum('status', ['active','inactive'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zones');
    }
};
