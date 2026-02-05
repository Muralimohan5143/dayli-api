<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            // === PRIMARY KEY (as per dump) ===
            $table->unsignedBigInteger('product_id')->primary();

            // === FIELDS FROM YOUR DUMP (1:1) ===
            $table->string('title');
            $table->string('vendor')->default('Dayli');
            $table->string('product_type')->default('daily-need');
            $table->string('handle')->default('empty-handle');
            $table->string('tags')->default('""');
            $table->string('status')->default('""');
            $table->string('img_src')->default('""');

            // === NEW, SAFE ADDITIONS (OPTIONAL) ===
            // Keep these as plain strings to avoid enum-migration conflicts.
            // You can still validate/enforce via application logic or Shopify metafields.
            //$table->string('product_type')->nullable()->index(); // e.g., "Bakery", "Vegetables", ...
            $table->string('product_sub_type')->nullable()->index();        // e.g., "biscuits_and_chips"

            // === Timestamps ===
            $table->timestamps();

            // === INDEXES from dump ===
            $table->index('handle', 'products_handle_idx');
            $table->index('vendor', 'products_vendor_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};


// use Illuminate\Database\Migrations\Migration;
// use Illuminate\Database\Schema\Blueprint;
// use Illuminate\Support\Facades\Schema;

// return new class extends Migration {
//     public function up(): void
//     {
//         Schema::create('products', function (Blueprint $table) {
//             // PK matches dump: bigint unsigned, not auto-increment in dump
//             $table->unsignedBigInteger('product_id')->primary();

//             $table->string('title');
//             $table->string('vendor')->default('Dayli');
//             $table->string('product_type')->default('daily-need');
//             $table->string('handle')->default('empty-handle');
//             $table->string('tags')->default('""');
//             $table->string('status')->default('""');
//             $table->string('img_src')->default('""');

//             $table->timestamps();

//             // indexes as per dump
//             $table->index('handle', 'products_handle_idx');
//             $table->index('vendor', 'products_vendor_idx');
//         });
//     }

//     public function down(): void
//     {
//         Schema::dropIfExists('products');
//     }
// };
