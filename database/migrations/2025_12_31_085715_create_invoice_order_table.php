<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoice_order', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $t->foreignId('order_id')->constrained('orders')->cascadeOnDelete();

            $t->decimal('allocated_total', 12, 2)->default(0);

            $t->timestamps();

            $t->unique(['invoice_id', 'order_id']);
            $t->index(['order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_order');
    }
};
