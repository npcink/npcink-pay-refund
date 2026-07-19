# Refund Integration Checklist

Use this checklist for the first real merchant run. Do not test refunds against a production order unless the merchant account owner explicitly approves it.

## Release Candidate

- Build package: `composer build:zip`
- Verify package: `composer verify`
- Install the current release-candidate package from `build/npcink-pay-refund-1.3.4.zip`, not a raw source archive.
- Confirm `vendor/autoload.php` exists in the installed plugin.
- Record the package SHA-256 before installing it.

## Alipay

- Save APP ID, application private key, and Alipay public key.
- Run the Alipay configuration check from the settings page.
- Query a paid order within the allowed refund window.
- Submit one full refund with a unique reason.
- Confirm the response shows success or a clear gateway error.
- Query the successful refund again by its original `out_request_no`; confirm the returned order number, refund request number, and amount all match.
- Repeat the same refund request and confirm the duplicate-refund lock blocks it.
- Confirm a refund record is written with amount, time, order number, operator, type, and reason.
- In a controlled non-production test, interrupt the refund response after submission. Confirm the next operation queries the original `out_request_no` before any retry and reuses the original amount and reason.
- Confirm the admin reconciliation notice remains visible while the Alipay result is uncertain and disappears after provider confirmation plus a successful local audit write.

## WeChat Pay

- Save merchant ID, merchant API certificate serial number, merchant private key, WeChat Pay public key ID or platform certificate serial number, and WeChat Pay public key or platform certificate.
- Run the WeChat configuration check from the settings page.
- Query a paid order within the allowed refund window.
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
- Non-admin refund specialists should only access the configured admin pages.
- CSV export should open without formula execution for values starting with `=`, `+`, `-`, or `@`.
- Real merchant execution is operator-owned evidence. A passing local/CI suite must not be recorded as a successful live refund.
