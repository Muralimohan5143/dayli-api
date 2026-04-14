<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('zone_id');
            $table->date('delivery_date');
            $table->unsignedBigInteger('subscription_type_id');
            $table->string('status', 20); // matched | mismatch
            $table->json('summary_json')->nullable();
            $table->json('mismatches_json')->nullable();
            $table->timestamps();

            $table->unique(
                ['zone_id', 'delivery_date', 'subscription_type_id'],
                'recon_reports_zone_date_subtype_uidx'
            );

            $table->index(['delivery_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_reports');
    }
};
