#!/usr/bin/env bash
#
# Build the shared-hosting package.
#
#   deploy/build-shared-hosting.sh
#
# Produces deploy/dist/kcca-vrsp-shared-hosting.zip: only the files Apache
# needs, without the Docker stack, CI files, the 20 MB stock originals, or
# any local configuration. Upload the ZIP (or its contents) to the host's
# web root.
#
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$ROOT/deploy/dist"
STAGE="$OUT/kcca-vrsp"
ZIP="$OUT/kcca-vrsp-shared-hosting.zip"

command -v zip >/dev/null 2>&1 || { echo "zip is not installed." >&2; exit 1; }

rm -rf "$STAGE" "$ZIP"
mkdir -p "$STAGE"

cd "$ROOT"

# Runtime tree. Anything not listed here stays out of the package on purpose.
for item in api assets auth config database images index.php lang layouts lib pages partials .htaccess; do
    cp -a "$item" "$STAGE/"
done

# Not needed on a server, and large.
rm -rf "$STAGE/images/source"

# Local credentials, if this checkout has any, must never leave the machine.
rm -f "$STAGE/config/config.local.php"

# Release notes and container files.
rm -f "$STAGE/README.md" "$STAGE/DOCKER.md" "$STAGE/docker-compose.yml" \
      "$STAGE/docker-compose.dev.yml" "$STAGE/Dockerfile"

( cd "$STAGE" && zip -qr "$ZIP" . )

printf '\nPackage: %s\n' "$ZIP"
printf 'Files:   %s\n' "$(find "$STAGE" -type f | wc -l)"
printf 'Size:    %s\n' "$(du -h "$ZIP" | cut -f1)"
printf '\nNext: deploy/make-db-dump.sh, then follow deploy/HOSTING.md\n'
