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
