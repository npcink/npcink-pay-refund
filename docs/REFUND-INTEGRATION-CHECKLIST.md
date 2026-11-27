# Refund Integration Checklist

Use this checklist for the first real merchant run. Do not test refunds against a production order unless the merchant account owner explicitly approves it.

## Release Candidate

- Build package: `composer build:zip`
- Verify package: `composer verify`
- To reproduce the original 1.3.10 candidate validation, use the existing `build/npcink-pay-refund-1.3.10.zip` and verify it against the [recorded candidate SHA-256](CLOSEOUT-AND-RETROSPECTIVE-1.3.10.zh-CN.md#验证与制品). Do not rebuild it from a later checkout or substitute a source archive. The released 1.3.9 artifact remains unchanged.
- For the follow-up 1.3.11 candidate, use the attached ZIP from the [GitHub pre-release record](RELEASE-1.3.11.zh-CN.md) or its frozen local copy. Verify it against its own manifest and SHA-256. This is not a stable or WordPress.org release; do not carry the original 1.3.10 sign-off forward.
- The default local build output for this version is `build/npcink-pay-refund-1.3.11.zip`. A new file at that path is not the frozen candidate unless its SHA-256 matches the pre-release ZIP.
- `composer verify` and `composer verify:local` both build a ZIP and can overwrite a same-version artifact. Run package checks in a separate checkout when the current build directory holds a frozen candidate.
- Record live results only for the exact versioned ZIP and merchant configuration being tested.
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

## Candidate evidence record (required before stable release sign-off)

1.3.9 was published on 2026-11-06 after the user separately confirmed WeChat and Alipay merchant validation for the original package. See RELEASE-1.3.9.zh-CN.md for the exact SHA-256, confirmation scope and remote publication evidence. No order references or amounts were supplied; do not invent them. The historical 1.3.7 manual confirmation remains historical evidence only.

On 2026-11-26, the user confirmed both channels passed merchant validation for the original 1.3.10 candidate. See its [acceptance record](CLOSEOUT-AND-RETROSPECTIVE-1.3.10.zh-CN.md#商户验收补记) for the evidence scope. This does not authorize release or sign off on any subsequent code change.

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
- For a signed response with `fund_change=N` or no `fund_change`, verify that no successful refund record is written. Check the original request number before any retry; never create a replacement number to bypass a conflict.
- Query the successful refund again by its original `out_request_no`; confirm the returned order number, refund request number, amount and `refundStatus=REFUND_SUCCESS` all match. Missing or unknown status must remain uncertain.
- Repeat the same refund request and confirm the duplicate-refund lock blocks it.
- Confirm a refund record is written with amount, time, order number, operator, type, and reason.
- In a controlled non-production test, interrupt the refund response after submission. Confirm the next operation queries the original `out_request_no` before any retry and reuses the original amount and reason.
- Confirm the admin reconciliation notice remains visible while the Alipay result is uncertain and disappears after provider confirmation plus a successful local audit write.

### H2: Alipay request-number conflict evidence

1. Prefer an existing incident. Otherwise, only the authorized merchant operator may use a dedicated test order in a controlled environment. Never reproduce against a customer order, create a replacement request number, or retry to force a conflict when the first outcome is uncertain.
2. Record the candidate SHA-256, UTC timestamps, masked merchant/order/request references, refund amount, whether the first response was signature-verified, its business code/subcode, `fund_change`, and whether the first request was rejected or its outcome is unknown. For an existing retry, record which fields changed (in particular whether only the reason changed), not the actual reason.
3. Check the original `out_request_no` using the provider's refund query. Record whether the response was verified, its code/status, whether order/request/amount matched, and whether local reconciliation completed. Do not paste keys, buyer details, raw gateway bodies or full identifiers into the repository.
4. Classify as confirmed conflict, no conflict observed in the tested case, or inconclusive. A timeout, an unverified response, or an example using a different request number does not answer H2. Do not change retry rules without reviewing the merchant evidence separately.

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
