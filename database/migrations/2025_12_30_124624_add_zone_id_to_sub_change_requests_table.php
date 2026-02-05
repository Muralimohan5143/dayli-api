<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_change_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('zone_id')
                ->nullable()
                ->after('draft_order_id'); // 👈 EXACT position

            // optional but recommended index
            $table->index('zone_id', 'scr_zone_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('sub_change_requests', function (Blueprint $table) {
            $table->dropIndex('scr_zone_id_idx');
            $table->dropColumn('zone_id');
        });
    }
};
