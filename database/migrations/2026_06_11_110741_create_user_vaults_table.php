<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_vaults', function (Blueprint $table) {
            $table->id();

            // One vault per user
            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();

            // Full vault data JSON.
            // Store text/data + file paths only.
            // Do NOT store image base64 here.
            $table->json('vault_json')->nullable();

            // Future security/helper fields
            $table->timestamp('last_unlocked_at')->nullable();
            $table->boolean('is_locked')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_vaults');
    }
};
