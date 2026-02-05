<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // Shopify-ish customer fields
            $table->unsignedBigInteger('shopify_customer_id')->nullable()->index();
            $table->string('name');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('state')->nullable();
            $table->boolean('verified_email')->default(false);
            $table->string('currency')->nullable();
            $table->string('locale')->nullable();
            $table->boolean('tax_exempt')->default(false);
            $table->text('note')->nullable();
            $table->text('tags')->nullable();
            $table->json('marketing_opt_in_level')->nullable();
            $table->timestamp('shopify_created_at')->nullable();
            $table->timestamp('shopify_updated_at')->nullable();

            // App profile fields
            $table->string('password')->nullable();
            $table->string('day')->nullable();
            $table->string('month')->nullable();
            $table->string('year')->nullable();
            $table->string('gender')->nullable();
            $table->string('language')->nullable();
            $table->string('skills')->nullable();
            $table->string('avatar')->nullable();
            $table->string('company')->nullable();
            $table->string('twitter')->nullable();
            $table->string('facebook')->nullable();
            $table->string('instagram')->nullable();
            $table->string('public_email')->nullable();
            $table->text('bio')->nullable();

            // Contacts
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable()->index();

            // Zone affinity
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();

            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
