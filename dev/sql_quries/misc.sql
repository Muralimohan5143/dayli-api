## users on their corresponding draft orders and the number of items in those draft orders. This query will help us understand which users are associated with the most draft order items, potentially indicating higher activity or involvement in the drafting process. The results are ordered by the count of items in descending order, allowing us to quickly identify the most active users in terms of draft order item creation.


SELECT scr.for_user_id,doi.draft_order_id,u.display_name,p.title,  count(*) 
FROM dayli.draft_order_items doi inner join draft_orders do on do.id=doi.draft_order_id 
inner join variants v on v.variant_id=doi.variant_id
inner join products p on p.product_id=v.product_id
inner join sub_change_requests scr on do.change_request_id=scr.id
inner join users u on u.id=scr.for_user_id
group by doi.draft_order_id, scr.for_user_id, u.display_name,p.title 

order by 3 desc;
