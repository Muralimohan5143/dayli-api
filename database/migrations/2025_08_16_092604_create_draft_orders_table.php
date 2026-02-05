<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draft_orders', function (Blueprint $t) {
            $t->id();

            // Root intent link
            $t->foreignId('change_request_id')
                ->constrained('sub_change_requests')
                ->cascadeOnDelete();

            // Parties
            $t->foreignId('customer_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $t->foreignId('vendor_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $t->foreignId('zone_id')->nullable()
                ->constrained('zones')->nullOnDelete();

            // // Types / sub-types
            // $t->foreignId('subscription_type_id')->nullable()
            //     ->constrained('subscription_types')->nullOnDelete();
            // $t->foreignId('subscription_subtype_id')->nullable()
            //     ->constrained('subscription_sub_types')->nullOnDelete();

            // Cadence & invoicing
            $t->enum('cadence', [
                'daily',
                'alternate_days',
                'weekdays',
                'weekends',
                'sat',
                'sun',
                'custom',
                'on_demand'
            ])->default('daily');
            $t->text('custom_frequency_format')->nullable();
            $t->enum('invoice_cycle', ['monthly', 'weekly', 'custom'])
                ->default('monthly');

            // Window
            $t->date('start_date');
            $t->date('end_date')->nullable();

            // Status & locks
            $t->enum('status', ['active', 'archived'])->default('active');
            $t->timestamp('locked_at')->nullable();

            // Misc
            $t->string('timezone', 64)->default('Asia/Kolkata');
            $t->string('title')->nullable();
            $t->json('pricing_policy')->nullable();
            $t->json('tax_policy')->nullable();
            $t->json('meta')->nullable();
            $t->timestamps();

            // At most one ACTIVE draft per change request
            $t->unique(['change_request_id', 'status'], 'draft_orders_active_cr_uidx');

            // Useful indexes
            $t->index(['vendor_id', 'status', 'start_date'], 'draft_vendor_status_start_idx');
            $t->index(['customer_id', 'status', 'start_date'], 'draft_customer_status_start_idx');
            $t->index(['zone_id', 'status'], 'draft_zone_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draft_orders');
    }
};
