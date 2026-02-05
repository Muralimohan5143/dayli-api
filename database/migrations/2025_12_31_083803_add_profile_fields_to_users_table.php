<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {

            // add name after display_name (safe)
            if (!Schema::hasColumn('users', 'name')) {
                $t->string('name')->nullable()->after('display_name');
            }

            if (!Schema::hasColumn('users', 'state')) {
                $t->string('state')->nullable()->after('currency');
            }

            // DOB parts
            if (!Schema::hasColumn('users', 'day')) {
                $t->string('day')->nullable()->after('password');
            }
            if (!Schema::hasColumn('users', 'month')) {
                $t->string('month')->nullable()->after('day');
            }
            if (!Schema::hasColumn('users', 'year')) {
                $t->string('year')->nullable()->after('month');
            }

            if (!Schema::hasColumn('users', 'language')) {
                $t->string('language')->nullable()->after('skills');
            }

            if (!Schema::hasColumn('users', 'company')) {
                $t->string('company')->nullable()->after('language');
            }
            if (!Schema::hasColumn('users', 'twitter')) {
                $t->string('twitter')->nullable()->after('company');
            }
            if (!Schema::hasColumn('users', 'facebook')) {
                $t->string('facebook')->nullable()->after('twitter');
            }
            if (!Schema::hasColumn('users', 'instagram')) {
                $t->string('instagram')->nullable()->after('facebook');
            }
            if (!Schema::hasColumn('users', 'public_email')) {
                $t->string('public_email')->nullable()->after('instagram');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            foreach (
                [
                    'public_email',
                    'instagram',
                    'facebook',
                    'twitter',
                    'company',
                    'language',
                    'year',
                    'month',
                    'day',
                    'state',
                    'name',
                ] as $col
            ) {
                if (Schema::hasColumn('users', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
