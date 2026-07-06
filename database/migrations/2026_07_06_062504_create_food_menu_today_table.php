<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_menu_today', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('chef_id');
            $table->unsignedBigInteger('zone_id');

            $table->date('menu_date');
            $table->enum('meal_type', ['breakfast', 'lunch', 'dinner', 'snacks']);

            $table->unsignedBigInteger('food_menu_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('variant_id')->nullable();

            $table->integer('planned_qty')->default(0);
            $table->integer('available_qty')->default(0);

            $table->time('cutoff_time')->nullable();

            $table->enum('broadcast_status', ['not_sent', 'sent'])->default('not_sent');

            $table->enum('status', [
                'draft',
                'broadcasted',
                'closed',
                'cooking',
                'completed',
                'cancelled',
            ])->default('draft');

            $table->text('special_note')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['chef_id', 'menu_date']);
            $table->index(['zone_id', 'menu_date']);
            $table->index('food_menu_id');
            $table->index('meal_type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_menu_today');
    }
};
