<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('customers')) {
            return;
        }

        Schema::create('customer_tasks', function (Blueprint $t) {
            $t->id();

            $t->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            $t->foreignId('previous_task_id')->nullable()
                ->constrained('customer_tasks')->nullOnDelete();
            $t->unique('previous_task_id');

            $t->string('shopify_metaobject_gid')->unique();
            $t->string('handle')->nullable()->index();
            $t->timestamp('updated_at_shopify')->nullable();

            $t->date('task_date')->nullable();
            $t->string('task')->nullable();
            $t->string('task_outcome')->nullable();

            $t->text('notes_on_cancellation_postponement')->nullable();
            $t->string('customer_name_task_name')->nullable();

            $t->timestamps();
            $t->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_tasks');
    }
};
