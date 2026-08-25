#!/usr/bin/env bash
#
# Bouwt installeerbare zip-bestanden voor beide KZ-plugins.
# Gebruik: ./build.sh
# Output: dist/kz-plugin.zip en dist/kz-contentmanager-plugin.zip
#
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DIST_DIR="$ROOT_DIR/dist"

rm -rf "$DIST_DIR"
mkdir -p "$DIST_DIR"

build_plugin() {
    local plugin_dir="$1"
    local zip_name="$2"

    echo "Bouwen: $zip_name uit $plugin_dir"
    local tmp_dir
    tmp_dir="$(mktemp -d)"
    cp -r "$ROOT_DIR/$plugin_dir" "$tmp_dir/$plugin_dir"

    (cd "$tmp_dir" && zip -r -q "$DIST_DIR/$zip_name" "$plugin_dir" -x "*.DS_Store")

    rm -rf "$tmp_dir"
    echo "  -> $DIST_DIR/$zip_name"
}

build_plugin "kz-plugin" "kz-plugin.zip"
build_plugin "kz-contentmanager-plugin" "kz-contentmanager-plugin.zip"

echo "Klaar. Zips staan in $DIST_DIR/"
