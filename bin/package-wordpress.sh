#!/bin/sh
# Build an installable WordPress plugin zip: build/open-consent-wordpress-<version>.zip
set -eu
ROOT=$(cd "$(dirname "$0")/.." && pwd)
VERSION=$(cat "$ROOT/VERSION")
php "$ROOT/bin/build.php" --check
mkdir -p "$ROOT/build"
OUT="$ROOT/build/open-consent-wordpress-$VERSION.zip"
rm -f "$OUT"
cd "$ROOT/integrations/wordpress"
zip -qr -X "$OUT" open-consent -x '*.DS_Store'
echo "$OUT"
