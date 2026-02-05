<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('draft_order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('original_item_id')->nullable()->after('id');
            $table->string('change_action', 20)->nullable()->after('original_item_id'); // pause | cancel
        });
    }

    public function down(): void
    {
        Schema::table('draft_order_items', function (Blueprint $table) {
            $table->dropColumn('original_item_id');
            $table->dropColumn('change_action');
        });
    }
};
