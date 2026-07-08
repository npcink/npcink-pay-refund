#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WP_PATH="${WP_PATH:-/Users/muze/Local Sites/test/app/public}"
WP_URL="${WP_URL:-http://test.local}"
WP_SKIP_PLUGINS="${WP_SKIP_PLUGINS:-magick-ai}"
PACKAGE_SLUG="${PACKAGE_SLUG:-npcink-pay-refund}"
PCP_TARGET="${PCP_TARGET:-$PACKAGE_SLUG}"
PCP_SLUG="${PCP_SLUG:-npcink-pay-refund}"

cd "$ROOT_DIR"

composer validate --strict
composer audit

find . \
	-path './vendor' -prune -o \
	-path './build' -prune -o \
	-path './.git' -prune -o \
	-name '*.php' -print0 | xargs -0 -n1 php -l

if command -v node >/dev/null 2>&1; then
	find admin/js -name '*.js' -print0 | xargs -0 -n1 node --check
else
	echo "Skipping JavaScript syntax checks: node command is unavailable." >&2
fi

ZIP_FILE="$(composer build:zip | tail -n 1)"

ZIP_LIST="$(unzip -Z1 "$ZIP_FILE")"

if ! grep -Fx "${PACKAGE_SLUG}/vendor/autoload.php" <<< "$ZIP_LIST" >/dev/null; then
	echo "Release zip is missing vendor/autoload.php." >&2
	exit 1
fi
if grep -E "^${PACKAGE_SLUG}/(vite/|bin/|build/|admin/sdk/)" <<< "$ZIP_LIST" >/dev/null; then
	echo "Release zip contains excluded development or legacy SDK paths." >&2
	exit 1
fi

if command -v wp >/dev/null 2>&1 && [ -d "$WP_PATH" ]; then
	WP_CLI_ARGS=(
		--path="$WP_PATH"
		--url="$WP_URL"
		--allow-root
	)
	if [ -n "$WP_SKIP_PLUGINS" ]; then
		WP_CLI_ARGS+=(--skip-plugins="$WP_SKIP_PLUGINS")
	fi

	wp plugin install "$ZIP_FILE" --force --activate "${WP_CLI_ARGS[@]}" >/tmp/npcink-pay-refund-install-verify.out
	wp eval-file "$ROOT_DIR/bin/smoke-admin.php" "${WP_CLI_ARGS[@]}"

	wp plugin check "$PCP_TARGET" \
		--slug="$PCP_SLUG" \
		--format=json \
		--exclude-directories=build,bin \
		--exclude-files=.gitignore,.distignore \
		"${WP_CLI_ARGS[@]}" >/tmp/npcink-pay-refund-pcp-verify.out

	php <<'PHP'
<?php
$lines = file('/tmp/npcink-pay-refund-pcp-verify.out', FILE_IGNORE_NEW_LINES);
$rows = array();
foreach ($lines as $line) {
	$trim = trim($line);
	if ('' === $trim || '[' !== $trim[0]) {
		continue;
	}
	$items = json_decode($trim, true);
	if (is_array($items)) {
		$rows = array_merge($rows, $items);
	}
}
if (count($rows) > 0) {
	fwrite(STDERR, 'Plugin Check returned ' . count($rows) . " issue(s).\n");
	exit(1);
}
echo "Plugin Check clean.\n";
PHP
else
	echo "Skipping Plugin Check: wp command or WP_PATH is unavailable." >&2
fi

echo "Verification complete: $ZIP_FILE"
