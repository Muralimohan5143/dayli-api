<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {

            // Shopify identifiers
            if (!Schema::hasColumn('orders', 'shopify_order_gid')) {
                $t->string('shopify_order_gid')->nullable()->after('shopify_id');
                $t->index('shopify_order_gid', 'orders_shopify_order_gid_idx');
            }
            if (!Schema::hasColumn('orders', 'shopify_legacy_id')) {
                $t->unsignedBigInteger('shopify_legacy_id')->nullable()->after('shopify_order_gid');
                $t->index('shopify_legacy_id', 'orders_shopify_legacy_id_idx');
            }

            // Name + phone
            if (!Schema::hasColumn('orders', 'name')) {
                $t->string('name')->nullable()->after('shopify_name');
                $t->index('name', 'orders_name_idx');
            }
            if (!Schema::hasColumn('orders', 'phone')) {
                $t->string('phone')->nullable()->after('email');
                $t->index('phone', 'orders_phone_idx');
            }

            // address json (your table already has shipping_address json; keep both)
            if (!Schema::hasColumn('orders', 'shipping_address_json')) {
                $t->json('shipping_address_json')->nullable()->after('shipping_address');
            }
            if (!Schema::hasColumn('orders', 'billing_address_json')) {
                $t->json('billing_address_json')->nullable()->after('shipping_address_json');
            }

            // currency code style
            if (!Schema::hasColumn('orders', 'currency_code')) {
                $t->string('currency_code', 3)->default('INR')->after('currency');
            }
            if (!Schema::hasColumn('orders', 'presentment_currency_code')) {
                $t->string('presentment_currency_code', 3)->nullable()->after('currency_code');
            }

            // display statuses
            if (!Schema::hasColumn('orders', 'display_financial_status')) {
                $t->string('display_financial_status')->nullable()->after('financial_status');
            }
            if (!Schema::hasColumn('orders', 'display_fulfillment_status')) {
                $t->string('display_fulfillment_status')->nullable()->after('fulfillment_status');
            }

            // timestamps from shopify
            if (!Schema::hasColumn('orders', 'created_at_shopify')) {
                $t->timestamp('created_at_shopify')->nullable()->after('created_at');
            }
            if (!Schema::hasColumn('orders', 'processed_at_shopify')) {
                $t->timestamp('processed_at_shopify')->nullable()->after('created_at_shopify');
            }
            if (!Schema::hasColumn('orders', 'updated_at_shopify')) {
                $t->timestamp('updated_at_shopify')->nullable()->after('processed_at_shopify');
            }
            if (!Schema::hasColumn('orders', 'cancelled_at_shopify')) {
                $t->timestamp('cancelled_at_shopify')->nullable()->after('updated_at_shopify');
            }
            if (!Schema::hasColumn('orders', 'closed_at_shopify')) {
                $t->timestamp('closed_at_shopify')->nullable()->after('cancelled_at_shopify');
            }

            // boolean flags
            if (!Schema::hasColumn('orders', 'confirmed')) {
                $t->boolean('confirmed')->default(false)->after('status');
            }
            if (!Schema::hasColumn('orders', 'closed')) {
                $t->boolean('closed')->default(false)->after('confirmed');
            }
            if (!Schema::hasColumn('orders', 'requires_shipping')) {
                $t->boolean('requires_shipping')->default(true)->after('closed');
            }
            if (!Schema::hasColumn('orders', 'taxes_included')) {
                $t->boolean('taxes_included')->default(false)->after('requires_shipping');
            }
            if (!Schema::hasColumn('orders', 'tax_exempt')) {
                $t->boolean('tax_exempt')->default(false)->after('taxes_included');
            }
            if (!Schema::hasColumn('orders', 'test')) {
                $t->boolean('test')->default(false)->after('tax_exempt');
            }
            if (!Schema::hasColumn('orders', 'unpaid')) {
                $t->boolean('unpaid')->default(false)->after('test');
            }

            // money fields (default 0)
            $moneyCols = [
                'current_subtotal',
                'current_shipping',
                'current_discounts',
                'current_tax',
                'current_total',
                'total_received',
                'total_outstanding',
                'total_refunded',
            ];

            foreach ($moneyCols as $col) {
                if (!Schema::hasColumn('orders', $col)) {
                    $t->decimal($col, 12, 2)->default(0)->after('unpaid');
                }
            }

            if (!Schema::hasColumn('orders', 'source_name')) {
                $t->string('source_name')->nullable()->after('meta');
            }
            if (!Schema::hasColumn('orders', 'status_page_url')) {
                $t->string('status_page_url')->nullable()->after('source_name');
            }
            if (!Schema::hasColumn('orders', 'note')) {
                $t->text('note')->nullable()->after('status_page_url');
            }

            // soft deletes
            if (!Schema::hasColumn('orders', 'deleted_at')) {
                $t->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) {

            foreach (
                [
                    'orders_shopify_order_gid_idx',
                    'orders_shopify_legacy_id_idx',
                    'orders_name_idx',
                    'orders_phone_idx',
                ] as $idx
            ) {
                try {
                    $t->dropIndex($idx);
                } catch (\Throwable $e) {
                }
            }

            foreach (
                [
                    'deleted_at',
                    'note',
                    'status_page_url',
                    'source_name',
                    'total_refunded',
                    'total_outstanding',
                    'total_received',
                    'current_total',
                    'current_tax',
                    'current_discounts',
                    'current_shipping',
                    'current_subtotal',
                    'unpaid',
                    'test',
                    'tax_exempt',
                    'taxes_included',
                    'requires_shipping',
                    'closed',
                    'confirmed',
                    'closed_at_shopify',
                    'cancelled_at_shopify',
                    'updated_at_shopify',
                    'processed_at_shopify',
                    'created_at_shopify',
                    'display_fulfillment_status',
                    'display_financial_status',
                    'presentment_currency_code',
                    'currency_code',
                    'billing_address_json',
                    'shipping_address_json',
                    'phone',
                    'name',
                    'shopify_legacy_id',
                    'shopify_order_gid',
                ] as $col
            ) {
                if (Schema::hasColumn('orders', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
