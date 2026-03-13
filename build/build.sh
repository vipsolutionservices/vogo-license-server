#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_SOURCE_DIR="${REPO_ROOT}/wp-plugin"
BUILD_DIR="${REPO_ROOT}/build"
STAGE_DIR="${BUILD_DIR}/.stage"
PLUGIN_DIR_NAME="vogo-plugin"
OUTPUT_ZIP="${BUILD_DIR}/vogo-plugin.zip"

rm -rf "${STAGE_DIR}"
mkdir -p "${STAGE_DIR}/${PLUGIN_DIR_NAME}"

rsync -a --delete \
  --exclude='.git' \
  --exclude='.github' \
  --exclude='node_modules' \
  --exclude='tests' \
  --exclude='build' \
  --exclude='.editorconfig' \
  "${PLUGIN_SOURCE_DIR}/" "${STAGE_DIR}/${PLUGIN_DIR_NAME}/"

rm -f "${OUTPUT_ZIP}"
(
  cd "${STAGE_DIR}"
  zip -rq "${OUTPUT_ZIP}" "${PLUGIN_DIR_NAME}"
)

echo "Created ${OUTPUT_ZIP}"
