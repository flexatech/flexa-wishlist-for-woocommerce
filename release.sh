#!/usr/bin/env bash
# Build a production zip of flexa-wishlist-for-woocommerce into ./build/.
#
# The package honours .distignore. This plugin has no runtime composer
# dependencies (only a `php` constraint + a fallback autoloader), and vendor/
# is excluded from the package, so there is no composer step — only the
# front-end build that refreshes assets/dist/.
set -euo pipefail

PLUGIN_SLUG="flexa-wishlist-for-woocommerce"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BUILD_DIR="${ROOT_DIR}/build"
STAGE_DIR="${BUILD_DIR}/${PLUGIN_SLUG}"

cd "${ROOT_DIR}"

# Read plugin version from the main file.
VERSION="$(grep -E '^[[:space:]]*\*[[:space:]]*Version:' "${PLUGIN_SLUG}.php" | head -1 | sed -E 's/.*Version:[[:space:]]*([^[:space:]]+).*/\1/')"
if [[ -z "${VERSION}" ]]; then
    echo "Could not read Version from ${PLUGIN_SLUG}.php" >&2
    exit 1
fi
echo "Building ${PLUGIN_SLUG} v${VERSION}"

# Fresh admin bundle (tsc type-check + vite build -> assets/dist/).
if [[ -f package.json ]]; then
    pnpm install --frozen-lockfile
    pnpm build
fi

# Bail if the build did not produce the compiled bundle.
if [[ ! -f "${ROOT_DIR}/assets/dist/.vite/manifest.json" ]]; then
    echo "Missing assets/dist/.vite/manifest.json — did the build run?" >&2
    exit 1
fi

# Stage a clean copy that honours .distignore.
rm -rf "${BUILD_DIR}"
mkdir -p "${STAGE_DIR}"

EXCLUDES=()
if [[ -f .distignore ]]; then
    while IFS= read -r line; do
        line="${line%%#*}"      # strip comment
        line="${line## }"       # trim leading space
        line="${line%% }"       # trim trailing space
        [[ -z "${line}" ]] && continue
        EXCLUDES+=(--exclude="${line#/}")
    done < .distignore
fi

rsync -a "${EXCLUDES[@]}" --exclude="build" --exclude=".git" "${ROOT_DIR}/" "${STAGE_DIR}/"

# Zip: top-level folder inside the archive is the plugin slug.
cd "${BUILD_DIR}"
ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"
rm -f "${ZIP_NAME}"
zip -rqX "${ZIP_NAME}" "${PLUGIN_SLUG}"
echo "Built ${BUILD_DIR}/${ZIP_NAME}"

# Keep only the zip in build/.
rm -rf "${STAGE_DIR}"
