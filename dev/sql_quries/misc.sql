## users on their corresponding draft orders and the number of items in those draft orders. This query will help us understand which users are associated with the most draft order items, potentially indicating higher activity or involvement in the drafting process. The results are ordered by the count of items in descending order, allowing us to quickly identify the most active users in terms of draft order item creation.


SELECT scr.for_user_id,doi.draft_order_id,u.display_name,p.title,  count(*) 
FROM dayli.draft_order_items doi inner join draft_orders do on do.id=doi.draft_order_id 
inner join variants v on v.variant_id=doi.variant_id
inner join products p on p.product_id=v.product_id
inner join sub_change_requests scr on do.change_request_id=scr.id
inner join users u on u.id=scr.for_user_id
group by doi.draft_order_id, scr.for_user_id, u.display_name,p.title 
order by 3 desc;


SELECT
    scr.for_user_id,
    doi.draft_order_id,
    u.display_name,
    CASE
        WHEN v.title IS NULL OR v.title = '' OR v.title = 'Default Title'
            THEN p.title
        ELSE v.title
    END AS item_name,
    COUNT(*) AS total
FROM dayli.draft_order_items doi
INNER JOIN draft_orders do
    ON do.id = doi.draft_order_id
INNER JOIN variants v
    ON v.variant_id = doi.variant_id
INNER JOIN products p
    ON p.product_id = v.product_id
INNER JOIN sub_change_requests scr
    ON do.change_request_id = scr.id
INNER JOIN users u
    ON u.id = scr.for_user_id
GROUP BY
    scr.for_user_id,
    doi.draft_order_id,
    u.display_name,
    doi.variant_id,
    v.title,
    p.title
ORDER BY u.display_name DESC;


SELECT -- *
distinct subscription_type_id,users.id,users.zone_id
 FROM dayli.model_has_roles
inner join users on users.id=model_has_roles.model_id 
inner join sub_change_requests scr on scr.zone_id=users.zone_id 
where role_id=4;




// This query retrieves the order ID, delivery date, and the count of order items for each order that meets specific criteria. It filters out orders from customers with IDs 11345 and 11346, focuses on orders with a delivery date of '2026-04-11', and excludes orders with a delivery status of 'pending'. The results are grouped by order ID and delivery date, allowing us to see the number of items associated with each relevant order on that specific date. This information can be useful for analyzing order activity and understanding customer behavior on that day.
SELECT o.id, o.delivery_date, count(oi.id) FROM dayli.orders o
left outer join dayli.order_items oi on oi.order_id = o.id
where o.customer_id not in (11345,  11346) and o.delivery_date = '2026-04-11'
and o.delivery_status !='pending'
group by o.id, o.delivery_date; 



