<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sub_change_requests', function (Blueprint $t) {
            $t->bigIncrements('id');

            // Actors
            $t->unsignedBigInteger('for_user_id');
            $t->unsignedBigInteger('by_user_id');

            // Version chain
            $t->unsignedBigInteger('from_id')->nullable();

            // Linkage
            $t->unsignedBigInteger('draft_order_id')->nullable();

            // Type & subtypes
            $t->unsignedBigInteger('subscription_type_id')->nullable();
            $t->json('subtypes_json')->nullable();

            // ⚙️ Removed frequency_type, start_date, end_date (moved to draft_order_items)
            $t->text('custom_frequency_format')->nullable();
            $t->enum('invoice_cycle', ['monthly','weekly','custom'])
                ->default('monthly')
                ->index();

            // Reason
            $t->enum('change_reason', ['self_service','user-error','staff-error']);

            // Action + status + approvals
            $t->enum('action', ['create','modify','pause','resume','cancel'])->default('create');
            $t->enum('status', ['pending','approved','rejected'])->default('pending');
            $t->unsignedBigInteger('approved_by')->nullable();
            $t->timestamp('approved_at')->nullable();

            // Misc
            $t->unsignedTinyInteger('priority')->default(3);
            $t->json('payload')->nullable();
            $t->json('meta')->nullable();

            $t->timestamps();

            // FKs
            $t->foreign('for_user_id')->references('id')->on('users')->cascadeOnDelete();
            $t->foreign('by_user_id')->references('id')->on('users')->cascadeOnDelete();
            $t->foreign('from_id')->references('id')->on('sub_change_requests')->nullOnDelete();
            $t->foreign('approved_by')->references('id')->on('users')->nullOnDelete();

            // Indexes
            $t->index(['status'], 'scr_status_idx');
            $t->index(['for_user_id','status'], 'scr_for_status_idx');
            $t->index(['by_user_id','status'], 'scr_by_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_change_requests');
    }
};
