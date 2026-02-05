<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // add column
            $table->unsignedBigInteger('user_id')
                ->nullable()
                ->after('order_id');

            // add FK
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // drop FK first
            $table->dropForeign(['user_id']);

            // drop column
            $table->dropColumn('user_id');
        });
    }
};
