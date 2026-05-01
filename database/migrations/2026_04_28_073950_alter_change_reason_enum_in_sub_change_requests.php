<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("
            ALTER TABLE sub_change_requests 
            MODIFY change_reason 
            ENUM(
                'self_service',
                'user-error',
                'staff-error',
                'operator_assisted'
            )
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE sub_change_requests 
            MODIFY change_reason 
            ENUM(
                'self_service',
                'user-error',
                'staff-error'
            )
        ");
    }
};
