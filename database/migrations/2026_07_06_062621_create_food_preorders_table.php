<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_preorders', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('food_menu_today_id');
            $table->unsignedBigInteger('customer_id');

            $table->integer('qty')->default(1);

            $table->enum('status', [
                'interested',
                'confirmed',
                'cancelled',
                'converted_to_order',
            ])->default('interested');

            $table->text('notes')->nullable();

            $table->unsignedBigInteger('order_id')->nullable();

            $table->timestamps();

            $table->index('food_menu_today_id');
            $table->index('customer_id');
            $table->index('status');
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_preorders');
    }
};
