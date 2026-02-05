<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);      // 'shopify', 'interakt', 'freshsales', etc.
            $table->string('account_code', 64);  // 'leela', 'dayli', 'leelashop.in', 'wa-leela'
            $table->string('display_name')->nullable();  // "Leela Shopify", "Dayli Interakt"
            $table->boolean('is_active')->default(true);
            $table->json('config')->nullable();  // domain, list ids, phone, API meta, etc.
            $table->timestamps();

            // One row per provider + account_code
            $table->unique(['provider', 'account_code'], 'provider_accounts_unique_key');

            // Common filters
            $table->index('provider');
            $table->index('account_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_accounts');
    }
};
