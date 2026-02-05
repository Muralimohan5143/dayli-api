<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        /**
         * 1) Service line items for draft orders
         *    (parallel to draft_order_items but for services)
         */
        if (!Schema::hasTable('draft_service_order_items')) {
            Schema::create('draft_service_order_items', function (Blueprint $table) {
                $table->bigIncrements('id');

                $table->unsignedBigInteger('draft_order_id');
                $table->unsignedBigInteger('service_id');
                $table->unsignedBigInteger('variant_id');
                $table->unsignedBigInteger('vendor_id')->nullable();

                $table->decimal('qty', 8, 2)->default(1.00);
                $table->string('unit', 16)->default('svc');
                $table->decimal('price_snapshot', 10, 2)->nullable();

                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique(['draft_order_id', 'variant_id', 'vendor_id'], 'draft_svc_items_tpl_uidx');

                $table->foreign('draft_order_id')
                      ->references('id')->on('draft_orders')
                      ->onDelete('cascade');

                $table->foreign('service_id')
                      ->references('service_id')->on('services')
                      ->onDelete('restrict')->onUpdate('cascade');

                $table->foreign('variant_id')
                      ->references('variant_id')->on('service_variants')
                      ->onDelete('restrict')->onUpdate('cascade');

                $table->foreign('vendor_id')
                      ->references('id')->on('users')
                      ->onDelete('set null');
            });
        }

        /**
         * 2) View of *active* service subscriptions
         */
        DB::unprepared(<<<'SQL'
DROP VIEW IF EXISTS `user_active_service_subscriptions`;

CREATE ALGORITHM=MERGE SQL SECURITY INVOKER VIEW `user_active_service_subscriptions` AS
SELECT
    -- who
    do.customer_id                           AS user_id,
    u.name                                   AS customer_name,

    -- contract context
    scr.id                                   AS change_request_id,
    scr.status                               AS change_request_status,
    scr.zone_id                              AS zone_id,
    z.name                                   AS zone_name,
    scr.subscription_type_id                 AS subscription_type_id,
    st.name                                  AS subscription_type,
    scr.vendor_id                            AS requested_vendor_id,

    -- draft order scope
    do.id                                    AS draft_order_id,
    do.cadence                               AS cadence,
    do.start_date                            AS start_date,
    do.end_date                              AS end_date,
    do.status                                AS draft_status,
    do.timezone                              AS timezone,

    -- line item (service)
    dsoi.id                                  AS draft_service_item_id,
    dsoi.vendor_id                           AS line_vendor_id,
    vnd.name                                 AS line_vendor_name,
    dsoi.service_id                          AS service_id,
    s.title                                  AS service_title,
    s.service_type                           AS service_type,
    dsoi.variant_id                          AS service_variant_id,
    sv.title                                 AS service_variant_title,
    sv.duration_minutes                      AS duration_minutes,
    dsoi.qty                                 AS qty,
    dsoi.unit                                AS unit,
    dsoi.price_snapshot                      AS price_snapshot

FROM draft_orders do
JOIN sub_change_requests scr
  ON scr.id = do.change_request_id
  AND scr.status IN ('approved','fulfilled')
JOIN users u
  ON u.id = do.customer_id
JOIN draft_service_order_items dsoi
  ON dsoi.draft_order_id = do.id
JOIN services s
  ON s.service_id = dsoi.service_id
JOIN service_variants sv
  ON sv.variant_id = dsoi.variant_id
  AND sv.service_id = s.service_id
LEFT JOIN users vnd
  ON vnd.id = dsoi.vendor_id
LEFT JOIN zones z
  ON z.id = scr.zone_id
LEFT JOIN subscription_types st
  ON st.id = scr.subscription_type_id
WHERE do.status = 'active'
  AND do.start_date <= CURRENT_DATE()
  AND (do.end_date IS NULL OR do.end_date >= CURRENT_DATE());
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP VIEW IF EXISTS `user_active_service_subscriptions`;');
        Schema::dropIfExists('draft_service_order_items');
    }
};
