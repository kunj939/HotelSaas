# TableBloom — Self-Service Restaurant QR Ordering SaaS

**One codebase. Any number of restaurants (subject to hosting capacity). No diners' login. No online payment integration.**

A ready-to-deploy PHP + MySQL + HTML/CSS/JavaScript project for Hostinger Premium shared hosting. Customer UI is mobile-first and restaurant-branded, with a separate restaurant owner portal and SaaS Super Admin.

## What is included

| Module | Included functionality |
|---|---|
| Customer | Welcome → premium menu → cart → table number only at checkout → final confirmation → order received → order tracking → order more |
| Menu | Categories, veg/non-veg, search, photos or placeholders, sizes/portions, optional add-ons, notes, live availability |
| QR modes | One restaurant-wide QR with manual table number, or unique QR for each configured table. Offline printable QR SVG |
| Restaurant owner | Login, self-service menu management, add/remove items, category creation, photo uploads, price edits, open/closed ordering, custom brand colors, cover, logo, QR download, profile, password change |
| Orders | Live / Today / Past, notifications, accept → preparing → ready → served, rejection, table and order details |
| Analytics | Monthly order counts, item popularity, top ordering hours, recorded order value (not verified cash collection) |
| SaaS admin | Create restaurant + owner, 7-day trial, subscription status/expiry, ₹499 or ₹599 fees, monthly recurring revenue, paid-subscription receipts, restaurant-wide platform analysis, password resets |
| Security | Server-priced orders, session/CSRF auth, role checks, MySQL prepared statements, tenant-scoped operations, rate limits, idempotency keys, non-executable uploads, password hashing |
| Demo | `public/demo.html`: end-to-end demo using local simulated data; no database required |
| Image starters | Exactly 500 Indian dish names and 500 original **illustrated SVG placeholders**, not 500 real photographs. Photo suggestion fetches suitable Wikimedia Commons results with license attribution; owner chooses image. |

### Important: about the 500 images

**Do not mistake the 500 bundled illustrations for photographs.** We did not have access to a legitimate, verified collection of 500 accurately labelled and reuse-licensed food photographs, so the ZIP does **not** contain 500 real dish photos. It includes exactly 500 local generated SVG artwork thumbnails and 500 dish names, plus owner-uploaded photos and a live Wikimedia Commons photo suggestion integration. Never copy images from Google, Zomato, or Swiggy without permission. Owners should verify generic photos are representative of their dish.

The **demo** menu uses 15 real food photos and a real banner photo from Wikimedia Commons, all CC BY-SA 4.0 or CC0. Photographer, licence and source page for each are in `public/assets/photos/credits.json`, and the customer app shows the credit beside each photo. Keep that credit visible if you reuse these images.

## Three URLs after deployment

- Customer: `https://YOURDOMAIN/index.html#/r/restaurant-slug`
- Restaurant owner: `https://YOURDOMAIN/index.html#/owner`
- Platform Super Admin: `https://YOURDOMAIN/index.html#/admin`

QR settings generate the appropriate URL. Restaurant owner sees only their data. Account access created by Super Admin.

## Files

```
public/                   # upload contents to your site's web root (public_html)
  index.html              # production single-page app
  demo.html               # standalone demo (DELETE before production)
  api/index.php           # PHP REST API
  api/config.example.php  # template: copy to config.local.php
  api/install_once.php    # first Super Admin setup (DELETE after use)
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

## Quick preview, before using MySQL

Open `public/demo.html` via a local web server. For example:

```bash
cd public
python -m http.server 8000
# open http://localhost:8000/demo.html
```

Then experience the customer flow. To see restaurant or Super Admin, change the address to `demo.html#/owner` or `demo.html#/admin`. Demo login accepts any nonempty email and password; `kvmeena2004@outlook.com` becomes Super Admin; any other email becomes restaurant owner. **These are mock accounts only, not production credentials.**

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

Static checks passed: PHP syntax validation, JavaScript syntax validation, QR SVG code generation and QR image scanning decode, catalog size and assets count. **Database-backed end-to-end and Hostinger production testing were not possible in this environment.** Run the manual test plan with your actual Hostinger credentials before relying on real customer orders.
