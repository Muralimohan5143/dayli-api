SELECT * FROM dayli.users;


CREATE TABLE customers_all AS
SELECT * FROM customers;

INSERT INTO customers_all
SELECT * FROM customers;