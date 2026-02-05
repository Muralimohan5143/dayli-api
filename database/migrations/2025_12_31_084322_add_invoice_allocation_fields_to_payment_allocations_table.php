<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function fkExists(string $table, string $fkName): bool
    {
        $db = DB::getDatabaseName();

        return DB::table('information_schema.REFERENTIAL_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $db)
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $fkName)
            ->exists();
    }

    public function up(): void
    {
        Schema::table('payment_allocations', function (Blueprint $t) {
            if (!Schema::hasColumn('payment_allocations', 'inward_payment_id')) {
                $t->unsignedBigInteger('inward_payment_id')->nullable()->after('payment_id');
                $t->index('inward_payment_id', 'payment_alloc_inward_payment_idx');
            }

            if (!Schema::hasColumn('payment_allocations', 'invoice_id')) {
                $t->unsignedBigInteger('invoice_id')->nullable()->after('inward_payment_id');
                $t->index('invoice_id', 'payment_alloc_invoice_idx');
            }

            if (!Schema::hasColumn('payment_allocations', 'allocated_amount')) {
                $t->decimal('allocated_amount', 12, 2)->default(0)->after('amount_applied');
            }

            if (!Schema::hasColumn('payment_allocations', 'is_final_allocation')) {
                $t->boolean('is_final_allocation')->default(false)->after('allocated_amount');
            }

            if (!Schema::hasColumn('payment_allocations', 'note')) {
                $t->text('note')->nullable()->after('is_final_allocation');
            }

            // NOTE: do NOT add FK inside Blueprint if we want to check existence cleanly
            // We'll add after this Schema::table using DB::statement guarded.
        });

        // Add FKs safely (unique names)
        $table = 'payment_allocations';

        // FK to invoices
        $fkInvoice = 'pa_invoice_id_fk_20251231';
        if (!$this->fkExists($table, $fkInvoice)) {
            try {
                DB::statement("
                    ALTER TABLE `payment_allocations`
                    ADD CONSTRAINT `$fkInvoice`
                    FOREIGN KEY (`invoice_id`)
                    REFERENCES `invoices` (`id`)
                    ON DELETE CASCADE
                ");
            } catch (\Throwable $e) {
                // ignore if invoices table doesn't exist or FK already exists with different name
            }
        }

        // FK to inward_payments (only if table exists)
        $fkInward = 'pa_inward_payment_id_fk_20251231';
        if (Schema::hasTable('inward_payments') && !$this->fkExists($table, $fkInward)) {
            try {
                DB::statement("
                    ALTER TABLE `payment_allocations`
                    ADD CONSTRAINT `$fkInward`
                    FOREIGN KEY (`inward_payment_id`)
                    REFERENCES `inward_payments` (`id`)
                    ON DELETE CASCADE
                ");
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // Unique/index (safe try)
        Schema::table('payment_allocations', function (Blueprint $t) {
            try {
                $t->unique(['inward_payment_id', 'invoice_id'], 'payment_alloc_inward_invoice_unique');
            } catch (\Throwable $e) {
            }
            try {
                $t->index(['invoice_id', 'inward_payment_id'], 'payment_alloc_invoice_inward_idx');
            } catch (\Throwable $e) {
            }
        });
    }

    public function down(): void
    {
        // Drop FKs safely by name (only if they exist)
        $table = 'payment_allocations';

        foreach (['pa_invoice_id_fk_20251231', 'pa_inward_payment_id_fk_20251231'] as $fk) {
            try {
                DB::statement("ALTER TABLE `payment_allocations` DROP FOREIGN KEY `$fk`");
            } catch (\Throwable $e) {
            }
        }

        Schema::table('payment_allocations', function (Blueprint $t) {
            try {
                $t->dropUnique('payment_alloc_inward_invoice_unique');
            } catch (\Throwable $e) {
            }
            try {
                $t->dropIndex('payment_alloc_invoice_inward_idx');
            } catch (\Throwable $e) {
            }
            try {
                $t->dropIndex('payment_alloc_invoice_idx');
            } catch (\Throwable $e) {
            }
            try {
                $t->dropIndex('payment_alloc_inward_payment_idx');
            } catch (\Throwable $e) {
            }

            foreach (['note', 'is_final_allocation', 'allocated_amount', 'invoice_id', 'inward_payment_id'] as $col) {
                if (Schema::hasColumn('payment_allocations', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
