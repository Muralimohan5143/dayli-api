<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $t) {
                $t->id();

                // Two-way party (AR: customer, AP: vendor)
                $t->enum('party_type', ['customer','vendor'])->index();
                $t->unsignedBigInteger('party_id')->index();   // -> users.id

                // Direction: 'in' = receipt (from customer), 'out' = payout (to vendor)
                $t->enum('direction', ['in','out'])->index();

                // When money moved
                $t->timestamp('received_at'); // also used for payouts

                $t->enum('method', ['cash','upi','card','bank','wallet','other'])->default('cash');
                $t->string('reference_no', 191)->nullable()->index();

                $t->decimal('amount', 10, 2);   // use 'amount' (neutral name)
                $t->enum('status', ['posted','reversed'])->default('posted');
                $t->unsignedBigInteger('created_by')->nullable(); // collector / operator
                $t->json('meta')->nullable();

                $t->timestamps();

                $t->index(['party_type','party_id','received_at'], 'payments_party_when_idx');

                // Soft FK (keep loose to avoid coupling users subtypes)
                $t->foreign('party_id')->references('id')->on('users')->cascadeOnDelete();
                $t->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
