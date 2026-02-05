<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
DROP VIEW IF EXISTS `user_active_product_subscriptions`;

CREATE ALGORITHM=MERGE SQL SECURITY INVOKER VIEW `user_active_product_subscriptions` AS
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

    -- line item
    doi.id                                   AS draft_order_item_id,
    doi.vendor_id                            AS line_vendor_id,
    vnd.name                                 AS line_vendor_name,
    doi.product_id                           AS product_id,
    p.title                                  AS product_title,
    p.product_type                           AS product_type,
    doi.variant_id                           AS variant_id,
    v.title                                  AS variant_title,
    doi.qty                                  AS qty,
    doi.unit                                 AS unit,
    doi.price_snapshot                       AS price_snapshot

FROM draft_orders do
JOIN sub_change_requests scr
  ON scr.id = do.change_request_id
  AND scr.status IN ('approved','fulfilled')          -- only approved/fulfilled CRs feed active plans
JOIN users u
  ON u.id = do.customer_id
JOIN draft_order_items doi
  ON doi.draft_order_id = do.id
JOIN products p
  ON p.product_id = doi.product_id
JOIN variants v
  ON v.variant_id = doi.variant_id
  AND v.product_id = p.product_id
LEFT JOIN users vnd
  ON vnd.id = doi.vendor_id
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
        DB::unprepared('DROP VIEW IF EXISTS `user_active_product_subscriptions`;');
    }
};
