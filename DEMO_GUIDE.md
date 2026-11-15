# Restaurant demo: 5 minutes

1. **Kaku's prepared menu:** Add Restaurant → choose “Kaku 2.0 — prepared menu with photos”. Open the customer preview or scan its QR. It has 194 editable dishes; other restaurants start with empty menus.
2. **Owner-picked recommendations:** Menu → Edit → “Feature as Chef's Special”. Refresh the customer menu to show the special first and in its own filter. Mark it sold out to show ordering is blocked. These are owner-picked specials, not invented bestsellers.
3. **Checkout flexibility:** Settings → Customer details. Name and mobile independently support Do not ask / Optional / Required. Off fields are not stored with new orders, even if sent by an old browser. Details appear in the restaurant's authenticated order view. This is for handling orders, not marketing consent.
4. **Live order demo:** Select a size, add to cart, choose a table and confirm. Show the order with customer details in the owner dashboard, then Accept → Preparing → Ready. Use a clearly identified test order and coordinate with staff when using a real restaurant account.
5. **Website enquiries:** Welcome page or contact icon → Location, Menu & prices, Order food on WhatsApp, Birthday & group party, Corporate & bulk orders. WhatsApp opens a composed enquiry which the customer sends. The restaurant confirms availability, delivery options and prices. Chats do not automatically create app orders or take payments.

Kaku's prepared account uses the supplied WhatsApp number `+91 90535 73091`, email `kakupizza17@gmail.com`, and menu address. Every owner can change the contact details or hide the service panel in Settings. Other accounts have this panel off by default.

## Existing-site upgrade

**Party posters:** Dashboard → Download party poster creates a 1080 × 1350 PNG with the restaurant name, contact, address and menu QR in one click. Posters opens birthday, group-party and corporate templates with editable heading, details and offer text. Preview before sharing; no paid image service or automatic publishing is involved.

**Plan pricing:** Super Admin → Plan Pricing changes the default monthly fee for new accounts. Restaurants → Manage changes an individual restaurant's agreed fee. Monthly billing uses each restaurant?s agreed fee. Admin can also change the per-month price for new 6-month autopay registrations and upfront UPI purchases. Active mandates, pending payments and recorded receipts keep their original amounts. Setup and extra-outlet charges remain separate.

Run these migrations **once** in the application's database before uploading the updated API:

- `database/migrations/2026-11-15_demo_features.sql`
- `database/migrations/2026-11-15_contact_options.sql`
- `database/migrations/2026-11-15_plan_pricing.sql`

First apply any outstanding earlier migrations (November 2 through November 6) in date order; these add customer details, billing, tokens, outlets and UPI. Fresh installations use the updated `database/schema.sql` and skip migrations.

Then upload the contents of the package's `public/` folder to the existing restaurant website root. Preserve `api/config.local.php`; the package contains no credentials. Existing menus are not replaced. This update has been tested locally; Hostinger deployment has not been performed.
