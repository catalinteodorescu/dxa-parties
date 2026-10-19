# Deploy la cerere (GitHub Actions → FTP)

`git push` NU face deploy. Deploy-ul pornește doar când rulezi comanda `deploy_baile` (sau manual din GitHub: Actions → Deploy → Run workflow). Atunci GitHub construiește CSS/JS (`npm run build`),
urcă prin FTP doar fișierele schimbate și apelează `/deploy/<cheie>` (migrări + curățare cache). Nu se urcă niciodată `.env`, `storage/` și `bootstrap/cache/`.

## Configurare (o singură dată)

1. **cPanel → FTP Accounts**: creează un cont FTP cu directorul rădăcină în folderul aplicației (cel cu `artisan`, `.env`, `public/`), sau notează calea față de home pentru `FTP_DIR`.
2. **Pe server, în `.env`** adaugă: `DEPLOY_KEY=<șir lung aleator, 40+ caractere>` (generează unul cu `openssl rand -hex 24`).
3. **GitHub → repo → Settings → Secrets and variables → Actions → Secrets**:
   - `FTP_HOST` – serverul FTP (ex. `ftp.spsconstruct.ro`)
   - `FTP_USER`, `FTP_PASS` – contul FTP de mai sus
   - `FTP_DIR` – calea de pe server, cu `/` la final (ex. `/` dacă contul FTP pornește chiar în folderul aplicației)
   - `APP_URL` – adresa publică, fără `/` la final (ex. `https://baile.spsconstruct.ro`)
   - `DEPLOY_KEY` – aceeași valoare ca `DEPLOY_KEY` din `.env`
   - (opțional, la *Variables*) `FTP_PROTOCOL` = `ftp` dacă hostingul nu acceptă FTPS (implicit `ftps`).
4. **`vendor/` nu se urcă prin FTP** (zeci de mii de fișiere). Serverul îl are deja de la instalare. Se actualizează manual doar când se schimbă `composer.json`/`composer.lock`
   (GitHub Actions afișează atunci un avertisment galben):
   - pe calculator: `deploy/build-vendor.sh` → creează `../vendor.zip` (fără pachete de dezvoltare, ~25 MB);
   - în cPanel → File Manager, în folderul aplicației: redenumești `vendor` în `vendor_old`, încarci `vendor.zip` (Upload), îl extragi (Extract), apoi ștergi `vendor_old` și `vendor.zip`.

## Comanda `deploy_baile` (o singură dată)
Cerințe: `git` și GitHub CLI (`brew install gh`), apoi `gh auth login` (GitHub.com, HTTPS, autentificare în browser). Din folderul proiectului:

```
chmod +x deploy.sh
echo "alias deploy_baile='$(pwd)/deploy.sh'" >> ~/.zshrc && source ~/.zshrc
```

## Update obișnuit
Salvezi modificările în git, apoi, din orice folder, scrii `deploy_baile`. Scriptul face `git push origin main`, pornește deploy-ul, arată în terminal progresul pas cu pas și rezultatul
(✔ / ✖ cu ultimele linii de log). Funcționează doar de pe branch-ul `main`. Rollback: `git revert <commit>` + `deploy_baile`.
Dacă dai comanda de două ori la rând, a doua rulare așteaptă prima (coadă).

## Mentenanță în timpul deploy-ului
Implicit, cât durează urcarea fișierelor (1–2 minute), site-ul afișează pagina „Actualizăm aplicația” (503, `resources/views/errors/503.blade.php`).
La final, după migrări, site-ul iese singur din mentenanță, și la eșec sau anulare. Fără mentenanță: `deploy_baile --fara-mentenanta`.
Dacă site-ul rămâne totuși în mentenanță, deschide în browser `APP_URL/deploy/<cheie>?step=up`.
La primul deploy cu această funcție, pasul „Mentenanță pornită” dă avertisment (ruta nu există încă pe server); e normal.

## Primul deploy
Pune întâi `DEPLOY_KEY` în `.env` pe server (pasul 2). Prima rulare urcă toate fișierele aplicației (nu există încă starea de sincronizare de pe server), deci poate dura câteva minute;
ea aduce pe server și ruta `/deploy/<cheie>`, așa că pasul „Mentenanță pornită” dă un avertisment (ruta încă nu există) și e normal, iar pasul „Migrări” funcționează deja la aceeași rulare.

## Dacă ceva nu merge
- FTP respins: schimbă `FTP_PROTOCOL` în `ftp` sau verifică `FTP_HOST`/parola.
- Pasul „Migrări” dă 404: lipsește `DEPLOY_KEY` din `.env` de pe server sau nu coincide cu secretul `DEPLOY_KEY`, sau fișierele (ruta) nu sunt încă pe server.
- Pasul „Migrări” dă 500: textul răspunsului din log arată ce comandă a eșuat.
- Alternativ, fără terminal: GitHub → Actions → Deploy → Run workflow.
