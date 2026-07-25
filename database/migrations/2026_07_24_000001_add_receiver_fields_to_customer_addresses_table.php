<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->string('label', 50)
                ->nullable()
                ->after('zone_id');

            $table->string('receiver_name', 150)
                ->nullable()
                ->after('label');

            $table->string('receiver_phone', 20)
                ->nullable()
                ->after('receiver_name');
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn([
                'label',
                'receiver_name',
                'receiver_phone',
            ]);
        });
    }
};
