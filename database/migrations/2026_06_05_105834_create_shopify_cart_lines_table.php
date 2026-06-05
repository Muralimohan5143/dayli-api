<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_cart_lines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shopify_cart_id')
                ->constrained('shopify_carts')
                ->cascadeOnDelete();

            $table->string('shopify_line_gid')->nullable()->index();

            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->unsignedBigInteger('variant_id')->nullable()->index();

            $table->string('shopify_product_gid')->nullable()->index();
            $table->string('shopify_variant_gid')->nullable()->index();

            $table->string('title');
            $table->string('variant_title')->nullable();

            $table->unsignedInteger('qty')->default(1);

            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);

            $table->json('raw_shopify_json')->nullable();

            $table->timestamps();

            $table->unique(
                ['shopify_cart_id', 'shopify_line_gid'],
                'shopify_cart_lines_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_cart_lines');
    }
};
