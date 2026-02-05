<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('addresses');
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();

            // Polymorphic relation: addressable_type + addressable_id
            $table->string('addressable_type');
            $table->unsignedBigInteger('addressable_id');

            // Optional zone relation
            $table->unsignedBigInteger('zone_id')->nullable();

            // Address fields
            $table->string('line1')->nullable();
            $table->string('line2')->nullable();
            $table->string('nagar')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('pincode', 20)->nullable();

            // Coordinates
            $table->decimal('lat', 10, 6)->nullable();
            $table->decimal('lng', 10, 6)->nullable();

            // Flags
            $table->boolean('is_default')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['addressable_type', 'addressable_id']);
            $table->index(['zone_id', 'pincode']);

            // Foreign key
            $table->foreign('zone_id')
                ->references('id')
                ->on('zones')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
