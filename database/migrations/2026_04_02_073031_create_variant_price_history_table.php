<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variant_price_history', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id');

            $table->decimal('price', 10, 2);

            $table->dateTime('effective_from');
            $table->dateTime('effective_to')->nullable();

            $table->unsignedBigInteger('changed_by')->nullable();
            $table->string('change_source', 50)->nullable();
            $table->string('note', 255)->nullable();

            $table->timestamps();

            // Indexes
            $table->index('product_id');
            $table->index('variant_id');
            $table->index(['product_id', 'variant_id']);
            $table->index(['variant_id', 'effective_from']);
            $table->index(['variant_id', 'effective_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_price_history');
    }
};
