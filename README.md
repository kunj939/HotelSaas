# TableBloom — Self-Service Restaurant QR Ordering SaaS

**One codebase. Any number of restaurants (subject to hosting capacity). No diners' login. Restaurants pay their subscription online through PayU.**

A ready-to-deploy PHP + MySQL + HTML/CSS/JavaScript project for Hostinger Premium shared hosting. Customer UI is mobile-first and restaurant-branded, with a separate restaurant owner portal and SaaS Super Admin.

## What is included

| Module | Included functionality |
|---|---|
| Customer | Welcome → premium menu → cart → table number only at checkout → final confirmation → order received → order tracking → order more |
| Menu | Categories, veg/non-veg, search, photos or placeholders, sizes/portions, optional add-ons, notes, live availability |
| QR modes | One restaurant-wide QR with manual table number, a unique QR for each configured table, or **Counter** mode for small places without tables (no table question, token only). Offline printable QR SVG |
| Tokens | Every order gets a daily token number (1, 2, 3… restarting each day). Owner presses **📣 Call token**: the order is marked ready and the guest's phone shows the token with sound and vibration |
| Multiple outlets | One owner login can open several restaurants: switch outlet from the top bar and see **All outlets** orders together. 2 outlets are included; each extra outlet adds ₹200/month to the owner's bill. Super Admin links outlets to an owner |
| Restaurant owner | Login, self-service menu management, add/remove items, category creation, photo uploads, clipboard image paste, direct HTTPS image links, price edits, open/closed ordering, custom brand colors, cover, logo, QR download, profile, password change |
| Orders | Live / Today / Past, notifications, accept → preparing → ready → served, rejection, table and order details |
| Analytics | Monthly order counts, item popularity, top ordering hours, recorded order value (not verified cash collection) |
| Billing | Owner pays from the Billing tab through PayU: ₹599/month without autopay, or ₹549/month with 6-month autopay (UPI/card mandate). A ₹500 one-time setup fee is added to the first payment. Each payment extends the subscription by one month. Until PayU is set up, owners can **Pay by UPI** (QR + UTR): monthly, or 6 months upfront at ₹549/month; Super Admin approves the UTR and the subscription extends |
| SaaS admin | Create restaurant + owner, 7-day trial, subscription status/expiry, fees, setup-fee status, autopay status, **stop/resume, download all data, delete (restorable) and permanent delete** per restaurant, monthly recurring revenue, paid-subscription receipts, restaurant-wide platform analysis, password resets |
| Security | Server-priced orders, session/CSRF auth, role checks, MySQL prepared statements, tenant-scoped operations, rate limits, idempotency keys, non-executable uploads, password hashing |
| Image starters | Exactly 500 Indian dish names and 500 original **illustrated SVG placeholders**, not 500 real photographs. Photo suggestion fetches suitable Wikimedia Commons results with license attribution; owner chooses image. |

### Important: about the 500 images

**Do not mistake the 500 bundled illustrations for photographs.** We did not have access to a legitimate, verified collection of 500 accurately labelled and reuse-licensed food photographs, so the ZIP does **not** contain 500 real dish photos. It includes exactly 500 local generated SVG artwork thumbnails and 500 dish names, plus owner-uploaded photos and a live Wikimedia Commons photo suggestion integration. Never copy images from Google, Zomato, or Swiggy without permission. Owners should verify generic photos are representative of their dish.

The **demo** menu uses 15 real food photos and a real banner photo from Wikimedia Commons, all CC BY-SA 4.0 or CC0. Photographer, licence and source page for each are in `public/assets/photos/credits.json`, and the customer app shows the credit beside each photo. Keep that credit visible if you reuse these images.

## Three URLs after deployment

- Customer: `https://YOURDOMAIN/index.html#/r/restaurant-slug`
- Restaurant owner: `https://YOURDOMAIN/index.html#/owner`
- Platform Super Admin: `https://YOURDOMAIN/admin` (not linked from the home page; type it directly)

QR settings generate the appropriate URL. Restaurant owner sees only their data. Account access created by Super Admin.

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

Static checks passed: PHP syntax validation, JavaScript syntax validation, QR SVG code generation and QR image scanning decode, catalog size and assets count. **Database-backed end-to-end and Hostinger production testing were not possible in this environment.** Run the manual test plan with your actual Hostinger credentials before relying on real customer orders.

## Dish photos

In Owner → Menu → Add/Edit Dish, upload a JPG/PNG/WebP (up to 2.5 MB), paste a copied image into the editor, or paste a direct HTTPS image address and select **Use image link**. Review the preview, then save. Pasted image files use the same validated upload endpoint as file uploads. Image links are saved directly, without a server download; some external hosts block hotlinking or remove images. Broken links display the default food artwork. Use **Skip photo** to remove a photo.

Local checks: `php tests/test_images.php` and `node tests/test_menu_images.js`. Clipboard support depends on the browser and source application; file upload remains available.
