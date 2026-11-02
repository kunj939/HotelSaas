# QA Test Plan

## Automated static checks

Run:

```bash
python tests/test_static.py
php -l public/api/index.php
node --check public/assets/app.js
node --check public/assets/qr.js
```

## Manual end-to-end use cases

1. Onboard two restaurants A and B. Owners cannot see or modify data from the other.
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
14. Register a ₹499 first-customer plan, and a ₹599 standard plan. Expired subscriptions pause new ordering without deleting menus.
15. Upload a malicious disguised PHP/SVG or >2.5 MB: must reject. Config file must be web-inaccessible.
16. Test mobile UI at 320, 375, 414 px width and on poor network; no invisible CTA, duplicate orders or lost cart.
17. Do load test with representative 10/30/50 restaurants before promising shared-hosting capacity.
