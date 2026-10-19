<?php

namespace App\Console\Commands;

use App\Services\Installer;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Adăugat (runda 84). Instalarea unei instanțe noi pentru un client (organizator). Se rulează o dată, după ce codul e pe server,
 * baza de date e creată și datele ei sunt în .env. Repetabilă: ce există deja nu se atinge.
 *
 *   php artisan app:install --name="Club Salsa" --url=https://salsa.exemplu.ro --admin-phone=0722000000 --plan=basic [...]
 *   php artisan app:install --check      (doar verifică instalarea, nu schimbă nimic)
 */
class Install extends Command
{
    protected $signature = 'app:install
        {--check : doar verifică instalarea și afișează ce lipsește}
        {--name= : denumirea organizatorului (clientul)}
        {--url= : adresa instalării (APP_URL), ex. https://salsa.exemplu.ro}
        {--admin-name=Administrator : numele superadmin-ului clientului}
        {--admin-phone= : telefonul superadmin-ului clientului (login)}
        {--admin-password= : parola superadmin-ului; dacă lipsește se generează una și se afișează o singură dată}
        {--plan=basic : planul din licență (basic | pro)}
        {--owner-phone= : telefonul contului de proprietar (furnizorul); implicit INSTALL_OWNER_PHONE din mediu}
        {--owner-password= : parola contului de proprietar; implicit INSTALL_OWNER_PASSWORD din mediu}
        {--cui= : CUI (texte legale)} {--regcom= : nr. Registrul Comerțului} {--address= : sediul} {--email= : e-mail de contact} {--phone= : telefon de contact}
        {--app-env=production : APP_ENV de scris în .env}
        {--push-keys : generează cheile pentru notificări push și le scrie în .env (dacă nu există)}
        {--no-env : nu modifica fișierul .env}';

    protected $description = 'Instalează o instanță nouă pentru un client: .env, migrări, conturi, plan, nume, texte legale';

    public function handle(): int
    {
        if ($this->option('check')) {
            return $this->check();
        }

        foreach (['name', 'url', 'admin-phone'] as $required) {
            if (blank($this->option($required))) {
                $this->error("Lipsește --{$required}.");

                return self::FAILURE;
            }
        }
        $name = trim((string) $this->option('name'));
        $url = rtrim(trim((string) $this->option('url')), '/');
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            $this->error('--url nu e o adresă validă (ex. https://salsa.exemplu.ro).');

            return self::FAILURE;
        }

        try {
            $this->prepareEnv($name, $url);

            $this->components->task('Migrări', fn () => Artisan::call('migrate', ['--force' => true]) === 0);
            $this->components->task('Legătura public/storage', function () {
                if (! is_link(public_path('storage')) && ! is_dir(public_path('storage'))) {
                    try {
                        Artisan::call('storage:link');
                    } catch (\Throwable $e) {
                        $this->warn('Nu am putut crea legătura public/storage (hostingul poate bloca symlink): '.$e->getMessage());

                        return false;
                    }
                }

                return true;
            });

            if ($this->option('push-keys') && ! $this->option('no-env')) {
                $subject = 'mailto:'.($this->option('email') ?: 'contact@'.(parse_url($url, PHP_URL_HOST) ?: 'exemplu.ro'));
                $this->line(Installer::writePushKeys(base_path('.env'), $subject) ? 'Chei push generate și scrise în .env.' : 'Cheile push există deja.');
            }

            Installer::setOrganizationName($name);
            Installer::setPlan((string) $this->option('plan'));
            $this->line("Organizator: {$name} · plan: {$this->option('plan')}");

            $admin = Installer::ensureSuperadmin((string) $this->option('admin-phone'), (string) $this->option('admin-name'), $this->option('admin-password'));
            if ($admin['created']) {
                $this->info("Superadmin creat: {$admin['admin']->phone}".($admin['password'] ? "  ·  parola generată: {$admin['password']}  (se afișează o singură dată; schimb-o la prima autentificare)" : ''));
            } else {
                $this->line("Superadmin existent ({$admin['admin']->phone}); nu l-am schimbat.");
            }

            $this->owner();
            $this->legal();
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('Rămâne pe server: cron-ul `* * * * * cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1`, SMS_DRIVER/SMSO_*, cheile Stripe ale clientului și `php artisan app:push-keys` (dacă clientul are notificări).');
        $this->line('Verifică totul cu:  php artisan app:install --check');

        return self::SUCCESS;
    }

    private function prepareEnv(string $name, string $url): void
    {
        if ($this->option('no-env')) {
            config(['app.name' => $name, 'app.url' => $url]);

            return;
        }

        $path = base_path('.env');
        if (! is_file($path)) {
            if (! is_file(base_path('.env.example'))) {
                throw new DomainException('Nu există .env și nici .env.example.');
            }
            copy(base_path('.env.example'), $path);
        }

        Installer::writeEnv($path, ['APP_NAME' => $name, 'APP_URL' => $url, 'APP_ENV' => (string) $this->option('app-env'), 'APP_DEBUG' => 'false']);
        config(['app.name' => $name, 'app.url' => $url]);

        if (blank(config('app.key'))) {
            Artisan::call('key:generate', ['--force' => true]);
            $this->line('APP_KEY generat.');
        }
        $this->line('.env actualizat (APP_NAME, APP_URL, APP_ENV, APP_DEBUG).');
    }

    private function owner(): void
    {
        $phone = $this->option('owner-phone') ?: env('INSTALL_OWNER_PHONE');
        $password = $this->option('owner-password') ?: env('INSTALL_OWNER_PASSWORD');
        if (blank($phone) || blank($password)) {
            $this->line('Cont proprietar: sărit (nu s-au dat --owner-phone / --owner-password).');

            return;
        }

        $this->line(Installer::ensureOwner((string) $phone, (string) $password) === 'created' ? 'Cont proprietar creat.' : 'Cont proprietar existent; nu l-am schimbat.');
    }

    private function legal(): void
    {
        $args = array_filter([
            '--name' => $this->option('name'), '--cui' => $this->option('cui'), '--regcom' => $this->option('regcom'),
            '--address' => $this->option('address'), '--email' => $this->option('email'), '--phone' => $this->option('phone'),
        ], fn ($v) => filled($v));

        Artisan::call('app:install-legal', $args, $this->output);
    }

    private function check(): int
    {
        $bad = 0;
        foreach (Installer::checks() as $row) {
            if ($row['ok']) {
                $this->line('<fg=green>✔</> '.$row['label']);
            } else {
                $bad++;
                $this->line('<fg=red>✘</> '.$row['label'].($row['hint'] ? ' — '.$row['hint'] : ''));
            }
        }
        $this->newLine();
        $this->line($bad === 0 ? 'Totul e în regulă.' : "{$bad} lucruri de rezolvat.");

        return $bad === 0 ? self::SUCCESS : self::FAILURE;
    }
}
