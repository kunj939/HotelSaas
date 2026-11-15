"""Build the bundled Kaku starter menu. Prices transcribed from the two supplied PDFs.

Run from any directory: python tools/build_kaku_menu.py
Photo metadata is kept separately in public/assets/photos/kaku/credits.json.
See catalog/KAKU_MENU.md for source notes and portion-label assumptions.
"""
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
# Category | default photo key | labels for multiple prices.
# Dish | price(s) | optional photo override | optional description.
SOURCE = """
# Single Topping Pizzas|pizza|Small,Medium,Large
Cheese Capsicum Pizza|120,210,280
Cheese Corn Pizza|120,210,280
Cheese Onion Pizza|120,210,280
Cheese Tomato Pizza|120,210,280
Cheese Margherita Pizza|140,230,300|margherita
Cheese Red Pepper Pizza|140,230,300
Cheese Black Olive Pizza|140,230,300
Cheese Paneer Pizza|140,230,300
Cheese Mushroom Pizza|140,230,300
# Multi Topping Pizzas|pizza|Small,Medium,Large
Spicy Corn Pizza|150,270,400||Golden corn and red pepper.
Double Cheese Margherita Pizza|150,270,400|margherita|Loaded with extra cheese.
Country Feast Pizza|150,270,400||Onion, capsicum and fresh tomato.
Farm Fresh Pizza|160,280,410||Capsicum, fresh tomato, mushroom and jalapeno.
Pepper Paneer Pizza|160,280,410||Paneer, onion, capsicum and red pepper.
Super Deluxe Pizza|160,280,410||Onion, capsicum, paneer and corn.
Deluxe Veggie Pizza|160,280,410||Red pepper and capsicum.
Veg Loaded Pizza|160,280,410||Tomato, paneer, jalapeno and corn.
Tandoori Paneer Pizza|200,300,410||Tandoori sauce, paneer, capsicum and red paprika.
Veg Pie Pizza|200,300,410||Crispy onion, diced mozzarella and loaded veggies.
Maxicon Hunger Pizza|200,300,410||Onion, tomato, capsicum and fresh mushroom.
All Topping Pizza|250,360,470||Vegetable toppings with mozzarella. Mention your preferred toppings in the order note.
# Bread Veg Appetizers|sandwich|
Veg Grilled Sandwich|90
Spicy Corn Sandwich|90
Tandoori Paneer Sandwich|110
Cheese Sandwich|120
Butter Toast|60|toast
Bread Butter|60|toast
Stick Garlic Bread|120|garlic-bread
Stuffed Garlic Bread|150|garlic-bread
# Burgers|burger|
Aloo Tikki Burger|60
Veg Spicy Burger|70
Delight Burger|90
Cheese Slice Burger|100
Paneer Burger|110
King Burger|140
# Pasta|pasta-white|
White Pasta|140
Red Pasta|140|pasta-red
Mix Sauce Pasta|150|pasta-red
# Maggi|maggi|
Plain Maggi|70
Veg Maggi|80
Corn & Cheese Maggi|100
# Fries|fries|
Salted Fries|100
Masala Fries|110
Peri Peri Fries|130
Cheesy Fries|130
# Wraps|wrap|
Veg Spicy Wrap|90
Aloo Patty Wrap|100
Paneer Wrap|110
# Tandoori Appetizers|chaap|Half,Full
Malai Chaap|140,210
Masala Chaap|130,200
Malai Masala Chaap|150,220
Afghani Chaap|150,220
Achari Chaap|140,220
Paneer Tikka|220|paneer-tikka
Mushroom Tikka|250|mushroom-tikka
Paneer Malai Tikka|280|paneer-tikka
# Soups|vegetable-soup|
Tomato Soup|70|tomato-soup
Mix Veg Soup|70
Hot & Sour Soup|80|manchow
Veg Manchow Soup|80|manchow
# Chinese Starters|noodles|Gravy,Dry
Chilli Potato|130|chilli-potato
Honey Chilli Potato|150|chilli-potato
Chilli Paneer|200|chilli-paneer
Veg Noodles|140
Chilli Garlic Noodles|160
Singapuri Noodles|180
Hakka Noodles|180
Manchurian|230,230|manchurian
# Special Items|pakora|Half,Full
Chhole Bhature|90|chole-bhature
Puri Sabji|90|puri
Poha|80|poha
Sarson Saag|130,200|saag
Upma|80|upma
Khichdi|80|khichdi
Mix Pakode|90
Bread Pakode|70|bread-pakora
Pav Bhaji|100|pav-bhaji
Channa Chaat|100|chana-chaat
Corn Chaat|90|corn
Sweet Corn|70|corn
Besan Chilla|90|chilla
# Indian Curries|chana|Half,Full
Channa Masala|130,200
Chole|110,170
Rajma Masala|110,170|rajma
Bhindi Masala|120,190|bhindi
Gobi Masala|140,220|gobi
Gobi Matar|140,220|gobi
Mix Veg|140,220|mixed-veg
Pindi Chana|140,220
Kadi|100,170|kadhi
Kadi Pakoda|110,180|kadhi
Sev Bhaji|130,200|sev
Sev Tamatar|130,200|sev
Baingan Bharta|130,200|baingan
# Paneer Items|paneer-butter-masala|Half,Full
Matar Paneer|140,210|matar-paneer
Shahi Paneer|140,210
Kadai Paneer|150,230|kadai-paneer
Paneer Do Pyaza|150,230
Palak Paneer|150,230|palak-paneer
Chana Paneer|150,230|matar-paneer
Paneer Lababdar|160,240
Paneer Bhurji|160,240|bhurji
Paneer Malai|160,240
Paneer Butter Masala|170,250
Paneer Malai Methi|170,250
Paneer Makhmali|170,250
# Aloo Items|jeera-aloo|Half,Full
Aloo Jeera|110,170
Aloo Matar|100,160|aloo-matar
Aloo Dum Masala|130,190|dum-aloo
Aloo Dum Palak|130,190|saag
Aloo Palak|100,160|saag
Aloo Gobhi|110,180|gobi
Aloo Tamatar|100,160|aloo-matar
# Rice Items|rice|Half,Full
Plain Rice|60,90
Jeera Rice|110|jeera-rice
Fried Rice|70,120|fried-rice
Paneer Fried Rice|160|fried-rice
Veg Biryani|140|biryani
Veg Pulao|120|pulao
Matar Pulao|110|pulao
# Dal Items|dal|Half,Full
Dal Fry|80,130
Dal Tadka|100,150
Dal Butter Fry|110,160
Arhar Dal Fry|100,160
Dal Makhni|130,200|dal-makhani
# Kofta|kofta|Half,Full
Malai Kofta|160,230
Angoori Kofta|170,250
Palak Kofta|160,230
# Mushroom Items|mushroom|Half,Full
Matar Mushroom|150,220
Kadai Mushroom|170,240
Mushroom Masala|180,230
# Soya Chaap|chaap|Half,Full
Chaap Gravy|140,200
Kadi Chaap|150,210
# Chapati & Naan|butter-naan|Tawa,Tandoor
Plain Roti|8,12|roti
Butter Roti|10,14|roti
Plain Naan|40
Butter Naan|60
Garlic Naan|70|garlic-naan
Aloo Naan|70
Paneer Naan|80
Chur Chur Naan|90
Missi Roti|40|roti
Makka Roti|40|makki-roti
Lachha Paratha|40|paratha
Pyaz Roti|20|roti
# Thali|thali|
Base Thali|120
Matar Paneer Thali|150
Shahi Paneer Thali|160
Lachha Thali|230
Naan Thali|190
Churchur Naan Thali|230
Kaku Spl. Thali|210
Kaku Maharaja Thali|330
# Parathe|paratha|
Plain Paratha|30
Ajwain Paratha|40
Aloo Paratha|60
Onion Paratha|60
Aloo Onion Paratha|70
Gobi Paratha|80
Mix Paratha|80
Paneer Paratha|90
Paneer Onion Paratha|80
# Rice Combos|thali|
Kadi + Rice|110
Dal + Rice|110
Rajma + Rice|120
Chole + Rice|120
Manchurian + Rice|150|fried-rice
Dal Makhani + Rice|150
# Milkshakes|chocolate-shake|
Mango Milkshake|70|mango-shake
Banana Milkshake|70|banana-shake
Strawberry Milkshake|80|strawberry-shake
Butterscotch Milkshake|90|banana-shake
Chocolate Milkshake|90
Oreo Milkshake|100
KitKat Milkshake|100
# Coffee & Tea|coffee|
Kaku Spl. Tea|25|masala-chai
Kaku Spl. Kulhad Tea|35|masala-chai
Hot Coffee|50
Black Coffee|50|black-coffee
Hot Chocolate|120|hot-chocolate
Haldi & Elaichi Milk|70|milk
Cold Coffee|90|cold-coffee
# Sweets|ice-cream|
Vanilla Ice Cream|50||3 scoops.
Chocolate Brownie|70|brownie|1 piece (80 g).
Gulab Jamun|50|gulab-jamun|2 pieces.
Kheer|60|kheer
Chocolava Cake|70|lava-cake
# Dahi & Lassi|raita|Half,Full
Salt Lassi|60|lassi
Sweet Lassi|70|lassi
Boondi Raita|90
Jeera Raita|90
Aloo Raita|100
Mix Raita|110
Dahi|60,100|yogurt
"""

def build():
    credits = json.loads((ROOT/'public/assets/photos/kaku/credits.json').read_text(encoding='utf-8'))
    categories, items = [], []
    for line in SOURCE.strip().splitlines():
        fields = line.split('|')
        if line.startswith('# '):
            category, default_photo, labels = fields
            category = category[2:]
            categories.append(category)
            labels = labels.split(',') if labels else []
            continue
        name, prices, *extras = fields
        prices = [int(price) for price in prices.split(',')]
        photo_key = extras[0] if extras and extras[0] else default_photo
        credit = credits[photo_key]
        options = []
        if len(prices) > 1:
            assert len(labels) == len(prices), name
            options = [dict(name=label, price=price) for label, price in zip(labels, prices)]
        items.append(dict(category=category, name=name, description=extras[1] if len(extras)>1 else '',
                          food_type='veg', base_price=min(prices), options=options, addons=[],
                          image_url=credit['file'], image_credit=credit['artist'],
                          image_license=credit['license'], image_source_url=credit['source'], available=1))
    assert len({item['name'] for item in items}) == len(items)
    data = dict(id='kaku-v1', name='Kaku 2.0 Pizza & Restaurant', version=1,
                categories=categories, items=items)
    path=ROOT/'public/assets/kaku_menu.json'
    path.write_text(json.dumps(data, ensure_ascii=False, indent=2)+'\n', encoding='utf-8')
    print(f'Built {len(items)} dishes in {len(categories)} categories: {path}')

if __name__ == '__main__':
    build()
