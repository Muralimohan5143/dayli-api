<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_delivery_exceptions', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('zone_id');
            $table->date('delivery_date');
            $table->unsignedBigInteger('subscription_type_id');
            $table->unsignedBigInteger('variant_id');

            $table->string('exception_type', 20); // procurement | adhoc | loss
            $table->string('direction', 10);      // in | out
            $table->decimal('qty', 10, 2)->default(0);

            $table->string('reason_code', 50)->nullable(); // extra_pickup | damaged | missing | manual_adjustment
            $table->text('discussion')->nullable();

            $table->string('status', 20)->default('pending'); // pending | approved | rejected

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->json('meta_json')->nullable();

            $table->timestamps();

            $table->index(['zone_id', 'delivery_date'], 'pde_zone_date_idx');
            $table->index(['subscription_type_id', 'delivery_date'], 'pde_subtype_date_idx');
            $table->index(['variant_id', 'delivery_date'], 'pde_variant_date_idx');
            $table->index(['status'], 'pde_status_idx');

            $table->unique(
                ['zone_id', 'delivery_date', 'subscription_type_id', 'variant_id', 'exception_type', 'direction', 'reason_code'],
                'pde_zone_date_subtype_variant_type_dir_reason_uidx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_delivery_exceptions');
    }
};
