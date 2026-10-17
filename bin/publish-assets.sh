#!/bin/sh
# Copy release assets into a static directory (e.g. a CDN origin or a website):
#   <target>/dist/<version>/open-consent.js (+ .sri, translations/*.json)
#   <target>/download/open-consent-wordpress-<version>.zip
#   <target>/download/open-consent-<version>-source.zip
# Published versions are immutable: an existing version directory is never overwritten.
set -eu
TARGET=${1:?usage: bin/publish-assets.sh <target-dir>}
ROOT=$(cd "$(dirname "$0")/.." && pwd)
VERSION=$(cat "$ROOT/VERSION")
php "$ROOT/bin/build.php" --check
DIST="$TARGET/dist/$VERSION"
if [ -e "$DIST" ]; then echo "refusing to overwrite $DIST (bump VERSION)"; exit 1; fi
mkdir -p "$DIST/translations" "$TARGET/download"
cp "$ROOT/dist/open-consent.js" "$ROOT/dist/open-consent.js.sri" "$DIST/"
cp "$ROOT"/config/translations/*.json "$DIST/translations/"
WPZIP=$("$ROOT/bin/package-wordpress.sh" | tail -1)
cp "$WPZIP" "$TARGET/download/"
SRC="$TARGET/download/open-consent-$VERSION-source.zip"
rm -f "$SRC"
cd "$(dirname "$ROOT")"
zip -qr -X "$SRC" "$(basename "$ROOT")" -x '*/vendor/*' '*/build/*' '*/.git/*' '*/composer.lock' '*/.phpunit.cache/*' '*/.phpunit.result.cache'
echo "published $VERSION to $TARGET"
