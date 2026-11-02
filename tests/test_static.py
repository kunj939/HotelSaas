from pathlib import Path
import json, re
p=Path(__file__).resolve().parents[1]
items=json.loads((p/'catalog/indian_dishes_500.json').read_text())
assert len(items)==500,len(items)
assert len({i['slug'] for i in items})==500
assert all((p/'public'/i['illustration']).exists() for i in items)
assert all(i['photo_verified'] is False for i in items)
assert (p/'public/index.html').exists()
assert not (p/'public/demo.html').exists()
assert not (p/'public/assets/demo.js').exists()
assert (p/'.htaccess').exists()
assert (p/'public/api/index.php').exists()
assert (p/'database/schema.sql').exists()
api=(p/'public/api/index.php').read_text()
for route in ['public/restaurant','public/order','public/status','owner/menu/save','owner/orders','owner/analytics','owner/settings/save','owner/photos/suggest','admin/overview','admin/restaurants/create']:
 assert route in api,route
print('PASS: 500 unique dish names and 500 honest illustrated SVGs, required files and API endpoints')
