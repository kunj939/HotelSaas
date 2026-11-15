# Kaku starter menu

This is **Kaku 2.0's prepared starting menu**, not a universal restaurant default. During Kaku account creation, explicitly select **Kaku 2.0 — prepared menu with photos**. That account receives **194 dishes in 28 categories**, with INR prices, portions, vegetarian labels and bundled representative food photos. The default for other accounts is an empty menu with five categories. Owners use the normal menu editor to change prices, replace photos, add/remove dishes, and mark items sold out or available. Existing menu contents are never re-seeded, and logging in does not restore deleted dishes.

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

The menu contains **194 distinct locally hosted food images**. All were visually reviewed against dish names and the supplied menu. **84 mismatched or unclear images were replaced with individually generated AI food images; 110 matching internet photos were retained.** These are representative illustrations/photos, not photographs taken at Kaku. House recipes and actual presentation can differ. Owners can replace them with their own dish photos.

`catalog/kaku_photo_audit.json` records a decision and reason for all 194 dishes, plus the exact generation prompts and output paths for replacements. Images were generated with the built-in `image_gen` tool and converted to optimized JPEGs. Each replacement has its own image; no image is reused for another dish. The photos follow the named toppings and visible printed thali components without changing menu descriptions, prices or portions.

`public/assets/photos/kaku/premium.json` records current photo provenance. Retained photos keep their photographer, source and licence. AI images carry `AI-generated representative image` metadata, no fabricated photographer/source link or Creative Commons claim, and a visible customer-facing AI label. Historical internet-photo credits remain in `catalog/kaku_photo_sources_v2.json` and `credits.json`; older assets remain available for existing saved URLs.

## Implementation and updates

- `tools/build_kaku_menu.py` contains the reviewed names, prices and photo assignments. Run it to regenerate `public/assets/kaku_menu.json` after changing the source or credits.
- `public/api/menu_template.php` inserts categories and items within the same transaction as restaurant and owner creation. Any missing template photo or database failure rolls back the account creation.
- The creation API seeds this menu only when `menu_template` is explicitly `kaku-v1`. Omitted values and `none` create an empty menu; unknown template values are rejected before creating any records.
- Seed insertion is restricted to an empty menu and never runs on login or menu reads.
- Each account has its own menu and category IDs; only the static photo files are shared.
- Existing Kaku starter photos refresh on menu reads using `public/assets/kaku_photo_upgrade.json`. All four photo fields must still match a known starter version from `catalog/kaku_photo_upgrade_legacy.json` or `catalog/kaku_photo_upgrade_v2.json`. Uploaded, linked, removed, renamed or custom-credited photos are preserved. This only changes returned photo metadata; it never writes to the database or resets prices, availability, specials or deleted dishes.
- Photo assets use a consistent card frame and an uncropped detail preview. The menu builder requires all 194 photo assignments; tests reject duplicate image files and reused internet source URLs, and require honest AI provenance.
- No database migration is required. Upload the updated API, frontend, JSON and photo assets together.
