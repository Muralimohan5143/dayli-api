<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('draft_order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('draft_order_items', 'status')) {
                $table->string('status', 20)
                    ->default('active')
                    ->after('end_date');
            }

            if (!Schema::hasColumn('draft_order_items', 'supersedes_doi_id')) {
                $table->unsignedBigInteger('supersedes_doi_id')
                    ->nullable()
                    ->after('status');
            }

            if (!Schema::hasColumn('draft_order_items', 'created_from_action')) {
                $table->string('created_from_action', 30)
                    ->nullable()
                    ->after('supersedes_doi_id');
            }

            if (!Schema::hasColumn('draft_order_items', 'closed_by_action')) {
                $table->string('closed_by_action', 30)
                    ->nullable()
                    ->after('created_from_action');
            }
        });

        Schema::table('draft_order_items', function (Blueprint $table) {
            try {
                $table->index(['draft_order_id', 'status'], 'doi_draft_status_idx');
            } catch (\Throwable $e) {
            }

            try {
                $table->index('start_date', 'doi_start_date_idx');
            } catch (\Throwable $e) {
            }

            try {
                $table->index('end_date', 'doi_end_date_idx');
            } catch (\Throwable $e) {
            }

            try {
                $table->index('supersedes_doi_id', 'doi_supersedes_idx');
            } catch (\Throwable $e) {
            }
        });

        Schema::table('draft_order_items', function (Blueprint $table) {
            try {
                $table->foreign('supersedes_doi_id', 'doi_supersedes_fk')
                    ->references('id')
                    ->on('draft_order_items')
                    ->nullOnDelete();
            } catch (\Throwable $e) {
            }
        });

        DB::table('draft_order_items')
            ->whereNull('status')
            ->update(['status' => 'active']);
    }

    public function down(): void
    {
        Schema::table('draft_order_items', function (Blueprint $table) {
            try {
                $table->dropForeign('doi_supersedes_fk');
            } catch (\Throwable $e) {
            }

            try {
                $table->dropIndex('doi_draft_status_idx');
            } catch (\Throwable $e) {
            }

            try {
                $table->dropIndex('doi_start_date_idx');
            } catch (\Throwable $e) {
            }

            try {
                $table->dropIndex('doi_end_date_idx');
            } catch (\Throwable $e) {
            }

            try {
                $table->dropIndex('doi_supersedes_idx');
            } catch (\Throwable $e) {
            }

            if (Schema::hasColumn('draft_order_items', 'closed_by_action')) {
                $table->dropColumn('closed_by_action');
            }

            if (Schema::hasColumn('draft_order_items', 'created_from_action')) {
                $table->dropColumn('created_from_action');
            }

            if (Schema::hasColumn('draft_order_items', 'supersedes_doi_id')) {
                $table->dropColumn('supersedes_doi_id');
            }

            if (Schema::hasColumn('draft_order_items', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
