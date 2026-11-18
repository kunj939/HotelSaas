# TableBloom — Self-Service Restaurant QR Ordering SaaS

**One codebase. Any number of restaurants (subject to hosting capacity). No diners' login. Restaurants pay their subscription online through PayU.**

A ready-to-deploy PHP + MySQL + HTML/CSS/JavaScript project for Hostinger Premium shared hosting. Customer UI is mobile-first and restaurant-branded, with a separate restaurant owner portal and SaaS Super Admin.

## What is included

Owners can highlight **Chef's Specials**, configure checkout name/mobile as **off, optional or required**, and show location/menu/WhatsApp order/party/bulk-enquiry links. See [the demo and upgrade guide](DEMO_GUIDE.md). Existing databases require all three November 15 migrations before this API update.

| Module | Included functionality |
|---|---|
| Customer | Welcome → premium menu → cart → table number only at checkout → final confirmation → order received → order tracking → order more |
| Menu | Categories, veg/non-veg, search, photos or placeholders, sizes/portions, optional add-ons, notes, live availability |
| Starting menu | Accounts start with an empty menu by default. For Kaku 2.0 onboarding, explicitly choose its prepared menu: 194 dishes in 28 categories with PDF prices, portions and representative food photos. Owners can edit, add, remove and mark dishes sold out. |
| QR modes | One restaurant-wide QR with manual table number, a unique QR for each configured table, or **Counter** mode for small places without tables (no table question, token only). Offline printable QR SVG |
| Tokens | Every order gets a daily token number (1, 2, 3… restarting each day). Owner presses **📣 Call token**: the order is marked ready and the guest's phone shows the token with sound and vibration |
| Multiple outlets | Owners can **Add outlet** from **My outlets**, choose a menu link, address and ordering style, and start empty or copy an accessible menu. Copies can be edited independently. **The second outlet is free; from the third, each extra outlet costs ₹250/month.** Owner-created outlets share the main restaurant's subscription, renewal date and payment history, with no separate base plan or extra setup fee. **All outlets** combines orders. Admin-linked, independently owned accounts keep their own billing. |
| Restaurant owner | Login, self-service menu management, add/remove items, category creation, photo uploads, clipboard image paste, direct HTTPS image links, price edits, open/closed ordering, custom brand colors, cover, logo, QR download, profile, password change |
| Orders | Live / Today / Past, notifications, accept → preparing → ready → served, rejection, table and order details |
| Analytics | Monthly order counts, item popularity, top ordering hours, recorded order value (not verified cash collection) |
| Billing | Owner pays from the Billing tab through PayU: ₹599/month without autopay, or ₹549/month with 6-month autopay (UPI/card mandate). A discounted ₹350 one-time setup fee (₹500 crossed out, 30% OFF) is added to the first payment. Previously created payments retain their recorded amounts. Each payment extends the subscription by one month. Until PayU is set up, owners can **Pay by UPI** (QR + UTR): monthly, or 6 months upfront at ₹549/month; Super Admin approves the UTR and the subscription extends |
| SaaS admin | Create restaurant + owner, 7-day trial, subscription status/expiry, fees, setup-fee status, autopay status, **stop/resume, download all data, delete (restorable) and permanent delete** per restaurant, monthly recurring revenue, paid-subscription receipts, restaurant-wide platform analysis, password resets |
| Security | Server-priced orders, session/CSRF auth, role checks, MySQL prepared statements, tenant-scoped operations, rate limits, idempotency keys, non-executable uploads, password hashing |
| Image starters | Exactly 500 Indian dish names and 500 original **illustrated SVG placeholders**, not 500 real photographs. Photo suggestion fetches suitable Wikimedia Commons results with license attribution; owner chooses image. |

### Important: about the 500 images

**Do not mistake the 500 bundled illustrations for photographs.** We did not have access to a legitimate, verified collection of 500 accurately labelled and reuse-licensed food photographs, so the ZIP does **not** contain 500 real dish photos. It includes exactly 500 local generated SVG artwork thumbnails and 500 dish names, plus owner-uploaded photos and a live Wikimedia Commons photo suggestion integration. Never copy images from Google, Zomato, or Swiggy without permission. Owners should verify generic photos are representative of their dish.

The **Kaku 2.0 prepared menu** is an explicit onboarding option for Kaku's account, not the default for other restaurants. Its 194 distinct local images were reviewed: 110 representative internet photos retain their source credits, and 84 mismatched or unclear images were replaced with dish-specific AI images. These are not photographs taken at Kaku. Customer cards and details show source attribution or an AI-generated label. `public/assets/photos/kaku/premium.json` records provenance; `catalog/kaku_photo_audit.json` records all review decisions and generation prompts. Unchanged starter photos from either earlier version refresh automatically on menu reads; owner replacements, prices and availability are preserved, with no new SQL migration. See [source and portion notes](catalog/KAKU_MENU.md).

## Login and menu URLs after deployment

- Customer: `https://YOURDOMAIN/r/restaurant-slug`
- Restaurant owner: `https://YOURDOMAIN/index.html#/owner`
- Platform Super Admin: `https://YOURDOMAIN/admin` (not linked from the home page; type it directly)
- Limited restaurant admin: `https://YOURDOMAIN/#/agent` (username and password created by Super Admin)

QR settings generate a clean `/r/restaurant-slug` link, adding `?table=1` for table-specific cards. Existing `index.html#/r/...` QR links remain supported. Cards include the restaurant name, green-and-gold styling and downloadable print-ready SVGs. Upload `public/.htaccess` with the updated HTML/JS so direct links and reloads work on Apache/Hostinger. QR links and owner-created outlets need no additional database migration. Owner accounts can be created by Super Admin or a limited admin; owners can add their own outlets.

Existing owner-created outlets automatically use the shared plan through their `owner.outlet.create` records; retain those records in `audit_logs`. Stopped/deleted outlets are excluded from extra-outlet charges. Already recorded payments keep their amounts. The billing cron stops separate legacy outlet mandates, and verified old outlet payments credit the shared subscription. The main billing restaurant cannot be permanently deleted while its outlets still exist.

Dark customer themes now use consistent surfaces and readable controls, with highlighted portions and a contrasting Add button. Local checks include `tests/test_shared_billing.php`, `tests/test_outlets_api.py`, and `tests/test_theme_contrast.js` (10 brand palettes and 320/375/1280 layouts).

Super Admin → **Admins → Add admin** creates a username/password account for restaurant onboarding. Each admin can create restaurants with the platform's default pricing and view only their own list and counts. Pricing, payments, owner dashboards and other accounts are blocked by the API as well as omitted from navigation. Super Admin can disable access or reset the password; both revoke existing sessions. Restaurant attribution survives deletion so the historical onboarding count stays accurate. Counts are for referral tracking; commission amounts and payouts are not calculated.

Existing installations install the additional admin role, username/session-version columns and attribution table automatically on the first authenticated **Add admin** action. The database user needs ALTER/CREATE privileges for that one-time operation. Alternatively run `php tools/upgrade_admin_accounts.php` with the site's configuration. The upgrade is idempotent; existing owner and Super Admin email logins keep working before and after it. Fresh installations include these additions in `database/schema.sql`.

Every order confirmation shows its **token number and order date**, with a button to copy the token and restaurant link. Returning guests select **Track my order** from the restaurant menu and enter the token, even after closing the browser or losing their session. Today is preselected; earlier orders require their date because tokens restart daily. The public receipt shows items, total and live status, including token calls, without exposing customer names, phones, private notes or private tracking keys. Saved receipts remain available when browser storage is allowed; blocked/corrupt storage does not prevent checkout or token lookup. Token lookup itself needs no database migration.

**Table bills and WhatsApp/phone orders:** each table has one open bill. Every order placed at that table joins it until staff mark the bill paid. The Tables tab shows open tables, totals and new orders. Bills print on an 80mm thermal or A4 layout, with restaurant details, optional GSTIN and an optional tax line set in Settings. Staff can enter WhatsApp, phone or walk-in orders in the same queue as QR orders. Run `database/migrations/2026-11-18_table_bills.sql` once on an existing database. A fully automatic WhatsApp bot is not included: it needs a WhatsApp Business Cloud API account and webhook.

## Files

```
.htaccess                 # serves public/ as the site root and keeps project files private
public/                   # website files
  index.html              # production single-page app
  api/index.php           # PHP REST API
  api/config.example.php  # template: copy to config.local.php
  assets/app.js           # UI logic
  assets/styles.css       # frontend design
  assets/qr.js            # offline QR generator
  assets/dish_catalog.json
  assets/dishes/*.svg     # 500 illustrated menu placeholders
  uploads/                # real restaurant photo uploads

database/schema.sql       # MySQL schema, import once in phpMyAdmin
catalog/indian_dishes_500.json
catalog/PHOTO_LICENSE_README.md
tools/build_catalog.py   # regenerate locally, Python stdlib only
tools/cleanup.sql
HOSTINGER_DEPLOY.md       # detailed setup
SECURITY_AND_LIMITATIONS.md
TEST_PLAN.md
```

## Quick restaurant setup

1. Follow `HOSTINGER_DEPLOY.md`: import the schema, configure the database, HTTPS URL and random secrets, then open `api/install_once.php` to create your administrator.
2. Delete the installer and remove `install_key`. With terminal access, run `php tools/check_setup.php` to check runtime, database, schema and installation readiness.
3. Add a restaurant in Super Admin. Choose common QR for guests to select tables, per-table QR for fixed table cards, or counter mode for token service without tables.
4. Owner signs in, changes the temporary password in Settings, adds dishes and prices, and downloads QR codes.
5. Scan the printed QR on a phone and place a test order. Accept, prepare, call its token and mark served from the owner dashboard before opening to guests.

See `CODE_REVIEW.md` for identified issues, fixes and remaining coverage.

## Production installation

See **HOSTINGER_DEPLOY.md**. In short: create a MySQL database; import schema; upload `public/*`; create `api/config.local.php`; visit the one-time installer to create the Super Admin; delete installer & demo; log in and create your first restaurant. Configure SSL/HTTPS, backups and monitoring. Do not deploy until checklist is done.

## Pricing & current product scope

Base offer: **₹599 per restaurant per month**; an existing first-customer agreement may be honoured at **₹499/month**. Online checkout, payment verification, WhatsApp/SMS, restaurant POS integrations and online billing/tax filings are intentionally *not* included. Customer pays restaurant directly. Subscription collections are recorded manually by your Super Admin.

## Runtime requirements

PHP 8.2+; PDO MySQL; `mbstring` and `fileinfo`; PHP `curl` for Wikimedia suggestions (optional); MySQL 8 / compatible MariaDB. Hostinger Premium supports PHP and MySQL/PDO depending on your live plan configuration. No Composer, npm, Node.js server or paid API required.

## Attribution

- Bundled SVG food pictures were generated procedurally for this project; they are *illustrations*, not photographic representations.
- QR library by Kazuhiko Arase, original MIT terms; package integration derived from qrcode-terminal Apache 2.0. Licence notices in `public/assets/`.
- Suggested Wikimedia Commons photos retain source, artist and licence fields; source owner must check licence conditions.

## Current verification status

Static checks pass for PHP/JavaScript syntax, the 500-dish illustration catalog, and the 194-dish starter menu with local photo assets. Local **PHP 8.4 + MariaDB 11.4** API and Chrome browser tests cover account creation, tenant-isolated menus, photo-preserving edits, size pricing, sold-out rejection/restocking, add/delete persistence, transaction rollback and mobile layouts. **Hostinger production testing has not been performed.** Run the manual test plan on your deployment before relying on real customer orders.

## Dish photos

In Owner → Menu → Add/Edit Dish, upload a JPG/PNG/WebP (up to 2.5 MB), click **Paste image** to paste a copied image or image link, use Ctrl+V / Cmd+V in the editor, or paste a direct HTTPS image address and select **Use image link**. Review the preview, then save. Pasted image files use the same validated upload endpoint as file uploads. Image links are saved directly, without a server download; some external hosts block hotlinking or remove images. Broken links display the default food artwork. Use **Skip photo** to remove a photo.

Local checks: `php tests/test_images.php` and `node tests/test_menu_images.js`. Clipboard support depends on the browser and source application; file upload remains available.
