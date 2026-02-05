<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_identities', function (Blueprint $table) {
            $table->id();

            // Link to your global customer
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            // Link to a specific external account (e.g., 'shopify'+'leela', 'interakt'+'dayli', 'freshsales'+'leela')
            $table->foreignId('provider_account_id')
                ->constrained('provider_accounts')
                ->cascadeOnDelete();

            // External identifiers for that account
            $table->string('external_id', 191);               // Shopify GID, Interakt UUID, Freshsales numeric id
            $table->string('external_legacy_id', 191)->nullable(); // Shopify legacy ID, etc.
            $table->string('status', 32)->nullable();         // active, archived, etc.

            // Per-account sync state & metadata
            $table->timestamp('last_synced_at')->nullable();
            $table->json('meta')->nullable();                 // e.g., wa_opted_in, tags, list_ids, etc.

            $table->timestamps();

            // Prevent duplicates inside same account
            $table->unique(['provider_account_id', 'external_id'], 'identities_unique_per_account');

            // Useful lookups
            $table->index('customer_id');
            $table->index('external_id');
            $table->index('last_synced_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_identities');
    }
};
