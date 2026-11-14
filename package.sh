#!/usr/bin/env bash
set -euo pipefail

PLUGIN_SLUG="wp-image-optimizer"
VERSION="${1:-}"

if [[ -z "$VERSION" ]]; then
  # Extract version from plugin header
  VERSION=$(grep -m1 "Version:" wp-image-optimizer.php | awk '{print $NF}')
fi

DIST_DIR="${PLUGIN_SLUG}"
ZIP_NAME="${PLUGIN_SLUG}-v${VERSION}.zip"

rm -rf "${DIST_DIR}" "${ZIP_NAME}"
mkdir -p "${DIST_DIR}"

cp wp-image-optimizer.php "${DIST_DIR}/"
cp -r includes            "${DIST_DIR}/"
cp -r assets              "${DIST_DIR}/"
cp    README.md           "${DIST_DIR}/"

zip -r "${ZIP_NAME}" "${DIST_DIR}/"
rm -rf "${DIST_DIR}"

echo "Built: ${ZIP_NAME}"
echo "ZIP_NAME=${ZIP_NAME}" >> "${GITHUB_ENV:-/dev/null}" 2>/dev/null || true
