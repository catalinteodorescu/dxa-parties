<?php

namespace App\Services;

use App\Services\Plan\PlanGate;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Adăugat (runda 84). Instalarea și actualizarea fără terminal (hosting partajat, cPanel): pagina `public/install.php` o rulează din browser.
 *
 * Securitate: pagina nu face nimic fără fișierul `.install-key` (în rădăcina aplicației, în afara lui public/), creat de furnizor
 * în File Manager cu un șir lung la alegere, și cere acel șir în formular. După instalare se șterge fișierul `.install-key`:
 * fără el, `install.php` refuză orice. Instalarea reușită scrie `storage/installed.lock`; după asta pagina poate face doar actualizarea.
 */
final class WebInstaller
{
    public const MIN_KEY_LENGTH = 12;

    /** @param  \Closure():void  $boot  pornește aplicația Laravel (după ce .env e scris) */
    public function __construct(private readonly string $basePath, private readonly \Closure $boot) {}

    public function keyFile(): string
    {
        return $this->basePath.'/.install-key';
    }

    public function lockFile(): string
    {
        return $this->basePath.'/storage/installed.lock';
    }

    public function keyConfigured(): bool
    {
        return is_file($this->keyFile()) && strlen($this->secret()) >= self::MIN_KEY_LENGTH;
    }

    public function installed(): bool
    {
        return is_file($this->lockFile());
    }

    public function keyMatches(string $given): bool
    {
        return $this->keyConfigured() && hash_equals($this->secret(), trim($given));
    }

    private function secret(): string
    {
        return trim((string) @file_get_contents($this->keyFile()));
    }

    /** @param array<string, mixed> $in @return array<string, string> câmp => mesaj */
    public function validate(array $in): array
    {
        $errors = [];
        $get = fn (string $k) => trim((string) ($in[$k] ?? ''));

        if (! $this->keyMatches($get('key'))) {
            return ['key' => 'Cheia de instalare nu e corectă.'];
        }
        foreach (['name' => 'Denumirea organizatorului', 'url' => 'Adresa instalării', 'db_name' => 'Numele bazei de date', 'db_user' => 'Utilizatorul bazei de date', 'admin_phone' => 'Telefonul administratorului'] as $k => $label) {
            if ($get($k) === '') {
                $errors[$k] = "{$label} lipsește.";
            }
        }
        if ($get('url') !== '' && ! filter_var($get('url'), FILTER_VALIDATE_URL)) {
            $errors['url'] = 'Adresa nu e validă (ex. https://salsa.exemplu.ro).';
        }
        if ($get('admin_password') !== '' && strlen($get('admin_password')) < 8) {
            $errors['admin_password'] = 'Parola trebuie să aibă cel puțin 8 caractere.';
        }
        if ($get('email') !== '' && ! filter_var($get('email'), FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'E-mailul nu e valid.';
        }
        if (! in_array($get('plan') ?: 'basic', ['basic', 'pro'], true)) {
            $errors['plan'] = 'Planul trebuie să fie basic sau pro.';
        }
        if (($get('owner_phone') === '') !== ($get('owner_password') === '')) {
            $errors['owner_phone'] = 'Pentru contul de proprietar completează și telefonul, și parola (sau lasă-le goale).';
        }

        return $errors;
    }

    /** Verifică conexiunea MySQL înainte să scrie .env. @return ?string mesajul de eroare */
    public function checkDatabase(array $in): ?string
    {
        if (! in_array('mysql', \PDO::getAvailableDrivers(), true)) {
            return 'Extensia PHP pdo_mysql nu e activă (cPanel › Select PHP Version › Extensions).';
        }

        try {
            new \PDO(
                'mysql:host='.trim((string) ($in['db_host'] ?? '')).';port='.((int) ($in['db_port'] ?? 3306) ?: 3306).';dbname='.trim((string) $in['db_name']),
                trim((string) $in['db_user']), (string) ($in['db_password'] ?? ''), [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 5],
            );

            return null;
        } catch (\Throwable $e) {
            return 'Nu mă pot conecta la baza de date: '.$e->getMessage();
        }
    }

    /**
     * Instalează: scrie .env, pornește aplicația, rulează `app:install`. @param array<string, mixed> $in (deja validat)
     *
     * @return array{ok: bool, output: string}
     */
    public function install(array $in): array
    {
        $get = fn (string $k, string $d = '') => trim((string) ($in[$k] ?? '')) !== '' ? trim((string) $in[$k]) : $d;
        $env = $this->basePath.'/.env';

        if (! is_file($env)) {
            if (! is_file($this->basePath.'/.env.example')) {
                return ['ok' => false, 'output' => 'Lipsește .env.example.'];
            }
            copy($this->basePath.'/.env.example', $env);
        }

        $current = (string) file_get_contents($env);
        $values = [
            'APP_NAME' => $get('name'), 'APP_URL' => rtrim($get('url'), '/'), 'APP_ENV' => 'production', 'APP_DEBUG' => 'false',
            'DB_CONNECTION' => 'mysql', 'DB_HOST' => $get('db_host', 'localhost'), 'DB_PORT' => (string) ((int) $get('db_port', '3306') ?: 3306),
            'DB_DATABASE' => $get('db_name'), 'DB_USERNAME' => $get('db_user'), 'DB_PASSWORD' => (string) ($in['db_password'] ?? ''),
            'SESSION_DRIVER' => 'database', 'CACHE_STORE' => 'database', 'QUEUE_CONNECTION' => 'sync', 'LOG_LEVEL' => 'warning',
        ];
        if (! preg_match('/^APP_KEY=.+$/m', $current)) {
            $values['APP_KEY'] = Installer::generateAppKey();
        }
        Installer::writeEnv($env, $values);

        ($this->boot)();

        $out = new BufferedOutput;
        $args = ['--no-env' => true, '--name' => $get('name'), '--url' => $get('url'), '--admin-phone' => $get('admin_phone'),
            '--admin-name' => $get('admin_name', 'Administrator'), '--plan' => $get('plan', 'basic')];
        foreach (['admin_password' => '--admin-password', 'owner_phone' => '--owner-phone', 'owner_password' => '--owner-password',
            'cui' => '--cui', 'regcom' => '--regcom', 'address' => '--address', 'email' => '--email', 'phone' => '--phone'] as $field => $option) {
            if ($get($field) !== '') {
                $args[$option] = $get($field);
            }
        }

        $code = Artisan::call('app:install', $args, $out);
        $text = $out->fetch();

        if ($code === 0) {
            $subject = 'mailto:'.($get('email') ?: 'contact@'.(parse_url($get('url'), PHP_URL_HOST) ?: 'exemplu.ro'));
            $text .= Installer::writePushKeys($env, $subject) ? "\nChei pentru notificări push generate.\n" : '';
            @file_put_contents($this->lockFile(), date('c'));
        }

        return ['ok' => $code === 0, 'output' => $text];
    }

    /** Actualizarea unei instalări existente (după ce ai încărcat codul nou): migrări + golirea cache-urilor. @return array{ok: bool, output: string} */
    public function update(): array
    {
        ($this->boot)();
        $out = new BufferedOutput;
        $code = Artisan::call('migrate', ['--force' => true], $out);
        $text = $out->fetch();
        Artisan::call('optimize:clear');
        PlanGate::flush();

        return ['ok' => $code === 0, 'output' => $text."\nCache-urile au fost golite."];
    }
}
