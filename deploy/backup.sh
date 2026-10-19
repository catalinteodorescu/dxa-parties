#!/usr/bin/env bash
# Copie de siguranță pentru toate instalările din CLIENTS_DIR: baza de date (mysqldump) + fișierele încărcate (storage/app/public).
# Se rulează zilnic din cron, ex.:  15 3 * * * /var/www/release/deploy/backup.sh >> /var/log/backup-clienti.log 2>&1
# ȘABLON: adaptează căile; copiile ar trebui duse și în afara serverului (rclone / rsync / spațiu de stocare extern).
set -euo pipefail

CLIENTS_DIR="${CLIENTS_DIR:-/var/www/clients}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/clienti}"
KEEP_DAYS="${KEEP_DAYS:-14}"
STAMP="$(date +%Y%m%d-%H%M)"

mkdir -p "$BACKUP_DIR"
for dir in "$CLIENTS_DIR"/*/; do
  [[ -f "$dir/.env" ]] || continue
  slug="$(basename "$dir")"
  get() { grep -E "^$1=" "$dir/.env" | head -1 | cut -d= -f2- | tr -d '"'; }
  db="$(get DB_DATABASE)"; user="$(get DB_USERNAME)"; pass="$(get DB_PASSWORD)"
  [[ -n "$db" ]] || continue

  out="$BACKUP_DIR/$slug"; mkdir -p "$out"
  MYSQL_PWD="$pass" mysqldump --single-transaction --no-tablespaces -u "$user" "$db" | gzip > "$out/$STAMP-db.sql.gz"
  [[ -d "$dir/storage/app/public" ]] && tar -czf "$out/$STAMP-files.tar.gz" -C "$dir/storage/app" public
  find "$out" -type f -mtime +"$KEEP_DAYS" -delete
  echo "$(date -Is) $slug ok"
done
