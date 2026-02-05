<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sub_delivery_actuals', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('sub_change_request_id')->constrained('sub_change_requests')->cascadeOnDelete();

            $table->foreignId('for_user_id')->constrained('users')->cascadeOnDelete();   // customer
            $table->foreignId('by_user_id')->constrained('users')->cascadeOnDelete();    // delivery boy (Manjunath)
            $table->foreignId('from_id')->nullable()->constrained('users')->nullOnDelete(); // vendor/source

            $table->unsignedBigInteger('product_id'); // keep loose if products schema varies
            $table->smallInteger('product_count');

            $table->enum('status', ['pending_approval','approved','rejected'])->default('pending_approval');

            $table->timestamps();

            // indexes for query perf
            $table->index('product_id');
            $table->index(['for_user_id','by_user_id','from_id'], 'sda_actor_idx');

            // If/when products FK is stable, uncomment and adjust:
            // $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_delivery_actuals');
    }
};
