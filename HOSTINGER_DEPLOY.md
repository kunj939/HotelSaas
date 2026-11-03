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

Open phpMyAdmin for this database and import `database/schema.sql` (bundled inside the ZIP). It creates the tables `restaurants`, `users`, `categories`, `menu_items`, `orders`, `order_items`, `subscription_payments`, `audit_logs`, `auth_attempts`.

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
];
```

Generate strong random secrets locally, e.g. run `python -c "import secrets; print(secrets.token_hex(32))"` **twice**. Do not paste database passwords to ChatGPT or commit your config to GitHub.

`api/.htaccess` denies web access to config files. Verify by opening `https://menu.yourdomain.com/api/config.local.php` — it must yield **403 Forbidden**; if it does not, take the app offline and fix Apache rules immediately.

## 4. Create the first Super Admin

Open `https://menu.yourdomain.com/api/install_once.php` in your browser. Enter the installation key from config, admin name, email and a strong password of at least 14 characters. This only works if no Super Admin exists.

`install_once.php` is no longer in the GitHub repo. Use the copy in the uploaded ZIP to create the admin. **Then delete `api/install_once.php` from the server** and clear `install_key` from `config.local.php`.

Open `https://menu.yourdomain.com/admin` and sign in. The home page has no Super Admin button, so type this URL directly.

## 5. Add a restaurant

Super Admin → Restaurants → Add Restaurant. Enter the restaurant name, owner email, temporary password (12+ characters), table count, monthly subscription fee (₹599 or agreed ₹499). A 7-day trial is created.

Restaurant owner signs in at `index.html#/owner` and changes their password under Settings. Owner adds menu categories and dishes, sizes, extras, their own photos or licensed photo suggestions. Owner sets their logo, cover, theme and number of tables. In Settings → QR Code Generator, choose Common QR or Table QR, print and place QR cards.

## 6. Turn on subscription

Super Admin → Restaurants → Manage → set **Active**, choose the correct expiry date, save. Record any received UPI subscription payment separately under Record Payment. Saving a payment does not automatically extend expiry; update the expiry explicitly.

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
| No Wikimedia suggestions | `curl` or outbound Wikimedia access unavailable; owner can upload original photo |
| New orders not heard | Enable sound by clicking button and keep dashboard visible; browser background sound can be restricted |
| Wrong colours | Settings → Save restaurant settings, then refresh customer screen |

## Security note

No integration can guarantee zero missed orders under network failures, browser sleep, disabled notifications or shared-host limits. Staff should watch dashboard, reconcile pending orders, and maintain a manual fallback until the system is field-tested.
