# Instalarea pe hosting partajat (cPanel), fără terminal

Se face doar din browser: cPanel (File Manager, MySQL Databases, Cron Jobs) și pagina `install.php` a aplicației.

## Ce pregătești o singură dată (pe calculatorul tău)
Pachetul de instalare (un zip cu `vendor/` și `public/build/` gata făcute):
```bash
deploy/build-package.sh        # creează ../pachet-instalare-AAAAmmZZ.zip
```
Același pachet se folosește pentru toți clienții; datele fiecăruia se pun la instalare.

## Pentru fiecare client

### 1. Subdomeniul
cPanel › **Domains** › Create A New Domain (sau Subdomains): `salsa.exemplu.ro`.
- **Document Root**: folderul subdomeniului trebuie să se termine cu `/public`, ex. `salsa/public`
  (aplicația va sta în `salsa/`, iar doar `salsa/public` este vizibil din web). Nu lăsa rădăcina aplicației ca document root.
- Activează certificatul gratuit: cPanel › **SSL/TLS Status** › Run AutoSSL.

### 2. PHP
Aplicația cere **PHP 8.4 sau mai nou** (Laravel 13); sub 8.4 nu rulează și nu se poate adapta.
cPanel › **MultiPHP Manager**: alege **PHP 8.4** pentru subdomeniu (versiunea se setează per domeniu, caută subdomeniul în lista de jos).
Dacă interfața e blocată, încearcă în `.htaccess`, la început: `AddHandler application/x-httpd-ea-php84 .php` (sau, pe CloudLinux, `AddHandler application/x-httpd-alt-php84___lsphp .php`). Dacă hostingul nu oferă 8.4, ai nevoie de alt hosting.
cPanel › **Select PHP Version › Extensions**: activează `pdo_mysql`, `mbstring`, `openssl`, `gd`, `curl`, `fileinfo`, `intl`, `zip`.

### Plan B pentru document root
Dacă nu poți pune document root-ul pe `public/`, pune în rădăcina aplicației (lângă `app/` și `public/`) un `.htaccess` cu:
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```
Orice cerere ajunge în `public/`; `.env` și `.install-key` nu sunt expuse (primesc 404).

### 3. Baza de date
cPanel › **MySQL Databases**: creează o bază nouă, un utilizator nou, apoi adaugă utilizatorul la bază cu **ALL PRIVILEGES**.
Notează numele complet (cu prefixul contului, ex. `cont_salsa`), utilizatorul și parola.

### 4. Încarcă aplicația
File Manager › intră în folderul subdomeniului (ex. `salsa/`) › **Upload** pachetul zip › **Extract**.
Verifică: în `salsa/` să fie `app/`, `vendor/`, `public/`, `storage/` etc. (nu un folder în plus), apoi șterge zip-ul.
Drepturi: `storage/` și `bootstrap/cache/` scriabile (775 / implicit în cPanel).

### 5. Cheia de instalare
În `salsa/` (rădăcina aplicației, **nu** în `public`) creează un fișier nou numit `.install-key` (File Manager › New File;
activează „Show Hidden Files” ca să-l vezi) și scrie în el un șir lung și aleatoriu (minimum 12 caractere). Fără el, `install.php` nu face nimic.

### 6. Rulează instalarea
Deschide `https://salsa.exemplu.ro/install.php`. Completează:
- cheia din `.install-key`, denumirea organizatorului, adresa;
- datele bazei de date (server de obicei `localhost`);
- telefonul administratorului (login) și, opțional, parola (altfel se generează și ți-o arăt o singură dată);
- planul (Basic / Pro);
- opțional, datele pentru textele legale (CUI, Reg. Com., sediu, e-mail, telefon) și contul tău de proprietar.

Pagina verifică baza de date, scrie `.env`, rulează migrările, creează conturile, setează planul și publică Termenii + Politica.
Notează parola afișată și dă-o clientului.

### 7. După instalare (obligatoriu)
1. **Șterge fișierul `.install-key`** (File Manager). Fără el, `install.php` nu mai poate fi folosit de nimeni.
2. cPanel › **Cron Jobs**: adaugă, la 15 minute (`*/15 * * * *`), comanda pe care ți-o arată pagina, de forma  
   `/opt/alt/php84/usr/bin/php /home/CONT/salsa/artisan schedule:run >> /dev/null 2>&1`  
   **Executabilul PHP din cron nu e același cu versiunea site-ului**: `/usr/local/bin/php` e adesea 7.x. Testează înainte, cu un cron temporar  
   `for p in /opt/alt/php84/usr/bin/php /opt/cpanel/ea-php84/root/usr/bin/php /usr/local/bin/php; do echo "== $p"; $p -v 2>&1 | head -1; done >> /home/CONT/cron-php.txt 2>&1`  
   și folosește calea care afișează 8.4 (CloudLinux: `/opt/alt/php84/usr/bin/php`; cPanel clasic: `/opt/cpanel/ea-php84/root/usr/bin/php`). Șterge apoi `cron-php.txt`.
   Fără cron nu merg notificările programate și închiderea plăților cu cardul rămase în așteptare.
3. `.env` (File Manager › Edit): `SMS_DRIVER=smso`, `SMSO_API_KEY`, `SMSO_SENDER`; `STRIPE_SECRET` și `STRIPE_WEBHOOK_SECRET` ale clientului
   (webhook în contul Stripe al clientului către `https://salsa.exemplu.ro/webhooks/stripe`).
4. Intră în aplicație la `/admin`, schimbă parola, completează Setări › Organizație (logo) și Legal › Date organizator, dacă nu le-ai dat la instalare.

## Actualizarea unei instalări
1. Încarcă pachetul nou și extrage-l **peste** folderul aplicației (bifează „overwrite”). **Nu** suprascrie `.env` și nu șterge `storage/`
   (pachetul nu conține `.env` și nici `storage/app/public`, deci nu le atinge).
2. Creează din nou `.install-key`, deschide `https://salsa.exemplu.ro/install.php` (acum arată „Actualizare”), introdu cheia și apasă **Actualizează**
   (rulează migrările și golește cache-urile). Apoi șterge `.install-key`.

## Copii de siguranță
- cPanel › **Backup** (sau JetBackup, dacă există) › descarcă periodic copie completă; verifică ce păstrează automat hostingul.
- Pentru baza de date, în **Cron Jobs**, o dată pe zi (ajustează calea):  
  `0 3 * * * mysqldump --no-tablespaces -u UTILIZATOR -p'PAROLA' NUME_BAZA | gzip > /home/CONT/backup/salsa-$(date +\%Y\%m\%d).sql.gz`  
  (creează folderul `backup/` în afara lui `public_html`). Șterge periodic copiile vechi.

## Probleme frecvente
| Simptom | Cauză probabilă |
|---|---|
| „Please provide a valid cache path” | lipsește `storage/framework/views` (sau alt folder din `storage/`); instalatorul din runda 84e le creează singur, altfel le faci în File Manager |
| „500” după încărcare | PHP sub 8.4, extensie lipsă sau `storage/` fără drept de scriere |
| `install.php` arată „cheia lipsește” | `.install-key` nu e în rădăcina aplicației sau are sub 12 caractere |
| Imaginile încărcate nu apar | `public/storage` lipsește; legătura simbolică poate fi blocată de host — cere suport sau activează `symlink` |
| Nu pleacă notificările / plățile rămân în așteptare | cron-ul nu rulează |
| Nu pot crea baza de date | cPanel › MySQL Databases; prefixul contului face parte din nume |

Pe un server propriu (VPS) cu terminal există și varianta din `docs/INSTALARE.md` (`app:install`, `deploy/provision-client.sh`).

## Instanță de demonstrație (date fictive)

Pe o instalare folosită doar pentru demo poți încărca date neutre (20 de participanți cu cont, petreceri trecute și viitoare incluzând un festival, intrări, vânzări la bar, credite, bilete online, anunț). Deschide `/install.php`, introdu cheia din `.install-key` și apasă **Încarcă date demo** (activează `DEMO_MODE=true` în `.env`). Cu terminal: `php artisan app:demo-seed` după ce pui `DEMO_MODE=true`. Conturile au telefoanele `0700000001` … `0700000020` și parola `demo1234`. Operația e idempotentă; nu o rula pe o instalare cu clienți reali.
