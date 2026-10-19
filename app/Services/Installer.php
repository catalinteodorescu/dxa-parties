<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\License;
use App\Models\Plan;
use App\Services\Push\Vapid;
use App\Support\Settings\Settings;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Adăugat (runda 84). Pașii instalării unei instanțe noi pentru un client (organizator): fișierul .env, contul de superadmin,
 * contul de proprietar (furnizorul), planul din licență și numele organizației. Folosit de comanda `app:install`;
 * fiecare pas e repetabil (nu strică o instalare existentă) și fără valori specifice unui client în cod.
 */
class Installer
{
    /** Scrie/actualizează cheile în .env (păstrează restul rândurilor). @param array<string, string> $values */
    public static function writeEnv(string $path, array $values): void
    {
        $text = is_file($path) ? (string) file_get_contents($path) : '';

        foreach ($values as $key => $value) {
            $line = $key.'='.(preg_match('/[\s#"\'$]/', $value) ? '"'.addcslashes($value, '"\\$').'"' : $value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            $text = preg_match($pattern, $text)
                ? preg_replace($pattern, str_replace(['\\', '$'], ['\\\\', '\\$'], $line), $text, 1)
                : rtrim($text, "\n")."\n".$line."\n";
        }

        file_put_contents($path, $text);
    }

    /** Cheie de aplicație în formatul Laravel (base64 de 32 de octeți). */
    public static function generateAppKey(): string
    {
        return 'base64:'.base64_encode(random_bytes(32));
    }

    /**
     * Generează cheile VAPID (notificări push) și le scrie în .env, dacă nu există deja. @return bool true dacă le-a scris
     */
    public static function writePushKeys(string $envPath, string $subject): bool
    {
        if (Vapid::configured()) {
            return false;
        }

        try {
            $keys = Vapid::generate();
        } catch (\Throwable) {
            return false;   // openssl fără curbe eliptice: notificările push rămân neconfigurate, instalarea merge mai departe
        }

        self::writeEnv($envPath, ['VAPID_PUBLIC_KEY' => $keys['public'], 'VAPID_PRIVATE_KEY' => $keys['private'], 'VAPID_SUBJECT' => $subject]);
        config(['services.push.public_key' => $keys['public'], 'services.push.private_key' => $keys['private']]);

        return true;
    }

    /** Superadmin-ul clientului. @return array{created: bool, password: ?string, admin: Admin} parola apare doar la creare și doar dacă a fost generată */
    public static function ensureSuperadmin(string $phone, string $name, ?string $password): array
    {
        $existing = Admin::query()->where('role', 'superadmin')->first();
        if ($existing) {
            return ['created' => false, 'password' => null, 'admin' => $existing];
        }

        $generated = $password === null || $password === '';
        $password = $generated ? Str::password(14, symbols: false) : $password;
        if (mb_strlen($password) < 8) {
            throw new DomainException('Parola trebuie să aibă cel puțin 8 caractere.');
        }

        $admin = Admin::create([
            'name' => $name, 'phone' => $phone, 'password' => $password, 'role' => 'superadmin',
            'is_active' => true, 'phone_needs_setup' => false, 'activated_at' => now(),
        ]);

        return ['created' => true, 'password' => $generated ? $password : null, 'admin' => $admin];
    }

    /** Contul de proprietar (furnizorul aplicației): ascuns din Utilizatori, vede pagina Proprietar. @return 'created'|'exists' */
    public static function ensureOwner(string $phone, string $password, string $name = 'Proprietar'): string
    {
        if (Admin::query()->where('role', 'owner')->exists() || Admin::query()->where('phone', $phone)->exists()) {
            return 'exists';
        }
        if (mb_strlen($password) < 8) {
            throw new DomainException('Parola proprietarului trebuie să aibă cel puțin 8 caractere.');
        }

        $now = now();
        DB::table('admins')->insert([
            'name' => $name, 'phone' => $phone, 'password' => bcrypt($password), 'role' => 'owner',
            'is_active' => true, 'access_admin' => true, 'access_bar' => false, 'access_reception' => false,
            'permissions' => json_encode(['levels' => [], 'actions' => []]),
            'phone_needs_setup' => false, 'activated_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);

        return 'created';
    }

    /** Planul instalării (cheia din tabelul `plans`). */
    public static function setPlan(string $planKey): void
    {
        if (! Plan::query()->where('key', $planKey)->exists()) {
            throw new DomainException("Planul „{$planKey}” nu există.");
        }

        $license = License::query()->first() ?? new License;
        $license->fill(['plan_key' => $planKey, 'overrides' => []])->save();
    }

    public static function setOrganizationName(string $name): void
    {
        Settings::set('school_name', $name);
    }

    /**
     * Verificări de după instalare (și pentru depanare). @return list<array{label: string, ok: bool, hint: string}>
     */
    public static function checks(): array
    {
        $rows = [];
        $add = function (string $label, bool $ok, string $hint = '') use (&$rows) {
            $rows[] = ['label' => $label, 'ok' => $ok, 'hint' => $hint];
        };

        $add('PHP '.PHP_VERSION, version_compare(PHP_VERSION, '8.3.0', '>='), 'Aplicația cere PHP 8.3 sau mai nou.');
        foreach (['pdo', 'mbstring', 'openssl', 'gd', 'curl', 'fileinfo', 'intl'] as $ext) {
            $add("Extensia {$ext}", extension_loaded($ext), "Activează extensia PHP {$ext}.");
        }
        $add('APP_KEY', filled(config('app.key')), 'Rulează `php artisan key:generate`.');
        $add('APP_URL', filled(config('app.url')) && ! str_contains((string) config('app.url'), 'localhost'), 'Pune adresa reală a instalării în APP_URL.');
        $add('APP_DEBUG oprit', ! config('app.debug'), 'Pe producție APP_DEBUG=false.');

        try {
            DB::connection()->getPdo();
            $add('Baza de date', true);
            $add('Migrările rulate', ! self::hasPendingMigrations(), 'Rulează `php artisan migrate --force`.');
            $add('Superadmin existent', Admin::query()->where('role', 'superadmin')->exists(), 'Rulează `php artisan app:install`.');
            $add('Plan setat', License::query()->exists(), 'Rulează `php artisan app:install --plan=basic|pro`.');
        } catch (\Throwable $e) {
            $add('Baza de date', false, 'Nu mă pot conecta: '.$e->getMessage());
        }

        $add('Folderul storage scriabil', is_writable(storage_path()), 'Dă drepturi de scriere pe storage/ și bootstrap/cache/.');
        $add('Legătura public/storage', is_link(public_path('storage')) || is_dir(public_path('storage')), 'Rulează `php artisan storage:link`.');
        $add('Texte legale publicate', Terms::enabled() && PrivacyPolicy::enabled(), 'Rulează `php artisan app:install-legal` cu datele organizatorului.');
        $add('Notificări push (chei VAPID)', Vapid::configured(), 'Rulează `php artisan app:push-keys` și pune cheile în .env.');
        $add('Driver SMS', config('sms.driver') !== 'log', 'SMS_DRIVER=log doar scrie mesajele în log; pentru trimitere reală folosește smso.');
        $add('Stripe', filled(config('services.stripe.secret')), 'Fără cheile Stripe ale clientului, plata cu cardul rămâne dezactivată.');

        return $rows;
    }

    private static function hasPendingMigrations(): bool
    {
        $files = collect(glob(database_path('migrations/*.php')))->map(fn ($f) => basename($f, '.php'));
        $ran = DB::table('migrations')->pluck('migration');

        return $files->diff($ran)->isNotEmpty();
    }
}
