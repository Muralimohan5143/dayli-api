<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disputes', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');

            // Source references
            $table->unsignedBigInteger('subscription_item_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('order_item_id')->nullable();

            // Expected (ordered) product
            $table->unsignedBigInteger('expected_product_id');
            $table->unsignedBigInteger('expected_variant_id')->nullable();
            $table->decimal('expected_qty', 10, 2)->default(1.00);

            // Dispute info
            $table->enum('dispute_type', [
                'wrong_product',
                'quantity_mismatch',
                'not_delivered',
                'quality_issue',
                'other',
            ])->default('wrong_product');

            /**
             * delivered_items JSON example:
             * [
             *   {"p_id":1,"v_id":1,"qty":1},
             *   {"p_id":2,"v_id":2,"qty":1}
             * ]
             */
            $table->json('delivered_items')->nullable();

            // Optional derived total
            $table->decimal('delivered_qty', 10, 2)->nullable();

            $table->text('notes')->nullable();

            $table->enum('status', [
                'open',
                'in_review',
                'resolved',
                'rejected',
            ])->default('open');

            $table->date('dispute_date')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['user_id', 'status']);
            $table->index('dispute_date');
            $table->index('expected_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};
