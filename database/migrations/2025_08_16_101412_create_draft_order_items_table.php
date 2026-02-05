<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('draft_order_items', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('draft_order_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id');
            $table->unsignedBigInteger('vendor_id')->nullable();

            // ⬇️ place it here (instead of using ->after())
            $table->enum('frequency_type', [
                'daily','alternate_days','weekdays','weekends','sat','sun','custom','on_demand'
            ])->nullable();

            $table->decimal('qty', 8, 2)->default(1.00);
            $table->string('unit', 16)->default('pcs');
            $table->decimal('price_snapshot', 10, 2)->nullable();

            // ⬇️ placed right after price_snapshot
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->json('meta')->nullable();
            $table->timestamps();

            // constraints & indexes
            $table->unique(['draft_order_id', 'variant_id', 'vendor_id'], 'draft_items_tpl_uidx');
            $table->index(['product_id', 'variant_id'], 'draft_items_variant_idx');
            $table->index('frequency_type');
            $table->index('start_date');
            $table->index('end_date');

            $table->foreign('draft_order_id')
                  ->references('id')->on('draft_orders')
                  ->cascadeOnDelete();

            $table->foreign('product_id')
                  ->references('product_id')->on('products')
                  ->restrictOnDelete()
                  ->cascadeOnUpdate();

            $table->foreign('variant_id')
                  ->references('variant_id')->on('variants')
                  ->restrictOnDelete()
                  ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draft_order_items');
    }
};
