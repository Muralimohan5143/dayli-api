<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // add AFTER meta, BEFORE created_at (MySQL supports after)
            $table->date('actuals_date')->nullable()->after('meta')->index();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['actuals_date']);
            $table->dropColumn('actuals_date');
        });
    }
};
