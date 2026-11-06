# Refund Integration Checklist

Use this checklist for the first real merchant run. Do not test refunds against a production order unless the merchant account owner explicitly approves it.

## Release Candidate

- Build package: `composer build:zip`
- Verify package: `composer verify`
- For the unpublished 1.3.8 candidate, install `build/npcink-pay-refund-1.3.8.zip`, not a raw source archive.
- Historical release records identify 1.3.7; the live directory version was not rechecked in this remediation. Record live results only for the exact versioned ZIP and merchant credentials being tested.
- For a later release, install the exact versioned ZIP printed by `composer build:zip`; record its version and SHA-256 before testing.
- Confirm `vendor/autoload.php` exists in the installed plugin.
- Record the package SHA-256 before installing it.
- Run `wp eval-file /absolute/path/to/npcink-pay-refund/bin/check-database-locks.php --path=/explicit/site` against the intended database routing. It uses session temporary tables and locks, with no gateway requests or persistent plugin data writes. Temporary-table privilege is required. Archive its 12 checks; a passing session does not certify future proxy/routing behavior.
- As an administrator, inspect every pending/reconciliation queue page before upgrading. Preserve original request IDs, amounts, owners and provider results. Missing legacy fields require merchant reconciliation; do not reset attempts or delete locks to create a new request ID.

## Historical 1.3.7 Local WordPress Verification

- Verified on 2026-09-04 with WordPress 7.1 and MySQL 8.0.35 in an isolated temporary database.
- Confirmed installation from the exact 1.3.7 ZIP, old-table schema upgrade (`n_user_id`), activation, deactivation, reactivation, and uninstall cleanup.
- Confirmed administrator/refund-operator/subscriber capability behavior with `bin/smoke-admin.php`.
- Confirmed WP-CLI Plugin Check returned `No errors found`.
- This evidence covers the local WordPress lifecycle only; it does not replace real Alipay or WeChat merchant verification.

## Candidate evidence record (required before release sign-off)

1.3.8 is not published or merchant-validated. The historical 1.3.7 manual confirmation has no attached version/hash/request log in this repository; preserve it as a historical statement, not evidence for this candidate.

Record one entry per channel, without secrets or full buyer identifiers:

```text
UTC time / operator:
Candidate version / ZIP SHA-256 (from .zip.sha256):
Git commit / tracked diff hash / source_tree_sha256 (from .manifest.json):
Database preflight evidence / proxy and read-write routing reviewed:
WordPress / PHP / MySQL / site timezone:
Channel / masked merchant and app identifiers:
Merchant out_trade_no (masked) / out_request_no or out_refund_no (masked):
Request full-refund amount / verified response amount / currency:
Provider status / payment timestamp source timezone / refund timestamp:
First submission outcome / repeated request outcome:
Interrupted-response recovery / same-ID retry outcome:
Local audit insert / forced local-write failure recovery outcome:
Evidence location / reviewer / unresolved discrepancies:
```

## Alipay

- Save APP ID, application private key, and Alipay public key.
- Run the Alipay configuration check from the settings page.
- Query using the merchant order number (`out_trade_no`), not the platform transaction ID, within the allowed refund window.
- Submit one full refund with a unique reason.
- Confirm the response shows success or a clear gateway error.
- Query the successful refund again by its original `out_request_no`; confirm the returned order number, refund request number, amount and `refundStatus=REFUND_SUCCESS` all match. Missing or unknown status must remain uncertain.
- Repeat the same refund request and confirm the duplicate-refund lock blocks it.
- Confirm a refund record is written with amount, time, order number, operator, type, and reason.
- In a controlled non-production test, interrupt the refund response after submission. Confirm the next operation queries the original `out_request_no` before any retry and reuses the original amount and reason.
- Confirm the admin reconciliation notice remains visible while the Alipay result is uncertain and disappears after provider confirmation plus a successful local audit write.

## WeChat Pay

- Save merchant ID, merchant API certificate serial number, merchant private key, WeChat Pay public key ID or platform certificate serial number, and WeChat Pay public key or platform certificate.
- Run the WeChat configuration check from the settings page.
- Query using the merchant order number (`out_trade_no`), not the platform transaction ID, within the allowed refund window.
- Submit one full refund with a unique reason.
- Confirm `PROCESSING`, `SUCCESS`, `CLOSED`, and `ABNORMAL` responses show distinct operator messages when they occur.
- Repeat the same refund request and confirm the duplicate-refund lock blocks it.
- Confirm a refund record is written with amount, time, order number, operator, type, and reason.
- In a controlled non-production test, interrupt the refund response after submission. Confirm the pending state and lock remain, and automatic status checks do not submit another refund.
- Confirm a manual retry is allowed only after the signed refund query returns HTTP 404 with `RESOURCE_NOT_EXISTS`, and that it reuses the original `out_refund_no`, amount, and reason.
- Confirm `MCH_NOT_EXISTS`, signature failures, timeouts, and `SYSTEM_ERROR` never trigger a refund resubmission.

## Regression Notes

- Leaving secret fields empty must retain existing saved secrets.
- Refund specialists must be author-or-above non-admin users.
- Non-admin refund specialists should be able to use refund operations and see only their own refund records; the plugin must not block unrelated WordPress admin pages.
- CSV export should open without formula execution for values starting with `=`, `+`, `-`, or `@`.
- Real merchant execution is operator-owned evidence. A passing local/CI suite must not be recorded as a successful live refund.
