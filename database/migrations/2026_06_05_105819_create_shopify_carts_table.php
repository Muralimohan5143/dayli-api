<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_carts', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();

            $table->string('shopify_cart_gid')->unique();

            $table->text('checkout_url')->nullable();

            $table->string('currency_code', 3)->default('INR');

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('total_tax', 12, 2)->default(0);

            $table->enum('status', [
                'active',
                'checkout_started',
                'completed',
                'abandoned'
            ])->default('active');

            $table->json('raw_shopify_json')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_carts');
    }
};
