#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PACKAGE_SLUG="${PACKAGE_SLUG:-npcink-pay-refund}"
BUILD_DIR="$ROOT_DIR/build"
STAGE_DIR="$BUILD_DIR/$PACKAGE_SLUG"
VERSION="$(
	php -r '$file=$argv[1]; $content=file_get_contents($file); if (preg_match("/Version:\s*([0-9A-Za-z.-]+)/", $content, $matches)) { echo $matches[1]; }' "$ROOT_DIR/mare.php"
)"

if [ -z "$VERSION" ]; then
	echo "Could not read plugin version from mare.php" >&2
	exit 1
fi

composer install --no-dev --optimize-autoloader --no-interaction --working-dir="$ROOT_DIR"

ZIP_FILE="$BUILD_DIR/${PACKAGE_SLUG}-${VERSION}.zip"
rm -rf "$STAGE_DIR" "$ZIP_FILE"
mkdir -p "$BUILD_DIR"

rsync -a --delete --exclude-from="$ROOT_DIR/.distignore" "$ROOT_DIR/" "$STAGE_DIR/"

(
	cd "$BUILD_DIR"
	zip -qr "$ZIP_FILE" "$PACKAGE_SLUG"
)

echo "$ZIP_FILE"
