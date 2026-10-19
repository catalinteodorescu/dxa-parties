#!/usr/bin/env bash
# Construiește vendor.zip (fără pachetele de dezvoltare) pe calculatorul tău, pentru hostingul fără SSH/composer.
# Rulează din rădăcina proiectului:  deploy/build-vendor.sh   →  creează ../vendor.zip
# Se urcă pe server doar când se schimbă composer.json / composer.lock (vezi docs/DEPLOY.md). Cere composer (Herd îl are).
set -euo pipefail
cd "$(dirname "$0")/.."

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
mkdir -p "$TMP/p"
cp composer.json composer.lock "$TMP/p/"
cp -R app database "$TMP/p/" 2>/dev/null || true
[ -d bootstrap ] && mkdir -p "$TMP/p/bootstrap"
( cd "$TMP/p" && composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress --no-scripts --ignore-platform-reqs )
# Fără istoric git, teste și documentație (zip mult mai mic, mai puține fișiere de urcat).
find "$TMP/p/vendor" -name .git -prune -exec rm -rf {} + 2>/dev/null || true
find "$TMP/p/vendor" -type d \( -name tests -o -name Tests -o -name docs -o -name doc -o -name examples -o -name .github \) -prune -exec rm -rf {} + 2>/dev/null || true
find "$TMP/p/vendor" -type f \( -iname '*.md' -o -iname 'phpunit.xml*' -o -iname 'CHANGELOG*' \) -delete 2>/dev/null || true

OUT="$(cd .. && pwd)/vendor.zip"
rm -f "$OUT"
( cd "$TMP/p" && zip -qr "$OUT" vendor )
echo "Gata: $OUT ($(du -h "$OUT" | cut -f1)). Instrucțiuni de extragere: docs/DEPLOY.md"
