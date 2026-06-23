<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('food_menus', function (Blueprint $table) {

            $table->id();

            // Chef/Vendor who uploaded menu
            $table->unsignedBigInteger('chef_id');

            // Delivery zone
            $table->unsignedBigInteger('zone_id');

            // Menu date
            $table->date('menu_date');

            // Breakfast/Lunch/Dinner/Snacks
            $table->enum('meal_type', [
                'breakfast',
                'lunch',
                'dinner',
                'snacks',
            ]);

            // Example: Andhra Meals
            $table->string('item_name');

            // Optional description
            $table->text('description')->nullable();

            // Price
            $table->decimal('price', 8, 2);

            // Available quantity
            $table->integer('available_qty')->default(0);

            // Veg or Non-Veg
            $table->boolean('is_veg')->default(true);

            // Last time to place order
            $table->time('cutoff_time')->nullable();

            // Active/Inactive
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Indexes for faster searches
            $table->index(['zone_id', 'menu_date']);
            $table->index(['chef_id', 'menu_date']);
            $table->index('meal_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('food_menus');
    }
};
