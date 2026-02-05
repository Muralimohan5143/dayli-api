<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_tasks', function (Blueprint $t) {
            $t->id();

            // local linkage
            $t->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            // chain linkage (linked list)
            $t->foreignId('previous_task_id')->nullable()
                ->constrained('customer_tasks')->nullOnDelete();
            $t->unique('previous_task_id'); // each node can only have one "next"

            // Shopify identity for this metaobject row
            $t->string('shopify_metaobject_gid')->unique(); // gid://shopify/Metaobject/...
            $t->string('handle')->nullable()->index();      // metaobject handle
            $t->timestamp('updated_at_shopify')->nullable();

            // Fields from metaobject
            $t->date('task_date')->nullable(); // from 'date'
            $t->string('task')->nullable();    // enum-ish string: Call for Orders, etc.
            $t->string('task_outcome')->nullable(); // Completed / Postponed / Cancelled / Issue

            $t->text('notes_on_cancellation_postponement')->nullable();

            // pretty display line like "Sri Lakshmi | Call for Payments"
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
