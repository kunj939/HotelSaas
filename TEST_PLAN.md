# QA Test Plan

## Automated static checks

Run:

```bash
python tests/test_static.py
python tests/test_starter_menu.py
php tests/test_photo_refresh.php
python tests/test_plan_pricing.py
php -l public/api/index.php
php -l public/api/menu_template.php
node --check public/assets/app.js
node --check public/assets/qr.js
node --check public/assets/poster.js
```

For a disposable MySQL-backed test installation, set `TABLEBLOOM_TEST_URL`, `TABLEBLOOM_TEST_EMAIL` and `TABLEBLOOM_TEST_PASSWORD` to its Super Admin account, then run `python tests/test_menu_api.py`. This creates test restaurants and orders; never run it against production. It checks empty default menus, explicitly selected Kaku menus, rejection of unknown templates, photo-preserving edits, ownership isolation, selected-size pricing, sold-out rejection/restocking, add/delete persistence and failed-account rollback.

The Kaku update was also checked locally in Chrome at 375 px and 320 px: admin creates an account, signs out, owner signs in, searches and edits a seeded dish, marks it sold out/restocks it, and a customer selects the pizza size and adds it to the cart. No horizontal overflow or JavaScript errors were found. A missing final starter photo was tested on an isolated copy: account/menu creation rolled back completely and a retry succeeded after restoring the file. Hostinger itself remains untested.

Pricing tests check monthly and 6-month quotes, UPI payment snapshots, approval without overwriting agreed rates, admin permissions and preservation of existing accounts. They create test payments and configure a test UPI ID on the disposable site. Set `TABLEBLOOM_TEST_CONFIG` to that site's local config and run `php tests/test_billing_prices.php` to check PayU settlement and failed-mandate handling without contacting PayU. Live gateway payments are not covered.

## Manual end-to-end use cases

Limited admins and token recovery have automated coverage on the same disposable test installation:

```bash
python tests/test_admin_accounts.py
python tests/test_token_recovery.py
node tests/test_admin_and_tracking_ui.js
```

For the browser suite set `PLAYWRIGHT_MODULE` if Playwright is outside the project and `TABLEBLOOM_ARTIFACTS` for screenshots. For the duplicate-token/different-date database fixture, also set `TABLEBLOOM_PHP` to the PHP executable and `TABLEBLOOM_TEST_CONFIG` to the disposable site's config. The API suites cover server role restrictions, price/attribution spoofing, separate admin counts, rollback, counts after deletion, session revocation, sessionless public receipts, date and restaurant isolation, and exclusion of private fields. The browser suite covers admin creation and username login, restricted navigation, restaurant creation, token confirmation/copy, recovery in a new browser context, live token calls, persisted recovery, invalid tokens, unavailable/corrupt storage and 320/375/1280 layouts. They create test accounts and restaurants: never target production.

On an existing installation, confirm that owner/Super Admin logins work before the admin-schema upgrade, create the first admin, run `php tools/upgrade_admin_accounts.php` twice, and confirm existing logins still work. From the Super Admin's Admins screen, verify Disable access and Reset password sign out the targeted admin. A normal admin must not gain pricing/payment/owner controls by changing the URL.

Additional automated coverage: with the disposable test-site environment configured, run `python tests/test_demo_features.py`. It verifies independent checkout modes, mobile normalization, off fields being discarded, authenticated order details, absence of contacts in public order status, featured edits, contact settings and validation. Browser checks additionally cover the services links, special filter and phone-required/name-off checkout through successful order submission.

1. Onboard a normal restaurant with the default selection: zero dishes and five categories. Onboard Kaku with its prepared menu selected: 194 dishes in 28 categories. Confirm unknown templates are rejected. Photos load locally, pizza sizes and roti Tawa/Tandoor prices match the PDF, and owners cannot modify another restaurant's data. Login or refresh must not re-seed deleted items or reset availability.
2. Owner A adds dish with single price, Half/Full variants and custom extras. Customer sees exactly the configured options.
3. Add dish without photo. Illustration appears; upload a real JPG and verify it replaces the fallback.
4. Change each restaurant theme; ensure menus differ and are still legible on light/dark mode.
5. Scan a common QR. Welcome screen → browse → cart → enter table number → final confirmation → order submitted → waiting message → Order More.
6. Scan table-specific QR (e.g. table 5); correct table is maintained through checkout.
7. Verify table 0 / above max rejected. For common QR, select 1..20 only.
8. Attempt unavailable dish or client-edited prices; backend must reject/recalculate server-side.
9. Retry same order idempotency ID twice; server must return original order instead of duplicating.
10. Open restaurant Live dashboard; New → Accepted → Preparing → Ready → Served. Confirm customer tracking on another phone.
11. Test rejected order path; customer should not see Accepted.
12. Owner Today's/Past orders remain queryable after completion, and menu deletion doesn't erase order history.
13. Analytics monthly counts and values match MySQL records; amounts labelled as **recorded order value**, not confirmed revenue.
14. Expired subscriptions pause new ordering without deleting menus.
15. PayU test mode, restaurant that has not paid the setup fee: monthly plan charges ₹1,099, and autopay charges ₹1,049. After a successful payment, expiry moves one month past the old expiry, setup fee shows Paid, and the payment appears in Billing and Super Admin.
16. Cancel a PayU checkout: Billing shows the failure toast and nothing changes. Posting a fake `txnid` to `api/payu_return.php` changes nothing.
17. Autopay: set the mandate's `next_debit_date` to today+2 and run `php api/payu_cron.php`. The first run sends the pre-debit notice; on the notified date the next run charges ₹549 and moves to month 2 of 6. After month 6 the plan returns to ₹599.
18. Staff accounts can view Billing but cannot pay or stop autopay.
22. Tokens: place 3 orders in one restaurant → tokens 1, 2, 3; another restaurant starts at 1; the next day starts at 1 again. Two phones ordering at the same moment never get the same token.
23. Call token: accept an order, press 📣 Call token → status becomes Ready and the guest's phone (page open, tapped once) shows the token, rings and vibrates, also while browsing the menu. "Call again" rings again. Reloading the page does not ring for an old call.
24. Counter mode: no table question at checkout, owner card shows "Counter", single QR printed. Switching back to Common/Table mode restores the table question.
25. Multiple outlets: link restaurant B to owner A. Open **My outlets** from navigation or the current restaurant button: both cards appear, A is highlighted, and name/address search and Clear search work at 320px and desktop widths. Select B: the previous page returns with B's data, menu filters clear, and orders default to This outlet. Reload preserves B. All outlets shows both with the restaurant name. Failed switches leave the current selection intact and allow retry. Unlink selected B: refreshing falls back to A. Suspended/unlinked restaurants cannot be selected; A cannot open or change orders of unrelated C. A single-outlet owner can still open My outlets. Linking a 3rd outlet adds ₹200 to A's own Billing. Run `tests/test_outlets_api.py` against a disposable test site for access-isolation coverage.
27. UPI: no Pay-by-UPI button until Super Admin saves a UPI ID. Owner pays monthly → ₹1,099 first time (₹599 after); 6 months upfront → ₹3,794 first time. QR opens the UPI app with the amount filled. A second payment is blocked while one is pending, and a UTR can be used only once. Approve adds 1 or 6 months after the current expiry exactly once; Reject shows the reason in the owner's Billing.
28. Owner Menu: search and category filter narrow the list without losing typing focus. ☰ Categories: add (goes last), move up/down (guests see the new order), rename (duplicate names refused), delete (refused while it still has dishes). Empty categories are hidden from guests.
29. Session: leave the dashboard idle for over 30 minutes, then click → still signed in; after signing out in another tab, the next click shows the login screen instead of errors.
30. Guest welcome shows the restaurant tagline, tap-to-call phone, hours and address when set. www.<domain> redirects to the main domain.
26. Super Admin Danger zone: Stop → customers can't order, a logged-in owner is locked out on the next click; Resume restores. Download gives a JSON with the full menu and no password hashes. Delete → customer link shows not found and the owner can't log in; Restore brings it back. Delete permanently refuses until the restaurant is deleted and the exact slug is typed, then removes it and its owner login.
19. Upload a malicious disguised PHP/SVG or >2.5 MB: must reject. Config file must be web-inaccessible.
20. Test mobile UI at 320, 375, 414 px width and on poor network; no invisible CTA, duplicate orders or lost cart.
21. Do load test with representative 10/30/50 restaurants before promising shared-hosting capacity.

## Dish photo sources

- Kaku's 194 images must have distinct file hashes and local JPEG assets. Retained internet photos must have unique source URLs and matching photographer/licence metadata. AI replacements must match the audit's prompts/output paths, have no fabricated source/licence, and show the AI-generated label. Inspect both list and grid layouts at 320 px and 375 px and open a dish to check its full photo.
- Existing starter images refresh without a SQL migration. Verify unchanged photo metadata from both earlier starter versions upgrades, while uploaded/linked/removed photos, edited credits, renamed dishes, prices, featured flags and sold-out status remain unchanged. Missing replacement image files retain the original image.

- Upload valid JPG/PNG/WebP files and paste copied image files into the dish editor; verify previews, save, reopen and customer menu display.
- Paste a direct HTTPS image URL (including query-string CDN URLs), preview, save, reopen and check customer display. Saving without pressing “Use image link” must still preserve the entered URL.
- Reject HTTP, javascript/data URLs, credentials in URLs, oversized links, unsupported clipboard file types and files over 2.5 MB. Existing photos must survive failed selections/uploads.
- Save is disabled during upload; after a failed upload it becomes available again. Close the editor during an upload and open another dish: the old result must not change the new dish.
- Replace a licensed photo with an upload or link: previous credit fields must be cleared. Skip photo must clear the URL field and restore artwork after saving.
- Use a broken image URL or a host blocking hotlinks: customer menu must show default artwork. A webpage URL is not a direct image URL and may show fallback artwork.

Photo refresh validation: 194 unique source URLs and image hashes; all JPEGs decoded; existing-account public API upgrade and partial-upload fallback passed on the disposable test site. Chrome list/grid layouts passed at 320, 375 and 1280 px, including uncropped detail previews and no horizontal overflow or JavaScript errors.

## Table bills, staff-entered orders and dashboard sign-in

Run `database/migrations/2026-11-18_table_bills.sql` once on an existing database (new installs get it from `schema.sql`). Then, on a disposable restaurant:

- Two QR orders from Table 4 share one open bill; a third order from Table 5 gets a separate bill. Tables tab totals and "new" badges match the orders.
- Closing a bill is refused while any order from it is still new, accepted, preparing or ready; after serving or rejecting them it closes, and a later order at that table opens a new bill.
- Print preview: the 80mm thermal layout shows restaurant name, address, phone, GSTIN when set, table, bill number, date, tokens, items with extras, subtotal, tax line only when tax% is above zero, and total. A4 layout prints the same content.
- Settings: GSTIN must be exactly 15 letters or numbers; tax above 28% is refused. Closed bills keep the tax they were closed with after the setting changes.
- WhatsApp/phone order: create a takeaway order with an address in the note, and a table order with a size and extras. Prices come from the server, the token appears in Orders with the WhatsApp label, and table orders join that table's bill. Double-clicking Send creates one order.
- Rejected orders are excluded from bill totals. A menu price change after an order does not change that bill.
- Keep the owner dashboard open on the Tables or Orders tab for over 30 minutes without touching it, then click: still signed in. Sign out in another tab, then click: login screen.
- Staff role can take orders and close bills; staff cannot change settings or menu.

## WhatsApp links, order tokens and delivery

Run `database/migrations/2026-11-18_order_delivery.sql` after the table bills migration on an existing database.

- The plain link (`/r/<slug>`, used by table and common QR codes) is dine-in only, as before: it asks for a table and has no takeaway option.
- The online link (`/r/<slug>?via=whatsapp`) opens a separate "Order online" landing page with no table question. It is generated and copied from Settings → WhatsApp order link.- Take a delivery order: the name, a 10-digit mobile and an address of 10+ characters are required; the server refuses an incomplete one. Dine-in needs a table; takeaway needs neither a table nor an address.
- After saving, the message shows the token, items, total, the delivery address and a tracking link. "Send on WhatsApp" opens WhatsApp to the customer's number with that text ready; without a mobile number, only Copy appears.
- Open the tracking link on a phone that has never ordered there: the order is looked up at once, showing its status. A wrong token shows the usual "No order found" message.
- Orders card: delivery shows "Deliver to" with the address; "Send token & tracking link" appears when a mobile and token exist. The button reads "Mark delivered" for delivery orders.
- The public tracking response does not include the delivery address or phone number.
- A QR order's "Copy token & link" copies the tracking link with the token filled in.

## WhatsApp-link ordering, order bills and the new-order prompt

- Online landing: name, mobile, takeaway or home delivery, and a full address for delivery are required. The order shows under Orders → Online orders with its own Bill button. It does not appear on the Tables board.
- Settings → WhatsApp order link: the copy button copies the online link, which includes `?via=whatsapp`.
- Placing it with a missing name, invalid mobile or short address is refused by the screen and by the server.
- After placing it, the success screen says the token and tracking link will go to the customer's own WhatsApp number. Nothing is sent to the restaurant's number.
- In the new-order prompt and on the order card, "Send token & tracking link to customer" opens WhatsApp addressed to the customer's mobile, with the token, items, total and tracking link filled in. The restaurant presses Send.
- Order card (Orders tab): "🧾 Bill" prints that order's bill from its token. This works for table, common-QR, counter, WhatsApp and delivery orders. Table totals stay on the Tables tab.
- Place a new order from a phone while the owner dashboard is open on any tab (Orders, Tables, Menu or Settings). Within about 10 seconds a "New order · Token #" card asks "Print the bill now?". Yes opens the bill and prints it; No closes it. Old open orders at sign-in are not asked about.
- Rejected lines do not appear on the order bill.
