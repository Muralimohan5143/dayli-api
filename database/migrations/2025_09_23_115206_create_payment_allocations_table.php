<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('payment_allocations')) {
            Schema::create('payment_allocations', function (Blueprint $t) {
                $t->id();

                $t->unsignedBigInteger('payment_id');

                // Polymorphic target: can be invoices now, vendor_bills later
                $t->string('allocatable_type');   // e.g., 'App\\Models\\Invoice'
                $t->unsignedBigInteger('allocatable_id');

                $t->decimal('amount_applied', 10, 2);
                $t->timestamps();

                $t->unique(['payment_id', 'allocatable_type', 'allocatable_id'], 'payment_alloc_unique');
                $t->index(['allocatable_type', 'allocatable_id'], 'payment_alloc_target_idx');

                $t->foreign('payment_id')->references('id')->on('payments')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
