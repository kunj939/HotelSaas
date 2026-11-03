-- Kaku 2.0 Pizza & Restaurant: full menu, copied from their printed menu card.
-- 1. Super Admin → Add restaurant with URL slug  kaku-2-0  (this creates the owner login).
-- 2. phpMyAdmin → select the database → SQL tab → paste this whole file → Go.
-- Running it again REPLACES this restaurant's whole menu (old orders keep their dish names).
-- If you see "Column 'restaurant_id' cannot be null", step 1 was not done or the slug is different.
SET @rid := (SELECT id FROM restaurants WHERE slug='kaku-2-0');

UPDATE restaurants SET name='Kaku 2.0 Pizza & Restaurant',tagline='Same Taste, New Vibe',
 welcome_message='Same taste, new vibe! Free home delivery within 2 km on orders above ₹350.',
 phone='90535-73091',address='U6/01, U Block, DLF Phase-III, Gurugram, Haryana 122002',
 primary_color='#F5C211',accent_color='#E32B2B',theme_mode='dark',menu_layout='list'
WHERE id=@rid;

DELETE FROM menu_items WHERE restaurant_id=@rid;
DELETE FROM categories WHERE restaurant_id=@rid;

INSERT INTO categories(restaurant_id,name,sort_order) VALUES
 (@rid,'Indian Curry''s',0),
 (@rid,'Paneer Item''s',1),
 (@rid,'Kofta',2),
 (@rid,'Mushroom',3),
 (@rid,'Soya Chaap',4),
 (@rid,'Aloo Item''s',5),
 (@rid,'Dal Items',6),
 (@rid,'Rice Items',7),
 (@rid,'Chapati',8),
 (@rid,'Parathe',9),
 (@rid,'Thali',10),
 (@rid,'Rice Combo',11),
 (@rid,'Milkshakes',12),
 (@rid,'Coffee & Tea',13),
 (@rid,'Sweets',14),
 (@rid,'Dahi & Lassi',15);

INSERT INTO menu_items(restaurant_id,category_id,name,description,food_type,base_price,options_json,addons_json,sort_order)
SELECT @rid,c.id,m.name,m.description,'veg',m.base_price,m.options_json,'[]',m.sort_order
FROM (
 SELECT 'Channa Masala' AS name,NULL AS description,130 AS base_price,'[{"name": "Half", "price": 130}, {"name": "Full", "price": 200}]' AS options_json,0 AS sort_order,'Indian Curry''s' AS category
 UNION ALL SELECT 'Chole' AS name,NULL AS description,110 AS base_price,'[{"name": "Half", "price": 110}, {"name": "Full", "price": 170}]' AS options_json,1 AS sort_order,'Indian Curry''s' AS category
 UNION ALL SELECT 'Rajma Masala' AS name,NULL AS description,110 AS base_price,'[{"name": "Half", "price": 110}, {"name": "Full", "price": 170}]' AS options_json,2 AS sort_order,'Indian Curry''s' AS category
 UNION ALL SELECT 'Bhindi Masala' AS name,NULL AS description,120 AS base_price,'[{"name": "Half", "price": 120}, {"name": "Full", "price": 180}]' AS options_json,3 AS sort_order,'Indian Curry''s' AS category
 UNION ALL SELECT 'Gobi Masala' AS name,NULL AS description,140 AS base_price,'[{"name": "Half", "price": 140}, {"name": "Full", "price": 220}]' AS options_json,4 AS sort_order,'Indian Curry''s' AS category
 UNION ALL SELECT 'Gobi Matar' AS name,NULL AS description,140 AS base_price,'[{"name": "Half", "price": 140}, {"name": "Full", "price": 220}]' AS options_json,5 AS sort_order,'Indian Curry''s' AS category
 UNION ALL SELECT 'Mix Veg' AS name,NULL AS description,140 AS base_price,'[{"name": "Half", "price": 140}, {"name": "Full", "price": 220}]' AS options_json,6 AS sort_order,'Indian Curry''s' AS category
 UNION ALL SELECT 'Pindi Chana' AS name,NULL AS description,140 AS base_price,'[{"name": "Half", "price": 140}, {"name": "Full", "price": 220}]' AS options_json,7 AS sort_order,'Indian Curry''s' AS category
 UNION ALL SELECT 'Kadi' AS name,NULL AS description,100 AS base_price,'[{"name": "Half", "price": 100}, {"name": "Full", "price": 170}]' AS options_json,8 AS sort_order,'Indian Curry''s' AS category
 UNION ALL SELECT 'Kadi Pakoda' AS name,NULL AS description,110 AS base_price,'[{"name": "Half", "price": 110}, {"name": "Full", "price": 180}]' AS options_json,9 AS sort_order,'Indian Curry''s' AS category
 UNION ALL SELECT 'Sev Bhaji' AS name,NULL AS description,130 AS base_price,'[{"name": "Half", "price": 130}, {"name": "Full", "price": 200}]' AS options_json,10 AS sort_order,'Indian Curry''s' AS category
 UNION ALL SELECT 'Sev Tamatar' AS name,NULL AS description,130 AS base_price,'[{"name": "Half", "price": 130}, {"name": "Full", "price": 200}]' AS options_json,11 AS sort_order,'Indian Curry''s' AS category
 UNION ALL SELECT 'Began Bharta' AS name,NULL AS description,130 AS base_price,'[{"name": "Half", "price": 130}, {"name": "Full", "price": 200}]' AS options_json,12 AS sort_order,'Indian Curry''s' AS category
 UNION ALL SELECT 'Matar Paneer' AS name,NULL AS description,140 AS base_price,'[{"name": "Half", "price": 140}, {"name": "Full", "price": 210}]' AS options_json,0 AS sort_order,'Paneer Item''s' AS category
 UNION ALL SELECT 'Shahi Paneer' AS name,NULL AS description,140 AS base_price,'[{"name": "Half", "price": 140}, {"name": "Full", "price": 210}]' AS options_json,1 AS sort_order,'Paneer Item''s' AS category
 UNION ALL SELECT 'Kadai Paneer' AS name,NULL AS description,150 AS base_price,'[{"name": "Half", "price": 150}, {"name": "Full", "price": 230}]' AS options_json,2 AS sort_order,'Paneer Item''s' AS category
 UNION ALL SELECT 'Paneer Do Pyaja' AS name,NULL AS description,150 AS base_price,'[{"name": "Half", "price": 150}, {"name": "Full", "price": 230}]' AS options_json,3 AS sort_order,'Paneer Item''s' AS category
 UNION ALL SELECT 'Palak Paneer' AS name,NULL AS description,150 AS base_price,'[{"name": "Half", "price": 150}, {"name": "Full", "price": 230}]' AS options_json,4 AS sort_order,'Paneer Item''s' AS category
 UNION ALL SELECT 'Chana Paneer' AS name,NULL AS description,150 AS base_price,'[{"name": "Half", "price": 150}, {"name": "Full", "price": 230}]' AS options_json,5 AS sort_order,'Paneer Item''s' AS category
 UNION ALL SELECT 'Paneer Lababdar' AS name,NULL AS description,160 AS base_price,'[{"name": "Half", "price": 160}, {"name": "Full", "price": 240}]' AS options_json,6 AS sort_order,'Paneer Item''s' AS category
 UNION ALL SELECT 'Paneer Bhujji' AS name,NULL AS description,160 AS base_price,'[{"name": "Half", "price": 160}, {"name": "Full", "price": 240}]' AS options_json,7 AS sort_order,'Paneer Item''s' AS category
 UNION ALL SELECT 'Paneer Malai' AS name,NULL AS description,160 AS base_price,'[{"name": "Half", "price": 160}, {"name": "Full", "price": 240}]' AS options_json,8 AS sort_order,'Paneer Item''s' AS category
 UNION ALL SELECT 'Paneer Butter Masala' AS name,NULL AS description,170 AS base_price,'[{"name": "Half", "price": 170}, {"name": "Full", "price": 250}]' AS options_json,9 AS sort_order,'Paneer Item''s' AS category
 UNION ALL SELECT 'Paneer Malai Methi' AS name,NULL AS description,170 AS base_price,'[{"name": "Half", "price": 170}, {"name": "Full", "price": 250}]' AS options_json,10 AS sort_order,'Paneer Item''s' AS category
 UNION ALL SELECT 'Paneer Makhmali' AS name,NULL AS description,170 AS base_price,'[{"name": "Half", "price": 170}, {"name": "Full", "price": 250}]' AS options_json,11 AS sort_order,'Paneer Item''s' AS category
 UNION ALL SELECT 'Malai Kofta' AS name,NULL AS description,160 AS base_price,'[{"name": "Half", "price": 160}, {"name": "Full", "price": 230}]' AS options_json,0 AS sort_order,'Kofta' AS category
 UNION ALL SELECT 'Angoori Kofta' AS name,NULL AS description,170 AS base_price,'[{"name": "Half", "price": 170}, {"name": "Full", "price": 250}]' AS options_json,1 AS sort_order,'Kofta' AS category
 UNION ALL SELECT 'Palak Kofta' AS name,NULL AS description,160 AS base_price,'[{"name": "Half", "price": 160}, {"name": "Full", "price": 230}]' AS options_json,2 AS sort_order,'Kofta' AS category
 UNION ALL SELECT 'Matar Mushroom' AS name,NULL AS description,150 AS base_price,'[{"name": "Half", "price": 150}, {"name": "Full", "price": 220}]' AS options_json,0 AS sort_order,'Mushroom' AS category
 UNION ALL SELECT 'Kadai Mushroom' AS name,NULL AS description,170 AS base_price,'[{"name": "Half", "price": 170}, {"name": "Full", "price": 240}]' AS options_json,1 AS sort_order,'Mushroom' AS category
 UNION ALL SELECT 'Mushroom Masala' AS name,NULL AS description,180 AS base_price,'[{"name": "Half", "price": 180}, {"name": "Full", "price": 230}]' AS options_json,2 AS sort_order,'Mushroom' AS category
 UNION ALL SELECT 'Chaap Gravy' AS name,NULL AS description,140 AS base_price,'[{"name": "Half", "price": 140}, {"name": "Full", "price": 200}]' AS options_json,0 AS sort_order,'Soya Chaap' AS category
 UNION ALL SELECT 'Kadi Chaap' AS name,NULL AS description,150 AS base_price,'[{"name": "Half", "price": 150}, {"name": "Full", "price": 210}]' AS options_json,1 AS sort_order,'Soya Chaap' AS category
 UNION ALL SELECT 'Aloo Jeera' AS name,NULL AS description,110 AS base_price,'[{"name": "Half", "price": 110}, {"name": "Full", "price": 170}]' AS options_json,0 AS sort_order,'Aloo Item''s' AS category
 UNION ALL SELECT 'Aloo Matar' AS name,NULL AS description,100 AS base_price,'[{"name": "Half", "price": 100}, {"name": "Full", "price": 160}]' AS options_json,1 AS sort_order,'Aloo Item''s' AS category
 UNION ALL SELECT 'Aloo Dum Masala' AS name,NULL AS description,130 AS base_price,'[{"name": "Half", "price": 130}, {"name": "Full", "price": 190}]' AS options_json,2 AS sort_order,'Aloo Item''s' AS category
 UNION ALL SELECT 'Aloo Dum Palak' AS name,NULL AS description,130 AS base_price,'[{"name": "Half", "price": 130}, {"name": "Full", "price": 190}]' AS options_json,3 AS sort_order,'Aloo Item''s' AS category
 UNION ALL SELECT 'Aloo Palak' AS name,NULL AS description,100 AS base_price,'[{"name": "Half", "price": 100}, {"name": "Full", "price": 160}]' AS options_json,4 AS sort_order,'Aloo Item''s' AS category
 UNION ALL SELECT 'Aloo Gobhi' AS name,NULL AS description,110 AS base_price,'[{"name": "Half", "price": 110}, {"name": "Full", "price": 180}]' AS options_json,5 AS sort_order,'Aloo Item''s' AS category
 UNION ALL SELECT 'Aloo Tamatar' AS name,NULL AS description,100 AS base_price,'[{"name": "Half", "price": 100}, {"name": "Full", "price": 160}]' AS options_json,6 AS sort_order,'Aloo Item''s' AS category
 UNION ALL SELECT 'Dal Fry' AS name,NULL AS description,80 AS base_price,'[{"name": "Half", "price": 80}, {"name": "Full", "price": 130}]' AS options_json,0 AS sort_order,'Dal Items' AS category
 UNION ALL SELECT 'Dal Tadka' AS name,NULL AS description,100 AS base_price,'[{"name": "Half", "price": 100}, {"name": "Full", "price": 150}]' AS options_json,1 AS sort_order,'Dal Items' AS category
 UNION ALL SELECT 'Dal Butter Fry' AS name,NULL AS description,110 AS base_price,'[{"name": "Half", "price": 110}, {"name": "Full", "price": 160}]' AS options_json,2 AS sort_order,'Dal Items' AS category
 UNION ALL SELECT 'Arhar Dal Fry' AS name,NULL AS description,100 AS base_price,'[{"name": "Half", "price": 100}, {"name": "Full", "price": 160}]' AS options_json,3 AS sort_order,'Dal Items' AS category
 UNION ALL SELECT 'Dal Makhni' AS name,NULL AS description,130 AS base_price,'[{"name": "Half", "price": 130}, {"name": "Full", "price": 200}]' AS options_json,4 AS sort_order,'Dal Items' AS category
 UNION ALL SELECT 'Plain Rice' AS name,NULL AS description,60 AS base_price,'[{"name": "Half", "price": 60}, {"name": "Full", "price": 90}]' AS options_json,0 AS sort_order,'Rice Items' AS category
 UNION ALL SELECT 'Jeera Rice' AS name,NULL AS description,110 AS base_price,'[]' AS options_json,1 AS sort_order,'Rice Items' AS category
 UNION ALL SELECT 'Fried Rice' AS name,NULL AS description,70 AS base_price,'[{"name": "Half", "price": 70}, {"name": "Full", "price": 120}]' AS options_json,2 AS sort_order,'Rice Items' AS category
 UNION ALL SELECT 'Paneer Fried Rice' AS name,NULL AS description,180 AS base_price,'[]' AS options_json,3 AS sort_order,'Rice Items' AS category
 UNION ALL SELECT 'Veg Biryani' AS name,NULL AS description,140 AS base_price,'[]' AS options_json,4 AS sort_order,'Rice Items' AS category
 UNION ALL SELECT 'Veg Pulao' AS name,NULL AS description,120 AS base_price,'[]' AS options_json,5 AS sort_order,'Rice Items' AS category
 UNION ALL SELECT 'Matar Pulao' AS name,NULL AS description,110 AS base_price,'[]' AS options_json,6 AS sort_order,'Rice Items' AS category
 UNION ALL SELECT 'Plain Roti' AS name,NULL AS description,8 AS base_price,'[{"name": "Tawa", "price": 8}, {"name": "Tandoor", "price": 12}]' AS options_json,0 AS sort_order,'Chapati' AS category
 UNION ALL SELECT 'Butter Roti' AS name,NULL AS description,10 AS base_price,'[{"name": "Tawa", "price": 10}, {"name": "Tandoor", "price": 14}]' AS options_json,1 AS sort_order,'Chapati' AS category
 UNION ALL SELECT 'Plain Naan' AS name,NULL AS description,40 AS base_price,'[]' AS options_json,2 AS sort_order,'Chapati' AS category
 UNION ALL SELECT 'Butter Naan' AS name,NULL AS description,60 AS base_price,'[]' AS options_json,3 AS sort_order,'Chapati' AS category
 UNION ALL SELECT 'Garlic Naan' AS name,NULL AS description,70 AS base_price,'[]' AS options_json,4 AS sort_order,'Chapati' AS category
 UNION ALL SELECT 'Aloo Naan' AS name,NULL AS description,70 AS base_price,'[]' AS options_json,5 AS sort_order,'Chapati' AS category
 UNION ALL SELECT 'Paneer Naan' AS name,NULL AS description,80 AS base_price,'[]' AS options_json,6 AS sort_order,'Chapati' AS category
 UNION ALL SELECT 'Chur Chur Naan' AS name,NULL AS description,90 AS base_price,'[]' AS options_json,7 AS sort_order,'Chapati' AS category
 UNION ALL SELECT 'Missi Roti' AS name,NULL AS description,40 AS base_price,'[]' AS options_json,8 AS sort_order,'Chapati' AS category
 UNION ALL SELECT 'Makka Roti' AS name,NULL AS description,40 AS base_price,'[]' AS options_json,9 AS sort_order,'Chapati' AS category
 UNION ALL SELECT 'Lachha' AS name,NULL AS description,40 AS base_price,'[]' AS options_json,10 AS sort_order,'Chapati' AS category
 UNION ALL SELECT 'Pyaz Roti' AS name,NULL AS description,20 AS base_price,'[]' AS options_json,11 AS sort_order,'Chapati' AS category
 UNION ALL SELECT 'Plain Paratha' AS name,NULL AS description,30 AS base_price,'[]' AS options_json,0 AS sort_order,'Parathe' AS category
 UNION ALL SELECT 'Ajwain Paratha' AS name,NULL AS description,40 AS base_price,'[]' AS options_json,1 AS sort_order,'Parathe' AS category
 UNION ALL SELECT 'Aloo Paratha' AS name,NULL AS description,60 AS base_price,'[]' AS options_json,2 AS sort_order,'Parathe' AS category
 UNION ALL SELECT 'Onion Paratha' AS name,NULL AS description,60 AS base_price,'[]' AS options_json,3 AS sort_order,'Parathe' AS category
 UNION ALL SELECT 'Aloo Onion Paratha' AS name,NULL AS description,70 AS base_price,'[]' AS options_json,4 AS sort_order,'Parathe' AS category
 UNION ALL SELECT 'Gobi Paratha' AS name,NULL AS description,80 AS base_price,'[]' AS options_json,5 AS sort_order,'Parathe' AS category
 UNION ALL SELECT 'Mix Paratha' AS name,NULL AS description,80 AS base_price,'[]' AS options_json,6 AS sort_order,'Parathe' AS category
 UNION ALL SELECT 'Paneer Paratha' AS name,NULL AS description,90 AS base_price,'[]' AS options_json,7 AS sort_order,'Parathe' AS category
 UNION ALL SELECT 'Paneer Onion Paratha' AS name,NULL AS description,80 AS base_price,'[]' AS options_json,8 AS sort_order,'Parathe' AS category
 UNION ALL SELECT 'Base Thali' AS name,'Sabji, Dal, Raita, Rice, 4 Butter Tawa Roti / 2 Tandoori Roti' AS description,120 AS base_price,'[]' AS options_json,0 AS sort_order,'Thali' AS category
 UNION ALL SELECT 'Matar Paneer Thali' AS name,'Matar Paneer, Dal, Raita, Rice, 4 Butter Tawa Roti / 2 Tandoori Roti' AS description,150 AS base_price,'[]' AS options_json,1 AS sort_order,'Thali' AS category
 UNION ALL SELECT 'Shahi Paneer Thali' AS name,'Shahi Paneer, Dal, Raita, Rice, 4 Butter Tawa Roti / 2 Tandoori Roti' AS description,160 AS base_price,'[]' AS options_json,2 AS sort_order,'Thali' AS category
 UNION ALL SELECT 'Lachha Thali' AS name,'Kadai Paneer, Dal Makhni, Papad, Salad, 2 Lachha Parantha' AS description,230 AS base_price,'[]' AS options_json,3 AS sort_order,'Thali' AS category
 UNION ALL SELECT 'Naan Thali' AS name,'Matar Paneer, Dal Tadka, 2 Aloo Naan, Raita' AS description,190 AS base_price,'[]' AS options_json,4 AS sort_order,'Thali' AS category
 UNION ALL SELECT 'Churchur Naan Thali' AS name,'Chole, 2 Chur Chur Naan, Raita, Salad, Matar Pulao' AS description,230 AS base_price,'[]' AS options_json,5 AS sort_order,'Thali' AS category
 UNION ALL SELECT 'Kaku Spl. Thali' AS name,'Kadai Paneer, Dal Makhni, 2 Tawa Roti / 3 Tandoori Roti, Raita, Salad, Papad' AS description,210 AS base_price,'[]' AS options_json,6 AS sort_order,'Thali' AS category
 UNION ALL SELECT 'Kaku Maharaja Thali' AS name,'Shahi Paneer, Dal Makhni, Mix Veg, Jeera Rice, 5 Tawa Roti / 3 Tandoori Roti, Raita, Salad, Papad' AS description,330 AS base_price,'[]' AS options_json,7 AS sort_order,'Thali' AS category
 UNION ALL SELECT 'Kadi + Rice' AS name,NULL AS description,110 AS base_price,'[]' AS options_json,0 AS sort_order,'Rice Combo' AS category
 UNION ALL SELECT 'Dal + Rice' AS name,NULL AS description,110 AS base_price,'[]' AS options_json,1 AS sort_order,'Rice Combo' AS category
 UNION ALL SELECT 'Rajma + Rice' AS name,NULL AS description,120 AS base_price,'[]' AS options_json,2 AS sort_order,'Rice Combo' AS category
 UNION ALL SELECT 'Chole + Rice' AS name,NULL AS description,120 AS base_price,'[]' AS options_json,3 AS sort_order,'Rice Combo' AS category
 UNION ALL SELECT 'Manchurian + Rice' AS name,NULL AS description,150 AS base_price,'[]' AS options_json,4 AS sort_order,'Rice Combo' AS category
 UNION ALL SELECT 'Dal Makhani + Rice' AS name,NULL AS description,150 AS base_price,'[]' AS options_json,5 AS sort_order,'Rice Combo' AS category
 UNION ALL SELECT 'Mango Flavored' AS name,NULL AS description,70 AS base_price,'[]' AS options_json,0 AS sort_order,'Milkshakes' AS category
 UNION ALL SELECT 'Banana Flavored' AS name,NULL AS description,70 AS base_price,'[]' AS options_json,1 AS sort_order,'Milkshakes' AS category
 UNION ALL SELECT 'Strawberry' AS name,NULL AS description,80 AS base_price,'[]' AS options_json,2 AS sort_order,'Milkshakes' AS category
 UNION ALL SELECT 'Butter Scotch' AS name,NULL AS description,90 AS base_price,'[]' AS options_json,3 AS sort_order,'Milkshakes' AS category
 UNION ALL SELECT 'Chocolate' AS name,NULL AS description,90 AS base_price,'[]' AS options_json,4 AS sort_order,'Milkshakes' AS category
 UNION ALL SELECT 'Oreo' AS name,NULL AS description,100 AS base_price,'[]' AS options_json,5 AS sort_order,'Milkshakes' AS category
 UNION ALL SELECT 'Kitkat' AS name,NULL AS description,100 AS base_price,'[]' AS options_json,6 AS sort_order,'Milkshakes' AS category
 UNION ALL SELECT 'Kaku Spl. Tea' AS name,'90 ml' AS description,25 AS base_price,'[]' AS options_json,0 AS sort_order,'Coffee & Tea' AS category
 UNION ALL SELECT 'Kaku Spl. Khulhad Tea' AS name,'140 ml' AS description,35 AS base_price,'[]' AS options_json,1 AS sort_order,'Coffee & Tea' AS category
 UNION ALL SELECT 'Hot Coffee' AS name,NULL AS description,50 AS base_price,'[]' AS options_json,2 AS sort_order,'Coffee & Tea' AS category
 UNION ALL SELECT 'Black Coffee' AS name,'200 ml' AS description,30 AS base_price,'[]' AS options_json,3 AS sort_order,'Coffee & Tea' AS category
 UNION ALL SELECT 'Hot Chocolate' AS name,'200 ml' AS description,120 AS base_price,'[]' AS options_json,4 AS sort_order,'Coffee & Tea' AS category
 UNION ALL SELECT 'Haldi & Elachi Milk' AS name,'300 ml' AS description,70 AS base_price,'[]' AS options_json,5 AS sort_order,'Coffee & Tea' AS category
 UNION ALL SELECT 'Cold Coffee' AS name,NULL AS description,90 AS base_price,'[]' AS options_json,6 AS sort_order,'Coffee & Tea' AS category
 UNION ALL SELECT 'Vanilla Icecream' AS name,'2 scoops' AS description,50 AS base_price,'[]' AS options_json,0 AS sort_order,'Sweets' AS category
 UNION ALL SELECT 'Chocolate Brownie' AS name,'1 pc, 80 g' AS description,70 AS base_price,'[]' AS options_json,1 AS sort_order,'Sweets' AS category
 UNION ALL SELECT 'Gulab Jamun' AS name,'2 pcs' AS description,50 AS base_price,'[]' AS options_json,2 AS sort_order,'Sweets' AS category
 UNION ALL SELECT 'Kheer' AS name,'180 ml' AS description,60 AS base_price,'[]' AS options_json,3 AS sort_order,'Sweets' AS category
 UNION ALL SELECT 'Chocolava Cake' AS name,NULL AS description,50 AS base_price,'[]' AS options_json,4 AS sort_order,'Sweets' AS category
 UNION ALL SELECT 'Salt Lassi' AS name,NULL AS description,60 AS base_price,'[]' AS options_json,0 AS sort_order,'Dahi & Lassi' AS category
 UNION ALL SELECT 'Sweet Lassi' AS name,NULL AS description,70 AS base_price,'[]' AS options_json,1 AS sort_order,'Dahi & Lassi' AS category
 UNION ALL SELECT 'Bundi Raita' AS name,NULL AS description,90 AS base_price,'[]' AS options_json,2 AS sort_order,'Dahi & Lassi' AS category
 UNION ALL SELECT 'Jeera Raita' AS name,NULL AS description,90 AS base_price,'[]' AS options_json,3 AS sort_order,'Dahi & Lassi' AS category
 UNION ALL SELECT 'Aloo Raita' AS name,NULL AS description,100 AS base_price,'[]' AS options_json,4 AS sort_order,'Dahi & Lassi' AS category
 UNION ALL SELECT 'Mix Raita' AS name,NULL AS description,110 AS base_price,'[]' AS options_json,5 AS sort_order,'Dahi & Lassi' AS category
 UNION ALL SELECT 'Dahi' AS name,NULL AS description,60 AS base_price,'[{"name": "Half", "price": 60}, {"name": "Full", "price": 100}]' AS options_json,6 AS sort_order,'Dahi & Lassi' AS category
) AS m
JOIN categories c ON c.restaurant_id=@rid AND c.name=m.category;
