<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();

            // generic item fields (since catalog may be external)
            $table->string('sku')->nullable();
            $table->string('title');          // e.g., "Vijaya Gold 500ml"
            $table->string('variant')->nullable();   // e.g., "500ml"
            $table->string('brand')->nullable();     // e.g., "Vijaya"

            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('line_total', 10, 2)->default(0);

            $table->json('meta')->nullable(); // time window, notes

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
