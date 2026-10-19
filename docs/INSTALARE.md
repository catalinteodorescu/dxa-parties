# Instalarea unei instanțe pentru un client (server cu terminal / VPS)

> Pe **hosting partajat (cPanel), fără terminal**, folosește `docs/INSTALARE-CPANEL.md` (instalare din browser, cu `install.php`).

O instalare = o copie a codului + o bază de date + un subdomeniu pe serverul furnizorului. Contul Stripe este al clientului;
contul SMS (SMSO) este comun, iar consumul se urmărește din pagina **Proprietar**.

## Cerințe pe server
PHP 8.4+ (extensiile `pdo_mysql`, `mbstring`, `openssl`, `gd`, `curl`, `fileinfo`, `intl`), MySQL/MariaDB, nginx sau Apache, cron, `rsync`.

## Procedura (cap-coadă)
1. **DNS** – o înregistrare pentru subdomeniul clientului (ex. `salsa.exemplu.ro`) către server.
2. **Cod, bază de date, instalare** – o singură comandă:
   ```bash
   deploy/provision-client.sh salsa salsa.exemplu.ro "Club Salsa SRL" 0722000111 basic
   ```
   Scriptul creează baza de date și utilizatorul ei, copiază codul în `/var/www/clients/<slug>`, scrie `.env`, rulează
   `php artisan app:install` (migrări, superadmin, plan, numele organizatorului) și adaugă cron-ul. Parola generată a
   superadmin-ului se afișează o singură dată: o dai clientului, care o schimbă la prima autentificare.
   Dacă dai și datele organizatorului (vezi mai jos), publică și Termenii + Politica de confidențialitate.
3. **vhost și TLS** – `deploy/nginx-client.conf.template` (înlocuiești `__HOST__` și `__DIR__`), apoi certbot.
4. **SMS** – în `.env`: `SMS_DRIVER=smso`, `SMSO_API_KEY`, `SMSO_SENDER` (id-ul îl vezi cu `php artisan app:sms-status`).
5. **Plata cu cardul** – în `.env`: `STRIPE_SECRET` și `STRIPE_WEBHOOK_SECRET` ale **clientului**; webhook către
   `https://<subdomeniu>/webhooks/stripe`. Fără aceste chei plata cu cardul rămâne dezactivată.
6. **Notificări push** (dacă planul le include) – `php artisan app:push-keys`, cheile în `.env`.
7. După orice schimbare în `.env`: `php artisan config:cache`.
8. **Verificare** – `php artisan app:install --check` (listează ce lipsește, cu indicația de rezolvare).
9. **Copie de siguranță** – `deploy/backup.sh` din cron, zilnic; copiile se duc și în afara serverului.

## Comanda `app:install`
```bash
php artisan app:install --name="Club Salsa SRL" --url=https://salsa.exemplu.ro \
    --admin-phone=0722000111 --admin-name="Ana Popescu" --plan=basic \
    --cui=RO123456 --regcom="J32/1/2020" --address="Str. Mare 1, Sibiu" --email=contact@salsa.ro --phone=0722000222 \
    [--owner-phone=... --owner-password=...]
```
- Repetabilă: superadmin-ul, contul de proprietar și textele deja publicate nu se schimbă.
- `--owner-phone` / `--owner-password` (sau `INSTALL_OWNER_PHONE` / `INSTALL_OWNER_PASSWORD` în mediu) creează contul tău de
  proprietar, care vede pagina Proprietar (plan, consum SMS). Fără ele, contul se omite.
- Fără datele pentru textele legale (`--cui --regcom --address --email --phone`), instalarea merge mai departe dar nu publică
  nimic; le completezi din **Legal › Date organizator** sau rulezi `php artisan app:install-legal`.
- `--no-env` nu modifică `.env` (pentru instalări gestionate altfel).

## Actualizarea unei instalări existente
`rsync` cu versiunea nouă (fără `.env` și fără `storage/`), apoi `php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache`.

## Comenzi utile
| Comandă | Ce face |
|---|---|
| `app:install --check` | verifică instalarea |
| `app:install-legal` | salvează datele organizatorului și publică textele legale |
| `app:sms-status` | creditul și expeditorii din contul SMSO |
| `app:push-keys` | generează cheile pentru notificări push |
| `app:push-run`, `app:expire-payments` | rulează singure din cron (`schedule:run`) |
