<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
      public function up(): void
      {
            Schema::create('orders', function (Blueprint $table) {
                  $table->id();

                  // 🔥 CHANGED HERE: customer_id now points to dayli_customers
                  $table->foreignId('customer_id')
                        ->constrained('users')
                        ->cascadeOnDelete();

                  // keep vendor_id as users
                  $table->foreignId('vendor_id')
                        ->nullable()
                        ->constrained('users')
                        ->nullOnDelete();

                  $table->foreignId('zone_id')
                        ->nullable()
                        ->constrained('zones')
                        ->nullOnDelete();

                  // link to the contract (draft order) this order is based on
                  $table->foreignId('draft_order_id')
                        ->nullable()
                        ->constrained('draft_orders')
                        ->nullOnDelete();

                  $table->string('number')->nullable()->unique();
                  $table->enum('status', ['draft', 'pending', 'confirmed', 'fulfilled', 'cancelled'])
                        ->default('draft');

                  $table->decimal('subtotal', 10, 2)->default(0);
                  $table->decimal('tax', 10, 2)->default(0);
                  $table->decimal('discount', 10, 2)->default(0);
                  $table->decimal('total', 10, 2)->default(0);

                  $table->json('meta')->nullable();

                  $table->timestamps();

                  $table->index(['customer_id', 'vendor_id', 'status'], 'orders_customer_vendor_status_idx');
            });
      }

      public function down(): void
      {
            Schema::dropIfExists('orders');
      }
};
