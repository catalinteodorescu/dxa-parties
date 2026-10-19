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
cPanel › **MultiPHP Manager**: alege **PHP 8.3** (sau mai nou) pentru subdomeniu.
cPanel › **Select PHP Version › Extensions**: activează `pdo_mysql`, `mbstring`, `openssl`, `gd`, `curl`, `fileinfo`, `intl`, `zip`.

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
2. cPanel › **Cron Jobs**: adaugă, la fiecare minut (`* * * * *`), comanda pe care ți-o arată pagina, de forma  
   `/usr/local/bin/php /home/CONT/salsa/artisan schedule:run >> /dev/null 2>&1`  
   (calea PHP o confirmi la MultiPHP; pe unele hosturi e `/opt/cpanel/ea-php83/root/usr/bin/php`).
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
| „500” după încărcare | PHP sub 8.3, extensie lipsă sau `storage/` fără drept de scriere |
| `install.php` arată „cheia lipsește” | `.install-key` nu e în rădăcina aplicației sau are sub 12 caractere |
| Imaginile încărcate nu apar | `public/storage` lipsește; legătura simbolică poate fi blocată de host — cere suport sau activează `symlink` |
| Nu pleacă notificările / plățile rămân în așteptare | cron-ul nu rulează |
| Nu pot crea baza de date | cPanel › MySQL Databases; prefixul contului face parte din nume |

Pe un server propriu (VPS) cu terminal există și varianta din `docs/INSTALARE.md` (`app:install`, `deploy/provision-client.sh`).
