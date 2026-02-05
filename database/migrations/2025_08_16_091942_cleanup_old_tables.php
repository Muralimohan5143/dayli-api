<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // drop only if they exist; ignore errors
        foreach (['categories','items','tags','item_tags','password_resets'] as $tbl) {
            if (Schema::hasTable($tbl)) {
                Schema::drop($tbl);
            }
        }
    }

    public function down(): void
    {
        // no-op (we don't want to recreate unwanted tables)
    }
};
