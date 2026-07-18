<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE food_preorders
            MODIFY COLUMN status ENUM(
                'interested',
                'confirmed',
                'fulfilled',
                'cancelled',
                'converted_to_order'
            )
            NOT NULL
            DEFAULT 'interested'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE food_preorders
            MODIFY COLUMN status ENUM(
                'interested',
                'confirmed',
                'cancelled',
                'converted_to_order'
            )
            NOT NULL
            DEFAULT 'interested'
        ");
    }
};
