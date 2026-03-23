<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('customers')) {
            return;
        }

        if (!Schema::hasTable('provider_accounts')) {
            return;
        }

        Schema::create('customer_identities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            $table->foreignId('provider_account_id')
                ->constrained('provider_accounts')
                ->cascadeOnDelete();

            $table->string('external_id', 191);
            $table->string('external_legacy_id', 191)->nullable();
            $table->string('status', 32)->nullable();

            $table->timestamp('last_synced_at')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(['provider_account_id', 'external_id'], 'identities_unique_per_account');

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
