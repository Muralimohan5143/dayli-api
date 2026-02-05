<?php

// database/migrations/2025_11_01_000001_create_freshsales_contact_filters_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('freshsales_contact_filters', function (Blueprint $table) {
            $table->id();
            // which Freshsales account (e.g. leela, dayli)
            $table->string('account_code', 50)->index();

            // remote filter payload
            $table->unsignedBigInteger('view_id');                 // remote filter id
            $table->string('name');                                // remote filter name
            $table->string('model_class_name')->nullable();        // "Contact"
            $table->unsignedBigInteger('user_id')->nullable();     // who owns it (0 = system)
            $table->string('user_name')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamp('remote_updated_at')->nullable();    // API "updated_at"
            $table->json('current_user_permissions')->nullable();  // JSON array

            $table->timestamps();

            // Prevent duplicates per account
            $table->unique(['account_code', 'view_id']);
            // Optional: fast lookup by name within an account
            $table->index(['account_code', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('freshsales_contact_filters');
    }
};
