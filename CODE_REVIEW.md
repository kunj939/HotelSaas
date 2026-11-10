# Restaurant setup and code review

This review focused on installation, restaurant onboarding, settings validation and concurrent order actions. It is not a claim that every workflow has been verified.

## Confirmed problems fixed

| Problem | Result |
| --- | --- |
| Deployment guide required an installer absent from the repository | Included a key-protected first-admin web installer. It refuses missing, short or placeholder keys and existing administrators; a database advisory lock serializes concurrent installations. Delete it after use. |
| Creating a restaurant always selected common QR | Onboarding now offers common QR, per-table QR and counter service, with server validation. Existing callers default to common QR. |
| Calling a token could reset a concurrently served order to ready | Conditional update permits only accepted, preparing or ready orders; stale requests receive HTTP 409. |
| Settings allowed an empty restaurant name | Server rejects blank names before updating settings. |
| Setup failures required manual investigation | `php tools/check_setup.php` checks PHP extensions, private configuration, uploads, database access, representative schema columns and installer cleanup without printing secrets. This is a readiness aid, not a complete schema verifier. |

## Restaurant use cases to verify before launch

Use `TEST_PLAN.md` for the detailed manual checks. Run these against a disposable database and both owner and guest browsers:

- Fresh installation: missing config, bad DB credentials, missing schema, invalid installation key, duplicate admin submissions, completed installer lockout and installer deletion.
- Dine-in with common QR: valid table, missing/out-of-range table, table count changes and copied links.
- Per-table QR: correct table, mismatched checkout table and QR cards after table configuration changes.
- Counter/takeaway: no table prompt, daily token assignment, repeated token calls and midnight token reset. This mode supports collection at the counter; delivery is not implemented.
- Menu: empty menu, categories, sizes, extras, sold-out items, removed items, changed prices, free extras, uploads and missing images.
- Checkout: required customer name, optional phone, rejected invalid phone, notes, cart limits, server prices, duplicate retries and dropped connections after submission.
- Staff operations: accept, prepare, ready, serve, reject, token calls, simultaneous updates, expired sessions and browser notification restrictions.
- Access: different tenants, linked outlets, outlet switching, suspended/deleted restaurants, restored restaurants and staff permission restrictions.
- Subscription: trial expiry, paused ordering, renewal, pending UPI review, duplicate UTR and verified PayU results. Test payment flows in the provider sandbox.

## Remaining product gaps and limits

There is no automated database-backed end-to-end suite. This environment lacks PDO MySQL and a configured database, so installation success, schema queries and concurrent SQL behavior remain unverified here. The setup checker correctly reports those missing prerequisites.

Opening hours are display text; staff must pause ordering manually. There is no delivery/address workflow, inventory deduction, kitchen printer integration, split bills, tax invoicing, diner online payment, refunds or guaranteed background push notifications. Staff accounts have a database role but no dedicated account-provisioning screen. These need separate product decisions and implementation before promising those capabilities to restaurants.
