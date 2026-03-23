<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('invoices')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $t) {

            if (!Schema::hasColumn('invoices', 'invoice_number')) {
                $t->string('invoice_number')->nullable()->after('number');
                $t->index('invoice_number', 'invoices_invoice_number_idx');
            }

            if (!Schema::hasColumn('invoices', 'invoice_date')) {
                $t->date('invoice_date')->nullable()->after('invoice_number');
            }

            if (!Schema::hasColumn('invoices', 'customer_id')) {
                $t->unsignedBigInteger('customer_id')->nullable()->after('order_id');
                $t->index('customer_id', 'invoices_customer_id_idx');
            }

            if (Schema::hasTable('customers') && Schema::hasColumn('invoices', 'customer_id')) {
                try {
                    $t->foreign('customer_id', 'invoices_customer_id_fk')
                        ->references('id')->on('customers')
                        ->nullOnDelete();
                } catch (\Throwable $e) {
                }
            }

            if (!Schema::hasColumn('invoices', 'billing_name')) {
                $t->string('billing_name')->nullable()->after('customer_id');
            }
            if (!Schema::hasColumn('invoices', 'billing_address_json')) {
                $t->json('billing_address_json')->nullable()->after('billing_name');
            }
            if (!Schema::hasColumn('invoices', 'buyer_gstin')) {
                $t->string('buyer_gstin')->nullable()->after('billing_address_json');
            }

            if (!Schema::hasColumn('invoices', 'currency')) {
                $t->string('currency', 3)->default('INR')->after('buyer_gstin');
            }

            if (!Schema::hasColumn('invoices', 'tax_total')) {
                $t->decimal('tax_total', 12, 2)->default(0)->after('tax');
            }
            if (!Schema::hasColumn('invoices', 'grand_total')) {
                $t->decimal('grand_total', 12, 2)->default(0)->after('total');
            }

            if (!Schema::hasColumn('invoices', 'payment_status')) {
                $t->enum('payment_status', ['unpaid', 'partial', 'paid'])
                    ->default('unpaid')
                    ->after('status');
                $t->index('payment_status', 'invoices_payment_status_idx');
            }

            if (!Schema::hasColumn('invoices', 'gst_status')) {
                $t->enum('gst_status', ['unfiled', 'filed'])
                    ->default('unfiled')
                    ->after('payment_status');
            }

            if (!Schema::hasColumn('invoices', 'gst_filing_period')) {
                $t->string('gst_filing_period')->nullable()->after('gst_status');
            }

            if (!Schema::hasColumn('invoices', 'deleted_at')) {
                $t->softDeletes();
            }

            try {
                $t->index(['invoice_date', 'gst_status', 'gst_filing_period'], 'invoices_gst_period_idx');
            } catch (\Throwable $e) {
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('invoices')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $t) {

            if (Schema::hasTable('customers')) {
                try {
                    $t->dropForeign('invoices_customer_id_fk');
                } catch (\Throwable $e) {
                }
            }

            foreach (
                [
                    'invoices_gst_period_idx',
                    'invoices_payment_status_idx',
                    'invoices_invoice_number_idx',
                    'invoices_customer_id_idx',
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
                    'gst_filing_period',
                    'gst_status',
                    'payment_status',
                    'grand_total',
                    'tax_total',
                    'currency',
                    'buyer_gstin',
                    'billing_address_json',
                    'billing_name',
                    'customer_id',
                    'invoice_date',
                    'invoice_number',
                ] as $col
            ) {
                if (Schema::hasColumn('invoices', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};