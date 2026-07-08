# Refund Integration Checklist

Use this checklist for the first real merchant run. Do not test refunds against a production order unless the merchant account owner explicitly approves it.

## Release Candidate

- Build package: `composer build:zip`
- Verify package: `composer verify`
- Install package from `build/magick-refund-1.3.0.zip`, not a raw source archive.
- Confirm `vendor/autoload.php` exists in the installed plugin.

## Alipay

- Save APP ID, application private key, and Alipay public key.
- Run the Alipay configuration check from the settings page.
- Query a paid order within the allowed refund window.
- Submit one full refund with a unique reason.
- Confirm the response shows success or a clear gateway error.
- Repeat the same refund request and confirm the duplicate-refund lock blocks it.
- Confirm a refund record is written with amount, time, order number, operator, type, and reason.

## WeChat Pay

- Save merchant ID, merchant API certificate serial number, merchant private key, WeChat Pay public key ID or platform certificate serial number, and WeChat Pay public key or platform certificate.
- Run the WeChat configuration check from the settings page.
- Query a paid order within the allowed refund window.
- Submit one full refund with a unique reason.
- Confirm `PROCESSING`, `SUCCESS`, `CLOSED`, and `ABNORMAL` responses show distinct operator messages when they occur.
- Repeat the same refund request and confirm the duplicate-refund lock blocks it.
- Confirm a refund record is written with amount, time, order number, operator, type, and reason.

## Regression Notes

- Leaving secret fields empty must retain existing saved secrets.
- Refund specialists must be author-or-above non-admin users.
- Non-admin refund specialists should only access the configured admin pages.
- CSV export should open without formula execution for values starting with `=`, `+`, `-`, or `@`.
