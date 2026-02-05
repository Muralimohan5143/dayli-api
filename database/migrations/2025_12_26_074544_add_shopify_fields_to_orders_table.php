<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {

            // Shopify identity
            $table->unsignedBigInteger('shopify_id')
                ->nullable()
                ->unique()
                ->after('id');

            $table->unsignedInteger('order_number')
                ->nullable()
                ->after('shopify_id');

            $table->string('shopify_name')
                ->nullable()
                ->after('order_number');

            $table->string('confirmation_number')
                ->nullable()
                ->after('shopify_name');

            // Shopify statuses
            $table->string('financial_status')
                ->nullable()
                ->after('status');

            $table->string('financial_status_label')
                ->nullable()
                ->after('financial_status');

            $table->string('fulfillment_status')
                ->nullable()
                ->after('financial_status_label');

            $table->string('fulfillment_status_label')
                ->nullable()
                ->after('fulfillment_status');

            // Cancel info
            $table->boolean('cancelled')
                ->default(false)
                ->after('fulfillment_status_label');

            $table->timestampTz('cancelled_at')
                ->nullable()
                ->after('cancelled');

            $table->string('cancel_reason')
                ->nullable()
                ->after('cancelled_at');

            $table->string('cancel_reason_label')
                ->nullable()
                ->after('cancel_reason');

            // Customer / URLs
            $table->string('email')
                ->nullable()
                ->after('cancel_reason_label');

            $table->text('order_status_url')
                ->nullable()
                ->after('email');

            // Counts & money breakdown
            $table->unsignedInteger('item_count')
                ->nullable()
                ->after('order_status_url');

            $table->decimal('line_items_subtotal_price', 10, 2)
                ->nullable()
                ->after('item_count');

            $table->decimal('shipping_price', 10, 2)
                ->nullable()
                ->after('line_items_subtotal_price');

            $table->decimal('total_refunded_amount', 10, 2)
                ->nullable()
                ->after('shipping_price');

            $table->decimal('total_net_amount', 10, 2)
                ->nullable()
                ->after('total_refunded_amount');

            $table->string('currency', 3)
                ->nullable()
                ->after('total_net_amount');

            // JSON blocks
            $table->json('tags')
                ->nullable()
                ->after('currency');

            $table->json('shipping_address')
                ->nullable()
                ->after('tags');

            $table->json('shipping_methods')
                ->nullable()
                ->after('shipping_address');

            $table->json('discounts')
                ->nullable()
                ->after('shipping_methods');

            $table->json('metafields')
                ->nullable()
                ->after('discounts');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {

            $table->dropColumn([
                'shopify_id',
                'order_number',
                'shopify_name',
                'confirmation_number',
                'financial_status',
                'financial_status_label',
                'fulfillment_status',
                'fulfillment_status_label',
                'cancelled',
                'cancelled_at',
                'cancel_reason',
                'cancel_reason_label',
                'email',
                'order_status_url',
                'item_count',
                'line_items_subtotal_price',
                'shipping_price',
                'total_refunded_amount',
                'total_net_amount',
                'currency',
                'tags',
                'shipping_address',
                'shipping_methods',
                'discounts',
                'metafields',
            ]);
        });
    }
};
