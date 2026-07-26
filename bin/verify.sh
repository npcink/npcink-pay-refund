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

composer test
composer validate --strict
if ! composer audit; then
	echo "Retrying Composer security audit after a transient network failure." >&2
	composer audit
fi

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
if grep -E "^${PACKAGE_SLUG}/(vite/|bin/|build/|tests/|admin/sdk/)" <<< "$ZIP_LIST" >/dev/null; then
	echo "Release zip contains excluded development or legacy SDK paths." >&2
	exit 1
fi
if grep -E "^${PACKAGE_SLUG}/(docs/|README\.md$|vendor/alipaysdk/easysdk/tea/)" <<< "$ZIP_LIST" >/dev/null; then
	echo "Release zip contains excluded maintenance documentation or EasySDK Tea sources." >&2
	exit 1
fi
if grep -E "^${PACKAGE_SLUG}/(.*/)?Teafile$|^${PACKAGE_SLUG}/.*\.tea$" <<< "$ZIP_LIST" >/dev/null; then
	echo "Release zip contains excluded Tea specification files." >&2
	exit 1
fi

if ! unzip -p "$ZIP_FILE" "${PACKAGE_SLUG}/npcink-pay-refund.php" | grep -Eq '^ \* Requires PHP:[[:space:]]+8\.1$'; then
	echo "Packaged plugin header does not require PHP 8.1." >&2
	exit 1
fi
if ! unzip -p "$ZIP_FILE" "${PACKAGE_SLUG}/readme.txt" | grep -Eq '^Requires PHP:[[:space:]]*8\.1$'; then
	echo "Packaged WordPress.org readme does not require PHP 8.1." >&2
	exit 1
fi
PACKAGED_PHP_REQUIREMENT="$(unzip -p "$ZIP_FILE" "${PACKAGE_SLUG}/composer.json" | php -r '$data = json_decode(stream_get_contents(STDIN), true); echo is_array($data) && isset($data["require"]["php"]) ? $data["require"]["php"] : "";')"
if [ "$PACKAGED_PHP_REQUIREMENT" != ">=8.1" ]; then
	echo "Packaged Composer metadata does not require PHP 8.1." >&2
	exit 1
fi
PACKAGED_PLUGIN_VERSION="$(
	unzip -p "$ZIP_FILE" "${PACKAGE_SLUG}/npcink-pay-refund.php" |
		php -r '$content=stream_get_contents(STDIN); if (preg_match("/^ \\* Version:\\s*([0-9A-Za-z.-]+)\\s*$/m", $content, $matches)) { echo $matches[1]; }'
)"
PACKAGED_RUNTIME_VERSION="$(
	unzip -p "$ZIP_FILE" "${PACKAGE_SLUG}/npcink-pay-refund.php" |
		php -r '$content=stream_get_contents(STDIN); if (preg_match("/NPCINK_PAY_REFUND_VERSION\\x27, \\x27([0-9A-Za-z.-]+)\\x27/", $content, $matches)) { echo $matches[1]; }'
)"
PACKAGED_STABLE_TAG="$(
	unzip -p "$ZIP_FILE" "${PACKAGE_SLUG}/readme.txt" |
		php -r '$content=stream_get_contents(STDIN); if (preg_match("/^Stable tag:\\s*([0-9A-Za-z.-]+)\\s*$/m", $content, $matches)) { echo $matches[1]; }'
)"
if [ -z "$PACKAGED_PLUGIN_VERSION" ] ||
	[ "$PACKAGED_PLUGIN_VERSION" != "$PACKAGED_RUNTIME_VERSION" ] ||
	[ "$PACKAGED_PLUGIN_VERSION" != "$PACKAGED_STABLE_TAG" ]; then
	echo "Packaged plugin header, runtime constant, and stable tag are not synchronized." >&2
	exit 1
fi

if command -v wp >/dev/null 2>&1 && [ -d "$WP_PATH" ]; then
	WP_CLI_BASE_ARGS=(
		--path="$WP_PATH"
		--url="$WP_URL"
		--allow-root
	)
	WP_CLI_ARGS=("${WP_CLI_BASE_ARGS[@]}")
	if [ -n "$WP_SKIP_PLUGINS" ]; then
		WP_CLI_ARGS+=(--skip-plugins="$WP_SKIP_PLUGINS")
	fi
	PCP_WP_CLI_ARGS=("${WP_CLI_ARGS[@]}")

	PLUGIN_PATH="$WP_PATH/wp-content/plugins/$PACKAGE_SLUG"
	PLUGIN_REAL_PATH="$(php -r '$path = realpath($argv[1]); echo false === $path ? "" : $path;' "$PLUGIN_PATH")"
	ROOT_REAL_PATH="$(cd "$ROOT_DIR" && pwd -P)"
	PACKAGE_CHECK_DIR=""

	cleanup_package_check() {
		if [ -n "$PACKAGE_CHECK_DIR" ] && [ -d "$PACKAGE_CHECK_DIR" ]; then
			rm -rf -- "$PACKAGE_CHECK_DIR"
		fi
	}
	trap cleanup_package_check EXIT

	if [ -L "$PLUGIN_PATH" ] && [ "$PLUGIN_REAL_PATH" = "$ROOT_REAL_PATH" ]; then
		wp plugin activate "$PACKAGE_SLUG" "${WP_CLI_ARGS[@]}" >/tmp/npcink-pay-refund-install-verify.out
		PACKAGE_CHECK_DIR="$(mktemp -d "$WP_PATH/wp-content/plugins/${PACKAGE_SLUG}-package-check.XXXXXX")"
		rsync -a --delete "$ROOT_DIR/build/$PACKAGE_SLUG/" "$PACKAGE_CHECK_DIR/"
		PCP_TARGET="$(basename "$PACKAGE_CHECK_DIR")"
	else
		wp plugin install "$ZIP_FILE" --force --activate "${WP_CLI_ARGS[@]}" >/tmp/npcink-pay-refund-install-verify.out
	fi

	wp eval-file "$ROOT_DIR/bin/smoke-admin.php" "${WP_CLI_ARGS[@]}"
	if [ -n "$PACKAGE_CHECK_DIR" ]; then
		PACKAGE_SKIP_PLUGINS="$PACKAGE_SLUG"
		if [ -n "$WP_SKIP_PLUGINS" ]; then
			PACKAGE_SKIP_PLUGINS="$WP_SKIP_PLUGINS,$PACKAGE_SKIP_PLUGINS"
		fi
		NPCINK_PAY_REFUND_SMOKE_PLUGIN_DIR="$PACKAGE_CHECK_DIR" \
			wp eval-file "$ROOT_DIR/bin/smoke-admin.php" \
			"${WP_CLI_BASE_ARGS[@]}" \
			--skip-plugins="$PACKAGE_SKIP_PLUGINS"
		PCP_WP_CLI_ARGS=("${WP_CLI_BASE_ARGS[@]}" --skip-plugins="$PACKAGE_SKIP_PLUGINS")
	fi

	wp plugin check "$PCP_TARGET" \
		--slug="$PCP_SLUG" \
		--format=json \
		--exclude-directories=build,bin \
		--exclude-files=.gitignore,.distignore \
		"${PCP_WP_CLI_ARGS[@]}" >/tmp/npcink-pay-refund-pcp-verify.out

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
