SELECT distinct financial_status,financial_status_label,cancelled,cancelled_at,cancel_reason,total_refunded_amount,total_net_amount,subtotal,discount,total FROM dayli.orders;
SELECT distinct financial_status, count(financial_status) FROM dayli.orders group by financial_status;
ghjghjghj


