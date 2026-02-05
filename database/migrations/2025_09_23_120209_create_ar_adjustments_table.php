<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('ar_adjustments')) {
            Schema::create('ar_adjustments', function (Blueprint $t) {
                $t->id();

                // Two-way party
                $t->enum('party_type', ['customer', 'vendor'])->index();
                $t->unsignedBigInteger('party_id')->index();

                // Optional: tie to a doc (invoice now, vendor bill later)
                $t->string('allocatable_type')->nullable();
                $t->unsignedBigInteger('allocatable_id')->nullable();

                $t->enum('type', ['credit', 'debit']); // credit reduces AR/AP; debit increases
                $t->decimal('amount', 10, 2);
                $t->string('reason', 255);
                $t->unsignedBigInteger('created_by')->nullable();
                $t->json('meta')->nullable();
                $t->timestamps();

                $t->index(['allocatable_type', 'allocatable_id'], 'ar_adj_target_idx');

                $t->foreign('party_id')->references('id')->on('users')->cascadeOnDelete();
                $t->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ar_adjustments');
    }
};
