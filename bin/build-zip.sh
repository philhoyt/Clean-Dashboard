#!/bin/bash
# Packages the plugin into a distributable zip file in dist/.
# Usage: bin/build-zip.sh

set -e

PLUGIN_SLUG="wp-dashboard-cleanup"
PLUGIN_FILE="${PLUGIN_SLUG}.php"
DIST_DIR="dist"

ZIP_FILE="${DIST_DIR}/${PLUGIN_SLUG}.zip"
BUILD_DIR=$(mktemp -d)
STAGE="${BUILD_DIR}/${PLUGIN_SLUG}"

echo "Building ${PLUGIN_SLUG}..."

# Stage plugin files.
mkdir -p "${STAGE}"
rsync -a \
  --exclude='.git' \
  --exclude='.gitignore' \
  --exclude='.claude' \
  --exclude='bin' \
  --exclude='dist' \
  --exclude='docs' \
  --exclude='vendor' \
  --exclude='composer.json' \
  --exclude='composer.lock' \
  --exclude='phpcs.xml' \
  . "${STAGE}/"

# Create dist directory and zip.
mkdir -p "${DIST_DIR}"
rm -f "${ZIP_FILE}"
(cd "${BUILD_DIR}" && zip -rq "${OLDPWD}/${ZIP_FILE}" "${PLUGIN_SLUG}")

# Clean up.
rm -rf "${BUILD_DIR}"

echo "Done: ${ZIP_FILE}"
