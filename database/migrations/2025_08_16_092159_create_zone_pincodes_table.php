<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('zone_pincodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
            $table->string('pin_code')->index();
            $table->timestamps();

            $table->unique(['zone_id','pin_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zone_pincodes');
    }
};
