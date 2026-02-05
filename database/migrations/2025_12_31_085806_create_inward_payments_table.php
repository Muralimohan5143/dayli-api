<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inward_payments', function (Blueprint $t) {
            $t->id();

            // Optional direct link to order (for daily ops), plus Shopify order GID for reconciliation
            $t->foreignId('order_id')->nullable()
                ->constrained('orders')->nullOnDelete();

            $t->string('shopify_order_gid')->nullable()->index();

            // Optional monthly invoice link (when consolidating)
            $t->foreignId('invoice_id')->nullable()
                ->constrained('invoices')->cascadeOnDelete();

            // Linked-list style chain (previous payment)
            $t->foreignId('previous_payment_id')->nullable()
                ->constrained('inward_payments')->nullOnDelete();
            $t->unique('previous_payment_id');

            $t->date('payment_date')->nullable();

            $t->decimal('amount', 12, 2);
            $t->decimal('due_amount', 12, 2)->default(0);
            $t->string('currency', 3)->default('INR');

            // payment mode: Cash / UPI / Bank Transfer / Other
            $t->string('method')->nullable();

            // points to Shopify metaobject instance (gid://shopify/Metaobject/…)
            $t->string('shopify_metaobject_gid')->nullable()->unique();

            $t->text('note')->nullable();

            $t->timestamps();
            $t->softDeletes();

            $t->index(['invoice_id', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inward_payments');
    }
};
