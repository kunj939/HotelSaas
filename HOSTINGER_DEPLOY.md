# Hostinger Premium — Installation (step by step)

## 0. Choose a website location

Create a dedicated domain or subdomain for the QR SaaS (for example `menu.yourdomain.com`). **Do not overwrite Yaitro or any existing website.** Point that site's document root to its own `public_html` folder.

Check hPanel → PHP Configuration. Use a supported PHP 8.2+ with `pdo_mysql`, `mbstring`, `fileinfo`, and preferably `curl` enabled. Turn on SSL. You need access to MySQL Databases and phpMyAdmin.

## 1. Create a MySQL database

Hostinger hPanel → Databases → MySQL Databases. Create one database and user. Note:
- DB host (usually `localhost`, confirm Hostinger panel)
- Exact database name
- Exact DB user
- DB password

Open phpMyAdmin for this database and import `database/schema.sql` (bundled inside the ZIP). It creates the tables `restaurants`, `users`, `categories`, `menu_items`, `orders`, `order_items`, `subscription_payments`, `autopay_mandates`, `payu_transactions`, `token_counters`, `restaurant_access`, `audit_logs`, `auth_attempts`.

**Upgrading an existing database?** Don't re-import `schema.sql`. Run each file in `database/migrations/` that you have not run yet, oldest first, in phpMyAdmin → SQL.

## 2. Upload files

Unzip locally. Upload **the contents inside `public/`**, not the parent folder, to the target website's `public_html/`. The web root should contain `index.html`, `api/`, `assets/` and `uploads/`. Preserve `.htaccess` hidden files and the `uploads/` directory.

Recommended permissions: files `644`, directories `755`. Ensure PHP process can write uploaded image files to `uploads/`. The `uploads/.htaccess` blocks executable PHP uploads. Never set wide-open permissions unless Hostinger support explicitly instructs.

## 3. Configure app

In `public_html/api/`, copy `config.example.php` → `config.local.php` and edit:

```php
return [
 'db_host' => 'localhost',
 'db_name' => 'your_full_hostinger_db_name',
 'db_user' => 'your_full_hostinger_db_user',
 'db_password' => 'your_real_database_password',
 'app_url' => 'https://menu.yourdomain.com',
 'app_secret' => 'A_UNIQUE_RANDOM_HEX_STRING_AT_LEAST_64_CHARACTERS',
 'install_key' => 'ANOTHER_UNIQUE_RANDOM_SECRET_AT_LEAST_24_CHARACTERS',
 'payu' => ['mode' => 'test', 'key' => 'YOUR_PAYU_KEY', 'salt' => 'YOUR_PAYU_SALT'],
];
```

Generate strong random secrets locally, e.g. run `python -c "import secrets; print(secrets.token_hex(32))"` **twice**. Do not paste database passwords to ChatGPT or commit your config to GitHub.

`api/.htaccess` denies web access to config files. Verify by opening `https://menu.yourdomain.com/api/config.local.php` — it must yield **403 Forbidden**; if it does not, take the app offline and fix Apache rules immediately.

## 4. Create the first Super Admin

Open `https://menu.yourdomain.com/api/install_once.php` in your browser. Enter the installation key from config, admin name, email and a strong password of at least 14 characters. This only works if no Super Admin exists.

The installer is included in this repository and refuses to run once a Super Admin exists. **Then delete `api/install_once.php` from the server** and clear `install_key` from `config.local.php`.

Open `https://menu.yourdomain.com/admin` and sign in. The home page has no Super Admin button, so type this URL directly.

## 5. Add a restaurant

Super Admin → Restaurants → Add Restaurant. Enter the restaurant name, owner email, temporary password (12+ characters), ordering setup (common QR, per-table QR, or counter tokens), table count, monthly subscription fee (₹599 by default). A 7-day trial is created.

Restaurant owner signs in at `index.html#/owner` and changes their password under Settings. Owner adds menu categories and dishes, sizes, extras, their own photos or licensed photo suggestions. Owner sets their logo, cover, theme and number of tables. In Settings → QR Code Generator, choose Common QR or Table QR, print and place QR cards.

## 5a. Counter mode, tokens and multiple outlets

- **Small restaurant without tables:** owner → Settings → QR code type → **Counter · No tables, token only**. Print the single QR for the counter.
- **Tokens:** every order gets a daily token (restarts at midnight in the `timezone` set in `config.local.php`, default `Asia/Kolkata`). In Orders, **📣 Call token** marks the order ready and rings the guest's phone. The guest must keep the order page open, and phones only play sound after the guest has tapped the page once (placing the order counts).
- **One owner, several restaurants:** create each restaurant as usual, then Super Admin → Restaurants → Manage (the extra restaurant) → **Other owner logins with access** → enter the main owner's login email → Link. The owner then switches outlets from the top bar and can view **All outlets** in Orders. 2 outlets per login are included; each extra outlet adds ₹200/month to the bill of the owner's own restaurant.

## 5b. Stopping or deleting a restaurant

Super Admin → Restaurants → Manage → **Danger zone**:
- **Stop restaurant:** QR ordering closes and the owner/staff are logged out at once. **Resume** reopens it. Nothing is deleted.
- **Download all data:** a JSON backup of the menu, orders, payments and logins (no passwords). Keep it before deleting.
- **Delete restaurant:** hides it from customers and owners and stops autopay, but keeps every record. **Restore** brings it back.
- **Delete permanently** (only after Delete): type the restaurant's URL slug to confirm. Erases its menu, orders, payments, uploaded photos and its own owner/staff logins. Cannot be undone.

## 5c. Subscription payments by UPI (no PayU needed)

1. Super Admin → **Subscriptions** → **UPI for subscription payments** → enter your UPI ID (e.g. `yourname@okaxis`) and the name to show → Save.
2. Owners now see **Pay by UPI** in Billing: a QR with the exact amount (monthly ₹599, or 6 months upfront 6 × ₹549, plus the ₹500 setup fee the first time). They pay and enter the UTR.
3. You get a "UPI payments waiting" alert on the Overview. Check the UTR and amount in your bank/UPI app, then **Approve** (extends the subscription and records the receipt) or **Reject** (the owner sees your reason).

## 6. Subscription payments (PayU)

Prices are set in `api/payu.php`: ₹599/month without autopay, ₹549/month with 6-month autopay, and a ₹500 one-time setup fee added to a restaurant's first PayU payment.

1. Create a PayU business account and ask PayU to enable **Recurring Payments / Standing Instructions (SI)** for UPI Autopay and cards. Without SI, only the monthly plan works.
2. Put the PayU **test** key and salt in `config.local.php` (`'mode' => 'test'`). `app_url` must be the real HTTPS address because PayU returns to `app_url/api/payu_return.php`.
3. In the PayU dashboard, add the webhook URL `https://menu.yourdomain.com/api/payu_return.php?webhook=1` for payment success and failure. This settles payments even if the owner closes the browser. **Sharing a PayU account with another website?** Do not replace that website's webhook; skip this step and rely on the cron below (run it hourly) to settle payments. Also ask PayU to add this domain to the account's allowed websites, and set `'merchant_name'` to the account's business name so owners know what they'll see at checkout.
4. Hostinger hPanel → Advanced → Cron Jobs → add a job that runs every hour (safe: each autopay month is notified once and charged at most once a day; NPCI asks to avoid 10:00–13:00 and 17:00–21:30 for UPI autopay, and debits run on the first hourly run of the due day):
   `php /home/YOUR_USER/public_html/public/api/payu_cron.php`
   (If you uploaded only the contents of `public/`, the path is `.../public_html/api/payu_cron.php`.)
   It sends the pre-debit notice about 3 days before each autopay month, charges the month and settles pending payments. Without it, autopay never charges.
5. Test in PayU test mode: pay the monthly plan once, then the autopay plan, and check that Billing shows the payment and the new expiry. Then switch to live keys and `'mode' => 'live'`.

The owner pays from **Billing** in their dashboard. Each successful payment adds one month after the current expiry. After 3 failed autopay attempts, autopay stops and the plan goes back to monthly. An owner can also stop autopay, which stops future charges; the mandate itself is revoked from their UPI app or bank.

**Paid offline?** Super Admin → Restaurants → Manage → set **Active** and the expiry date, tick **Setup fee paid** if it was paid, and record the payment under Record Payment. Recording a payment by hand does not extend expiry; update it explicitly.

## 7. Safety and go-live test

- Upload a photo and confirm it loads.
- Create one dish priced at ₹100 and one Half/Full dish.
- Scan QR using two real phones. Confirm menu and restaurant theme.
- Add dishes to cart. At checkout select Table 3; confirm order once.
- Restaurant dashboard → Live should show the order within ~8 seconds if open and visible.
- Accept → Prepare → Ready → Served; customer tracks state.
- Place additional items for the same table, make sure it creates a new order.
- Try double submit/retry; check no duplicate order.
- Check order amounts in MySQL and owner analytics.
- Toggle sold-out dishes: cannot order them.
- Test wrong table, closed ordering, expired subscription, mobile network loss, credentials and access isolation.
- Configure backups and inspect logs; do not rely on a single hosting backup.

## Capacity and monitoring

This is small shared-hosting-friendly software, **not a guaranteed 50-restaurant capacity rating**. Each active restaurant dashboard polls about every 8 seconds. A total of 50 restaurants can produce hundreds of thousands of requests monthly if open throughout service hours. Monitor hPanel CPU/entry processes, PHP errors, DB connections, page loading and order delivery. Upgrade hosting when measured limits require it.

## Common problems

| Symptom | Fix |
|---|---|
| `Setup required` | Create `api/config.local.php` and check file placement |
| `Database request failed` | Recheck MySQL credentials, import schema, enable PDO MySQL, read PHP error logs |
| `403 config file` | This is correct — credentials must remain private |
| QR opens wrong website | Change `app_url` in config and regenerate / reprint QR |
| Uploaded photo fails | Check writable `uploads/`, 2.5 MB limit and `fileinfo` |
| Billing says online payment is not set up | Add the `payu` key and salt to `config.local.php` |
| Paid but expiry did not change | Check PHP error logs. The webhook or the daily cron settles it; make sure both are set up |
| No Wikimedia suggestions | `curl` or outbound Wikimedia access unavailable; owner can upload original photo |
| New orders not heard | Enable sound by clicking button and keep dashboard visible; browser background sound can be restricted |
| Wrong colours | Settings → Save restaurant settings, then refresh customer screen |

## Security note

No integration can guarantee zero missed orders under network failures, browser sleep, disabled notifications or shared-host limits. Staff should watch dashboard, reconcile pending orders, and maintain a manual fallback until the system is field-tested.
