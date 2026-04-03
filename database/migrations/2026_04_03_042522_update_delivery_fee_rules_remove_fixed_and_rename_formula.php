<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_fee_rules', function (Blueprint $table) {
            // 1. drop fixed_fee
            if (Schema::hasColumn('delivery_fee_rules', 'fixed_fee')) {
                $table->dropColumn('fixed_fee');
            }
        });

        Schema::table('delivery_fee_rules', function (Blueprint $table) {
            // 2. rename formula_fee → fee_formula
            if (Schema::hasColumn('delivery_fee_rules', 'formula_fee')) {
                $table->renameColumn('formula_fee', 'fee_formula');
            }
        });
    }

    public function down(): void
    {
        Schema::table('delivery_fee_rules', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_fee_rules', 'fee_formula')) {
                $table->renameColumn('fee_formula', 'formula_fee');
            }
        });

        Schema::table('delivery_fee_rules', function (Blueprint $table) {
            if (!Schema::hasColumn('delivery_fee_rules', 'fixed_fee')) {
                $table->decimal('fixed_fee', 10, 2)->nullable();
            }
        });
    }
};
