<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_regimens', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');

            $table->string('day_of_week');
            // monday, tuesday, wednesday...

            $table->string('meal_type');
            // breakfast, lunch, dinner

            $table->text('preference')->nullable();
            // example: less spicy, no onion, diabetic friendly

            $table->text('notes')->nullable();
            // example: extra dal, avoid rice, chapati only

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_regimens');
    }
};
