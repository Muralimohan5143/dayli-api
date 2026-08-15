<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('upi_payment_attempts', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('invoice_id');

            $table->decimal('amount', 12, 2);

            // Our unique reference sent to PhonePe/GPay.
            $table->string('payment_reference', 100)->unique();

            // Returned by UPI app when available.
            $table->string('upi_transaction_id', 191)->nullable();
            $table->string('response_code', 50)->nullable();

            $table->enum('status', [
                'initiated',
                'pending',
                'successful',
                'failed',
                'cancelled',
            ])->default('initiated');

            $table->text('raw_response')->nullable();

            // Filled only after successful payment is actually allocated.
            $table->unsignedBigInteger('payment_id')->nullable();

            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('invoice_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upi_payment_attempts');
    }
};
