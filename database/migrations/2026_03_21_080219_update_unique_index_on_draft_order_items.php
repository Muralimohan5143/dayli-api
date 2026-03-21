<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('draft_order_items', function (Blueprint $table) {
            // Create a normal non-unique index first, so FK can still use it
            $table->index(
                ['draft_order_id', 'variant_id', 'vendor_id'],
                'draft_items_tpl_idx'
            );
        });

        Schema::table('draft_order_items', function (Blueprint $table) {
            // Now old unique can be dropped safely
            $table->dropUnique('draft_items_tpl_uidx');

            // New unique timeline index
            $table->unique(
                ['draft_order_id', 'variant_id', 'vendor_id', 'start_date'],
                'draft_items_timeline_uidx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('draft_order_items', function (Blueprint $table) {
            $table->dropUnique('draft_items_timeline_uidx');

            $table->unique(
                ['draft_order_id', 'variant_id', 'vendor_id'],
                'draft_items_tpl_uidx'
            );
        });

        Schema::table('draft_order_items', function (Blueprint $table) {
            $table->dropIndex('draft_items_tpl_idx');
        });
    }
};
