# Npcink Pay Refund repository guidance

- Start with [README.md](README.md) for install and release status, then use the [Chinese project guide](docs/README.zh-CN.md) to locate the relevant modules. Read the [development and release standards](docs/DEVELOPMENT-AND-RELEASE-STANDARDS.zh-CN.md) before changing refund behavior, permissions, storage, or packaging.
- Run `composer test` for local PHP regressions. For other checks and their scope, see the [verification commands](docs/README.zh-CN.md#开发与验证). Use an explicitly isolated WordPress site for full verification.
- `composer verify:local` and `composer verify` both build ZIPs. Protect frozen artifacts by running those checks in a separate checkout. Never rebuild or replace an accepted ZIP, publish, or run live merchant refunds without explicit authorization. Merchant acceptance applies only to the exact artifact tested.
- Keep `readme.txt` for WordPress.org; repository maintenance docs and this file stay out of the installation ZIP. Keep payment credentials and real order data out of docs, fixtures, and logs.
