# Security & Production Limitations

**Built-in safeguards:** server-side recomputation of item/size/add-on prices; transaction and idempotency key per order; rate-limit by secret-keyed IP hash; random tracking token; table validation; owner tenant isolation in every SQL menu/orders/settings operation; PHP sessions with HttpOnly and SameSite Lax; CSRF token on state changes; password hashing and login rate limiting; MIME-checked 2.5 MB photo uploads with random filenames, no SVG uploads; soft deletion of menu dishes to preserve historical orders; no OTP. Each order stores the customer's name (required) and an optional 10-digit mobile number, used only to call the customer about that order. Owners should delete old order records according to their own privacy obligations.

**Extra work needed before serious production use:** deploy on HTTPS, confirm private config not served; perform a complete penetration/security review; implement automated, tested offsite database backups; alerting independent of dashboard; host health monitoring; privacy policy and data retention schedule; measure load under restaurant peak hours; restore tests; optional stronger abuse/fake-order mitigation, especially with common QR (guests can type any valid table). Cross-check payment records separately; order value does not prove cash collection.

**Browser alerts:** dashboard polling runs around every 8 seconds while the live orders tab is open. A sound needs a human click to enable, and browser/OS can suppress it. This is not guaranteed push notification or POS-grade kitchen hardware.

**Token calls:** the guest's phone only rings while the order page is open, and browsers allow sound/vibration only after the guest has tapped the page (placing the order counts; iPhones do not vibrate from web pages). It is not an SMS or push notification, so staff should still call the token out loud.

**Tables:** common QR prompts table number 1–20 at checkout by default; restaurant may change count 1–500. Per-table QR includes table id in URL, but physical QR images can still be copied. Order acceptance by staff remains important.

**Photos:** all 500 bundled graphics are deliberately labelled as illustrations, not authentic photos. Owner photos remain their responsibility. Wikimedia results are filtered for common reuse licences but require human review for accurate depiction, rights, attribution and suitable representation. Do not automatically assume images portray the same recipe the restaurant serves.

**Payments:** diner payment is handled directly with restaurant staff. Restaurant subscriptions are paid through PayU. Prices are decided on the server, and a payment counts only after PayU's `verify_payment` API confirms it with the matching amount, so posted browser data can't fake a payment. Autopay depends on the daily cron, PayU Standing Instructions being enabled on the account, and the bank or UPI app honouring the mandate. Reconcile with the PayU settlement report every month. Financial analytics intentionally avoid claiming cash actually received.

**No live infrastructure QA in ZIP:** PHP syntax, QR generation and static catalog validation checked; PHP MySQL API has not been exercised against a real MySQL instance or Hostinger in this environment. Complete field testing required before opening to public diners.
