<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // rename table customers → users
        Schema::rename('customers', 'users');
    }

    public function down(): void
    {
        // rollback: rename users back to customers
        Schema::rename('users', 'customers');
    }
};
