<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // 🔴 ADD Previous Dues column AFTER subtotal
            $table->decimal('Unpaid_dues', 10, 2)
                ->default(0.00)
                ->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('Unpaid_dues');
        });
    }
};
