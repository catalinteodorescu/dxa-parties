#!/usr/bin/env bash
# Pregătește o instanță nouă pentru un client (organizator) pe serverul tău: baza de date, codul, .env, instalarea și cron-ul.
# ȘABLON: citește-l și adaptează-l serverului tău (utilizatori, căi, versiunea de PHP) înainte de prima rulare.
#
# Utilizare:
#   deploy/provision-client.sh <slug> <subdomeniu.exemplu.ro> "<Denumirea organizatorului>" <telefon-admin> [basic|pro] [telefon-proprietar]
# Variabile de mediu (cu valori implicite):
#   RELEASE_DIR   directorul cu versiunea de cod de instalat        (implicit: directorul curent al scriptului, un nivel mai sus)
#   CLIENTS_DIR   unde stau instalările                              (implicit: /var/www/clients)
#   MYSQL_ADMIN   comanda de conectare ca administrator MySQL        (implicit: mysql)
#   PHP           binarul PHP                                         (implicit: php)
#   OWNER_PASSWORD parola contului de proprietar (dacă dai telefonul proprietarului)
set -euo pipefail

SLUG="${1:?slug lipsește (litere mici, cifre, liniuță)}"
HOST="${2:?subdomeniul lipsește}"
NAME="${3:?denumirea organizatorului lipsește}"
ADMIN_PHONE="${4:?telefonul administratorului lipsește}"
PLAN="${5:-basic}"
OWNER_PHONE="${6:-}"

[[ "$SLUG" =~ ^[a-z0-9][a-z0-9-]{1,30}$ ]] || { echo "slug invalid: $SLUG" >&2; exit 1; }

RELEASE_DIR="${RELEASE_DIR:-$(cd "$(dirname "$0")/.." && pwd)}"
CLIENTS_DIR="${CLIENTS_DIR:-/var/www/clients}"
MYSQL_ADMIN="${MYSQL_ADMIN:-mysql}"
PHP="${PHP:-php}"
TARGET="$CLIENTS_DIR/$SLUG"
DB="app_${SLUG//-/_}"
DB_USER="$DB"
DB_PASS="$(openssl rand -base64 24 | tr -dc 'A-Za-z0-9' | head -c 28)"

[[ -e "$TARGET" ]] && { echo "$TARGET există deja; nu suprascriu o instalare existentă." >&2; exit 1; }

echo "==> Baza de date $DB"
$MYSQL_ADMIN <<SQL
CREATE DATABASE \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL

echo "==> Codul în $TARGET"
mkdir -p "$TARGET"
rsync -a --exclude '.git' --exclude '.env' --exclude 'node_modules' --exclude 'storage/logs/*' --exclude 'tests' "$RELEASE_DIR"/ "$TARGET"/
mkdir -p "$TARGET"/storage/{app/public,framework/{cache,sessions,views},logs} "$TARGET"/bootstrap/cache

echo "==> .env"
cp "$TARGET/.env.example" "$TARGET/.env"
sed -i "s|^DB_CONNECTION=.*|DB_CONNECTION=mysql|; s|^#\? \?DB_HOST=.*|DB_HOST=127.0.0.1|; s|^#\? \?DB_PORT=.*|DB_PORT=3306|; s|^#\? \?DB_DATABASE=.*|DB_DATABASE=$DB|; s|^#\? \?DB_USERNAME=.*|DB_USERNAME=$DB_USER|; s|^#\? \?DB_PASSWORD=.*|DB_PASSWORD=$DB_PASS|" "$TARGET/.env"

echo "==> Instalarea aplicației"
cd "$TARGET"
ARGS=(--name="$NAME" --url="https://$HOST" --admin-phone="$ADMIN_PHONE" --plan="$PLAN")
if [[ -n "$OWNER_PHONE" ]]; then ARGS+=(--owner-phone="$OWNER_PHONE" --owner-password="${OWNER_PASSWORD:?OWNER_PASSWORD lipsește}"); fi
$PHP artisan app:install "${ARGS[@]}"
$PHP artisan config:cache && $PHP artisan route:cache && $PHP artisan view:cache

echo "==> Cron (o linie pe instalare)"
CRON="* * * * * cd $TARGET && $PHP artisan schedule:run >> /dev/null 2>&1"
( crontab -l 2>/dev/null | grep -vF "$TARGET" ; echo "$CRON" ) | crontab -

cat <<EOT

Gata: $NAME  →  https://$HOST
Mai rămân (manual):
  1. DNS: înregistrarea pentru $HOST către server, apoi certificatul (ex. certbot) și vhost-ul din deploy/nginx-client.conf.template.
  2. SMS: în $TARGET/.env pune SMS_DRIVER=smso, SMSO_API_KEY, SMSO_SENDER.
  3. Stripe: STRIPE_SECRET și STRIPE_WEBHOOK_SECRET ale CLIENTULUI (contul lui), webhook la https://$HOST/webhooks/stripe.
  4. Texte legale: dacă nu ai dat datele organizatorului, rulează  php artisan app:install-legal --cui=... --regcom=... --address=... --email=... --phone=...
  5. Notificări push: php artisan app:push-keys și cheile în .env, apoi php artisan config:cache.
  6. Verificare: php artisan app:install --check
  7. Copia de siguranță: adaugă instalarea în deploy/backup.sh (cron zilnic).
EOT
