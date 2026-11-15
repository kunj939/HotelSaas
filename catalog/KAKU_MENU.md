# Kaku starter menu

This is **Kaku 2.0's prepared starting menu**, not a universal restaurant default. During Kaku account creation, explicitly select **Kaku 2.0 — prepared menu with photos**. That account receives **194 dishes in 28 categories**, with INR prices, portions, vegetarian labels and bundled representative food photos. The default for other accounts is an empty menu with five categories. Owners use the normal menu editor to change prices, replace photos, add/remove dishes, and mark items sold out or available. Existing restaurants are not modified, and logging in never re-seeds a menu.

## Sources and transcription

Transcribed from the two user-supplied one-page PDFs:

- `Kaku 2.0 Pizza & Restaurant - pdf1.pdf`: Indian meals, breads, thalis, beverages and sweets.
- `Kaku 2.0 Pizza & Restaurant - pdf2.pdf`: pizzas, sandwiches, burgers, pasta, Maggi, fries, wraps, appetizers and snacks.

The PDFs contain photographs of the printed menu. Their Google Maps headers, delivery promotions, phone number, branding and QR codes are not restaurant-account defaults or application instructions.

- Pizza prices use Small / Medium / Large; labelled curry and rice prices use Half / Full.
- Plain Roti is Tawa ₹8 / Tandoor ₹12; Butter Roti is Tawa ₹10 / Tandoor ₹14.
- Sarson Saag's two printed lines become one dish with Half ₹130 / Full ₹200.
- Manchurian offers Gravy and Dry, each ₹230, as printed.
- The tandoori chaap slash prices and Dahi ₹60/₹100 have **no printed portion labels**. Half / Full is the starter convention; the owner should verify these labels before publishing their QR.
- The second “Kofta” heading contains mushroom dishes and is labelled “Mushroom Items” in the app.
- Spelling and category names are lightly normalized for searching. “Maxicon Hunger” and “Kadi Chaap” retain the source's wording. Pizza and milkshake names include the dish type for clarity outside their category.
- Tiny beverage volumes and thali ingredient lists are omitted where the source is difficult to read. No unverified ingredients, extras or prices are invented.

## Photos

Food photos are representative Wikimedia Commons images, **not photos taken at Kaku**. Related variants may share a photo. The owner can replace them with actual dish photos. Customer cards and dish details show the photographer, licence, source link and representative-photo label. The originals' authorship and licences are retained in `public/assets/photos/kaku/credits.json`; resized JPEG derivatives retain the original licence. Existing bundled photos are referenced without duplicating files.

## Implementation and updates

- `tools/build_kaku_menu.py` contains the reviewed names, prices and photo assignments. Run it to regenerate `public/assets/kaku_menu.json` after changing the source or credits.
- `public/api/menu_template.php` inserts categories and items within the same transaction as restaurant and owner creation. Any missing template photo or database failure rolls back the account creation.
- The creation API seeds this menu only when `menu_template` is explicitly `kaku-v1`. Omitted values and `none` create an empty menu; unknown template values are rejected before creating any records.
- Seed insertion is restricted to an empty menu and never runs on login or menu reads.
- Each account has its own menu and category IDs; only the static photo files are shared.
- No database migration is required. Upload the updated API, frontend, JSON and photo assets together.
