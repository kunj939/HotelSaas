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
14. Expired subscriptions pause new ordering without deleting menus.
15. PayU test mode, restaurant that has not paid the setup fee: monthly plan charges ₹1,099, and autopay charges ₹1,049. After a successful payment, expiry moves one month past the old expiry, setup fee shows Paid, and the payment appears in Billing and Super Admin.
16. Cancel a PayU checkout: Billing shows the failure toast and nothing changes. Posting a fake `txnid` to `api/payu_return.php` changes nothing.
17. Autopay: set the mandate's `next_debit_date` to today+2 and run `php api/payu_cron.php`. The first run sends the pre-debit notice; on the notified date the next run charges ₹549 and moves to month 2 of 6. After month 6 the plan returns to ₹599.
18. Staff accounts can view Billing but cannot pay or stop autopay.
22. Tokens: place 3 orders in one restaurant → tokens 1, 2, 3; another restaurant starts at 1; the next day starts at 1 again. Two phones ordering at the same moment never get the same token.
23. Call token: accept an order, press 📣 Call token → status becomes Ready and the guest's phone (page open, tapped once) shows the token, rings and vibrates, also while browsing the menu. "Call again" rings again. Reloading the page does not ring for an old call.
24. Counter mode: no table question at checkout, owner card shows "Counter", single QR printed. Switching back to Common/Table mode restores the table question.
25. Multiple outlets: link restaurant B to owner A. A sees the outlet switcher, menus/settings change with the selected outlet, and All outlets shows both with the restaurant name. A cannot open or change orders of an unlinked restaurant C. Linking a 3rd outlet adds ₹200 to A's own Billing.
26. Super Admin Danger zone: Stop → customers can't order, a logged-in owner is locked out on the next click; Resume restores. Download gives a JSON with the full menu and no password hashes. Delete → customer link shows not found and the owner can't log in; Restore brings it back. Delete permanently refuses until the restaurant is deleted and the exact slug is typed, then removes it and its owner login.
19. Upload a malicious disguised PHP/SVG or >2.5 MB: must reject. Config file must be web-inaccessible.
20. Test mobile UI at 320, 375, 414 px width and on poor network; no invisible CTA, duplicate orders or lost cart.
21. Do load test with representative 10/30/50 restaurants before promising shared-hosting capacity.
