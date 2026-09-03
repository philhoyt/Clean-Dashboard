#!/bin/bash
# Packages the plugin into a distributable zip file in dist/.
# Usage: bin/build-zip.sh

set -e

PLUGIN_SLUG="dashboard-cleanup"
PLUGIN_FILE="${PLUGIN_SLUG}.php"
DIST_DIR="dist"

ZIP_FILE="${DIST_DIR}/${PLUGIN_SLUG}.zip"
BUILD_DIR=$(mktemp -d)
STAGE="${BUILD_DIR}/${PLUGIN_SLUG}"

echo "Building ${PLUGIN_SLUG}..."

# Stage plugin files, honouring .distignore as the single source of truth.
mkdir -p "${STAGE}"
rsync -a \
  --exclude-from='.distignore' \
  --exclude='.git' \
  . "${STAGE}/"

# Sanity-check the staged tree: a bad .distignore pattern silently stripping a
# bundled dependency is otherwise only discovered as a fatal error on install.
REQUIRED_FILES=(
  "${PLUGIN_FILE}"
  "readme.txt"
  "lib/plugin-update-checker/plugin-update-checker.php"
  "lib/plugin-update-checker/vendor/Parsedown.php"
  "lib/plugin-update-checker/vendor/ParsedownModern.php"
  "lib/plugin-update-checker/vendor/PucReadmeParser.php"
)
for FILE in "${REQUIRED_FILES[@]}"; do
  if [ ! -f "${STAGE}/${FILE}" ]; then
    echo "Error: ${FILE} is missing from the build. Check .distignore patterns." >&2
    rm -rf "${BUILD_DIR}"
    exit 1
  fi
done

# Create dist directory and zip.
mkdir -p "${DIST_DIR}"
rm -f "${ZIP_FILE}"
(cd "${BUILD_DIR}" && zip -rq "${OLDPWD}/${ZIP_FILE}" "${PLUGIN_SLUG}")

# Clean up.
rm -rf "${BUILD_DIR}"

echo "Done: ${ZIP_FILE}"
