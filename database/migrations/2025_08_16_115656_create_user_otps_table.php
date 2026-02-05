<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_otps', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('user_id');
            $table->string('otp');
            $table->timestamp('expire_at')->nullable();

            $table->timestamps();

            // Foreign key link to users
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');  // When user is deleted, clear OTPs
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_otps');
    }
};
