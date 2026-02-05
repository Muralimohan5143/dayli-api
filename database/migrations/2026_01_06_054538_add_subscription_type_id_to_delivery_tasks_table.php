<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('delivery_tasks', function (Blueprint $table) {
            // ✅ between zone_id and status (MySQL supports after())
            $table->unsignedBigInteger('subscription_type_id')
                ->nullable()
                ->after('zone_id');

            $table->index('subscription_type_id', 'delivery_tasks_subscription_type_id_index');

            // ✅ FK (if subscription_types table exists)
            $table->foreign('subscription_type_id', 'delivery_tasks_subscription_type_id_foreign')
                ->references('id')
                ->on('subscription_types')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_tasks', function (Blueprint $table) {
            // drop FK first, then index, then column
            $table->dropForeign('delivery_tasks_subscription_type_id_foreign');
            $table->dropIndex('delivery_tasks_subscription_type_id_index');
            $table->dropColumn('subscription_type_id');
        });
    }
};
