from pathlib import Path
import re

p = Path(__file__).resolve().parents[1]
schema = (p / 'database/schema.sql').read_text(encoding='utf-8')
migration = (p / 'database/migrations/2026-11-18_table_bills.sql').read_text(encoding='utf-8')
delivery_migration = (p / 'database/migrations/2026-11-18_order_delivery.sql').read_text(encoding='utf-8')
api = (p / 'public/api/index.php').read_text(encoding='utf-8')
owner = (p / 'public/api/owner_tables.php').read_text(encoding='utf-8')
tracking = (p / 'public/api/order_tracking.php').read_text(encoding='utf-8')
app = (p / 'public/assets/app.js').read_text(encoding='utf-8')
bills = (p / 'public/assets/table-bills.js').read_text(encoding='utf-8')
customer = (p / 'public/assets/customer-orders.js').read_text(encoding='utf-8')
index_html = (p / 'public/index.html').read_text(encoding='utf-8')

# Fresh installs and upgrades must create the same bill tables and columns.
for needle in ['CREATE TABLE IF NOT EXISTS table_sessions', 'uq_open_table (restaurant_id,table_no,open_flag)',
               'table_session_id BIGINT UNSIGNED', "source ENUM('qr','whatsapp','phone','walkin')",
               'gstin VARCHAR(15)', 'tax_percent DECIMAL(5,2)',
               "fulfilment ENUM('dine_in','takeaway','delivery')", 'delivery_address VARCHAR(400)']:
    assert needle in schema, needle
for needle in ['CREATE TABLE IF NOT EXISTS table_sessions', 'ADD COLUMN table_session_id', 'ADD COLUMN source', 'ADD COLUMN gstin', 'ADD COLUMN tax_percent']:
    assert needle in migration, needle
for needle in ['ADD COLUMN fulfilment', 'ADD COLUMN delivery_address', "UPDATE orders SET fulfilment='takeaway' WHERE table_no=0"]:
    assert needle in delivery_migration, needle

# Guest orders and staff orders share one pricing and saving path.
assert 'function priceCart(' in api and 'function insertOrder(' in api and 'function openTableSession(' in api
assert "priceCart($r,$b['items']??[])" in api
assert "require __DIR__.'/owner_tables.php'" in api and "$route==='owner/ping'" in api
for route in ['owner/tables', 'owner/table/bill', 'owner/table/close', 'owner/order/manual']:
    assert route in owner, route
assert 'FOR UPDATE' in owner and 'FOR UPDATE' in api
# Staff orders need a fulfilment type; delivery needs a name, mobile and address.
assert "in_array($fulfilment,['dine_in','takeaway','delivery'],true)" in owner
assert "mb_strlen($address)<10" in owner and "'delivery_address'=>$address===''?null:$address" in owner
assert "'restaurant'=>$shop" in owner
# Public tracking must not expose the delivery address.
assert 'o.fulfilment' in tracking and 'delivery_address' not in tracking
# Owner settings persist GSTIN and tax; the admin export includes bills.
assert 'gstin=?,tax_percent=?' in api and "'table_sessions'=>rows(" in api
# Owner order list carries what the order card needs.
assert 'o.delivery_address,o.fulfilment,o.token_day,r.slug AS restaurant_slug' in api

# Links: the WhatsApp CTA carries the menu link, and tracking links open the lookup filled in.
assert "' Menu & order link: '+posterMenuUrl(r)" in app
assert "get('track')" in app and "requestSubmit()" in app
assert 'function trackingLink(' in app
assert "trackingLink(S.restaurant,o.token_no??o.id,o.token_day)" in customer
assert 'orderSendLink(o)' in app and 'fulfilLabel(o)' in app

# Front end wiring.
for fn in ['ownerTables()', 'ownerBill(', 'openManualOrder(', 'shareMenuForWhatsApp(', 'startKeepAlive(', 'showOrderConfirmation(', 'confirmationMessage(', 'whatsappLink(']:
    assert fn in bills or fn in app, fn
assert "else if(t==='tables')await ownerTables()" in app
assert "['tables','🪑','Tables & bills']" in app
assert 'table-bills.js' in index_html and index_html.index('table-bills.js') < index_html.index('app.js?v=')
assert re.search(r"function (ownerTables|ownerBill|openManualOrder)\b", bills)

print('PASS: table bill and delivery schema, migrations, owner routes, tracking links and dashboard wiring')
