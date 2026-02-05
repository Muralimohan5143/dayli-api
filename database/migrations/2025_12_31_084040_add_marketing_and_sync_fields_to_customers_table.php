<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $t) {

            // After total_amount_due (exists)
            if (!Schema::hasColumn('customers', 'marketing_campaign_id')) {
                $t->unsignedBigInteger('marketing_campaign_id')->nullable()->after('total_amount_due');
                $t->index('marketing_campaign_id', 'customers_mkt_campaign_idx');
            }

            if (!Schema::hasColumn('customers', 'marketing_executive_id')) {
                $t->unsignedBigInteger('marketing_executive_id')->nullable()->after('marketing_campaign_id');
                $t->index('marketing_executive_id', 'customers_mkt_exec_idx');
            }

            // add FK safely (might fail if users table different engine etc.)
            try {
                $t->foreign('marketing_executive_id', 'customers_mkt_exec_fk')
                    ->references('id')->on('users')
                    ->nullOnDelete();
            } catch (\Throwable $e) {}

            // Put after sync_completed_with (exists)
            if (!Schema::hasColumn('customers', 'clickup_person_id')) {
                $t->string('clickup_person_id', 64)->nullable()->after('sync_completed_with');
            }
            if (!Schema::hasColumn('customers', 'type')) {
                $t->string('type')->nullable()->after('clickup_person_id');
            }
            if (!Schema::hasColumn('customers', 'name_mf')) {
                $t->string('name_mf')->nullable()->after('type');
            }
            if (!Schema::hasColumn('customers', 'phone_mf')) {
                $t->string('phone_mf')->nullable()->after('name_mf');
            }
            if (!Schema::hasColumn('customers', 'area_mf')) {
                $t->string('area_mf')->nullable()->after('phone_mf');
            }
            if (!Schema::hasColumn('customers', 'geolocation')) {
                $t->string('geolocation')->nullable()->after('area_mf');
            }
            if (!Schema::hasColumn('customers', 'last_payment_date')) {
                $t->date('last_payment_date')->nullable()->after('geolocation');
            }

            // After profile_metaobject_gid (exists)
            if (!Schema::hasColumn('customers', 'tasks_head_metaobject_gid')) {
                $t->string('tasks_head_metaobject_gid')->nullable()->after('profile_metaobject_gid');
                $t->index('tasks_head_metaobject_gid', 'customers_tasks_head_gid_idx');
            }

            if (!Schema::hasColumn('customers', 'external_refs')) {
                $t->json('external_refs')->nullable()->after('tasks_head_metaobject_gid');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $t) {

            // drop FK
            try { $t->dropForeign('customers_mkt_exec_fk'); } catch (\Throwable $e) {}

            // drop indexes
            foreach ([
                'customers_mkt_exec_idx',
                'customers_mkt_campaign_idx',
                'customers_tasks_head_gid_idx',
            ] as $idx) {
                try { $t->dropIndex($idx); } catch (\Throwable $e) {}
            }

            foreach ([
                'external_refs',
                'tasks_head_metaobject_gid',
                'last_payment_date',
                'geolocation',
                'area_mf',
                'phone_mf',
                'name_mf',
                'type',
                'clickup_person_id',
                'marketing_executive_id',
                'marketing_campaign_id',
            ] as $col) {
                if (Schema::hasColumn('customers', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
