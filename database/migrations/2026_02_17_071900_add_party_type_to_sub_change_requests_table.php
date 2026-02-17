<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_change_requests', function (Blueprint $table) {
            // consumer = normal customer subscriptions (delivery boy should see)
            // supplier = vendor self subscriptions (delivery boy should NOT see)
            $table->enum('party_type', ['consumer', 'supplier'])
                ->default('consumer')
                ->after('by_user_id')
                ->index();
        });

        // ✅ Backfill existing data:
        // If for_user_id == by_user_id, treat as supplier (self-service / vendor style)
        // Else consumer
        DB::statement("
            UPDATE sub_change_requests
            SET party_type = CASE
                WHEN for_user_id = by_user_id THEN 'supplier'
                ELSE 'consumer'
            END
            WHERE party_type IS NULL OR party_type = ''
        ");
    }

    public function down(): void
    {
        Schema::table('sub_change_requests', function (Blueprint $table) {
            $table->dropIndex(['party_type']);
            $table->dropColumn('party_type');
        });
    }
};
