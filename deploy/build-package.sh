#!/usr/bin/env bash
# Construiește pachetul de instalare (un zip) pe calculatorul tău, ca să-l încarci pe hostingul clientului fără terminal.
# Rulează din rădăcina proiectului:  deploy/build-package.sh   →  creează ../pachet-instalare-AAAAmmZZ.zip
# Cere composer și npm instalate local (Herd le are). Pachetul conține vendor/ și public/build/ gata făcute, fără teste, .env, .git sau node_modules.
set -euo pipefail
cd "$(dirname "$0")/.."

composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build

OUT="../pachet-instalare-$(date +%Y%m%d).zip"
rm -f "$OUT"
zip -qr "$OUT" . \
  -x '.git/*' 'node_modules/*' 'tests/*' '.env' '.env.*' '!.env.example' 'storage/logs/*' 'storage/framework/cache/*' 'storage/framework/sessions/*' \
     'storage/framework/views/*' 'storage/app/public/*' 'storage/installed.lock' '.install-key' 'database/*.sqlite' 'public/storage' 'public/hot' \
     '*.DS_Store' 'CLAUDE.md' 'AGENTS.md' '.phpunit.result.cache'
zip -q "$OUT" .env.example

echo "Gata: $OUT ($(du -h "$OUT" | cut -f1))"
echo "Reinstalează dependențele de dezvoltare local cu: composer install"
