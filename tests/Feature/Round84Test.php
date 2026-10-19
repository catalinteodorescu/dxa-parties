<?php

use App\Models\Admin;
use App\Models\License;
use App\Services\Installer;
use App\Services\PrivacyPolicy;
use App\Services\Terms;
use App\Services\WebInstaller;
use App\Support\Branding;
use App\Support\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Console\Output\BufferedOutput;

uses(RefreshDatabase::class);

/** Teste (runda 84). Procedura de instalare per client și aplicația fără valori ale unui anumit organizator. */
/** @return array{0: int, 1: string} codul și ieșirea comenzii (Artisan::output() ar arăta doar ultimul apel imbricat, ex. migrate) */
function r84Run(string $cmd, array $args = []): array
{
    $out = new BufferedOutput;
    $code = Artisan::call($cmd, $args, $out);

    return [$code, $out->fetch()];
}

function r84Args(array $extra = []): array
{
    return $extra + [
        '--no-env' => true, '--name' => 'Club Salsa SRL', '--url' => 'https://salsa.exemplu.ro', '--admin-phone' => '0722000111',
        '--plan' => 'basic', '--cui' => 'RO123456', '--regcom' => 'J32/1/2020', '--address' => 'Str. Mare 1, Sibiu',
        '--email' => 'contact@salsa.ro', '--phone' => '0722000222',
    ];
}

it('r84: writeEnv actualizează cheile existente, adaugă ce lipsește și pune ghilimele valorilor cu spații', function () {
    $path = tempnam(sys_get_temp_dir(), 'env');
    file_put_contents($path, "APP_NAME=Laravel\nAPP_DEBUG=true\nDB_HOST=127.0.0.1\n");

    Installer::writeEnv($path, ['APP_NAME' => 'Club Salsa', 'APP_DEBUG' => 'false', 'APP_URL' => 'https://x.ro']);
    $env = file_get_contents($path);
    unlink($path);

    expect($env)->toContain('APP_NAME="Club Salsa"')->toContain('APP_DEBUG=false')->toContain('APP_URL=https://x.ro')->toContain('DB_HOST=127.0.0.1')
        ->and(substr_count($env, 'APP_NAME='))->toBe(1);
});

it('r84: app:install cere numele, adresa și telefonul administratorului', function () {
    [$code, $out] = r84Run('app:install', ['--no-env' => true, '--url' => 'https://x.ro', '--admin-phone' => '0722000111']);
    expect($code)->toBe(1)->and($out)->toContain('--name');
    expect(Artisan::call('app:install', ['--no-env' => true, '--name' => 'X', '--url' => 'nu-e-url', '--admin-phone' => '0722000111']))->toBe(1);
    expect(Admin::query()->where('role', 'superadmin')->count())->toBe(0);
});

it('r84: app:install creează superadmin-ul cu parolă generată, setează planul și numele și publică textele legale', function () {
    [$code, $out] = r84Run('app:install', r84Args());
    expect($code)->toBe(0);

    $admin = Admin::query()->where('role', 'superadmin')->first();
    expect($admin->phone)->toBe('0722000111')->and($admin->is_active)->toBeTrue()
        ->and($out)->toContain('parola generată');
    preg_match('/parola generată: (\S+)/', $out, $m);
    expect(Hash::check($m[1], $admin->getRawOriginal('password')))->toBeTrue()
        ->and(License::query()->first()->plan_key)->toBe('basic')
        ->and(Settings::get('school_name'))->toBe('Club Salsa SRL')->and(Branding::name())->toBe('Club Salsa SRL')
        ->and(Terms::enabled())->toBeTrue()->and(PrivacyPolicy::enabled())->toBeTrue()
        ->and(Terms::current()->body)->toContain('Club Salsa SRL')->toContain('RO123456');
});

it('r84: a doua rulare nu schimbă nimic (superadmin, plan, texte)', function () {
    Artisan::call('app:install', r84Args());
    $id = Admin::query()->where('role', 'superadmin')->value('id');
    $version = Terms::current()->id;

    [$code, $out] = r84Run('app:install', r84Args(['--plan' => 'pro', '--admin-phone' => '0722999999']));
    expect($code)->toBe(0);
    expect($out)->toContain('Superadmin existent')->toContain('deja publicați')->not->toContain('parola generată');
    expect(Admin::query()->where('role', 'superadmin')->count())->toBe(1)->and(Admin::query()->where('role', 'superadmin')->value('id'))->toBe($id)
        ->and(Terms::current()->id)->toBe($version)->and(License::query()->first()->plan_key)->toBe('pro');
});

it('r84: fără datele pentru textele legale instalarea merge mai departe, dar nu publică nimic', function () {
    expect(Artisan::call('app:install', ['--no-env' => true, '--name' => 'Club', '--url' => 'https://c.ro', '--admin-phone' => '0722000111']))->toBe(0)
        ->and(Terms::enabled())->toBeFalse()->and(PrivacyPolicy::enabled())->toBeFalse()
        ->and(Admin::query()->where('role', 'superadmin')->exists())->toBeTrue();
});

it('r84: parola dată nu se afișează, iar una prea scurtă e refuzată', function () {
    [$code, $out] = r84Run('app:install', r84Args(['--admin-password' => 'parola-ta-lunga']));
    expect($code)->toBe(0)
        ->and($out)->not->toContain('parola-ta-lunga')->not->toContain('parola generată');

    Admin::query()->delete();
    [$code, $out] = r84Run('app:install', r84Args(['--admin-password' => 'scurta']));
    expect($code)->toBe(1)->and($out)->toContain('8 caractere');
});

it('r84: contul de proprietar se creează doar dacă i se dau telefonul și parola', function () {
    Artisan::call('app:install', r84Args());
    expect(Admin::query()->where('role', 'owner')->count())->toBe(0);

    Artisan::call('app:install', r84Args(['--owner-phone' => '0733000111', '--owner-password' => 'parola-owner-1']));
    expect(Admin::query()->where('role', 'owner')->where('phone', '0733000111')->count())->toBe(1);
});

it('r84: planul inexistent e refuzat', function () {
    [$code, $out] = r84Run('app:install', r84Args(['--plan' => 'inexistent']));
    expect($code)->toBe(1)->and($out)->toContain('inexistent');
});

it('r84: --check listează ce lipsește și iese cu cod de eroare', function () {
    [$code, $out] = r84Run('app:install', ['--check' => true]);

    expect($code)->toBe(1)->and($out)->toContain('Superadmin existent')->toContain('Texte legale publicate')->toContain('app:install');

    Artisan::call('app:install', r84Args());
    [, $out] = r84Run('app:install', ['--check' => true]);
    expect($out)->toContain('✔ Superadmin existent')->toContain('✔ Texte legale publicate');
});

it('r84: numele implicit al organizatorului vine din APP_NAME, nu dintr-o valoare fixă', function () {
    config(['app.name' => 'Alt Organizator']);
    expect(Branding::name())->toBe('Alt Organizator');

    config(['app.name' => '']);
    expect(Branding::defaultName())->toBe('Organizator');
});

it('r84: în cod nu mai rămân identificatori sau texte ale unui anumit organizator', function () {
    $bad = [];
    foreach (['app', 'config', 'routes', 'resources/views', 'resources/js', 'resources/css', 'database/migrations', 'database/seeders'] as $dir) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($dir), FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (! preg_match('/\.(php|js|css)$/', $file->getFilename())) {
                continue;
            }
            foreach (file($file->getPathname()) as $n => $line) {
                if (preg_match('/dxa[:\-_.A-Z]|DXA_[A-Z]|DXA:[PT]:|[Xx]plosion|DXA Parties|echipa DXA/', $line) && ! preg_match('/^\s*(\/\/|\*|\/\*|#|\{\{--)/', $line)) {
                    $bad[] = str_replace(base_path().'/', '', $file->getPathname()).':'.($n + 1);
                }
            }
        }
    }

    expect($bad)->toBe([]);
});

// ---------- instalarea din browser (hosting partajat, fără terminal) ----------

function r84Base(bool $withKey = true): string
{
    $dir = sys_get_temp_dir().'/r84-'.bin2hex(random_bytes(4));
    mkdir($dir.'/storage', 0777, true);
    copy(base_path('.env.example'), $dir.'/.env.example');
    if ($withKey) {
        file_put_contents($dir.'/.install-key', "cheie-de-instalare-lunga\n");
    }

    return $dir;
}

function r84Web(string $dir): WebInstaller
{
    return new WebInstaller($dir, fn () => null);
}

function r84Form(array $extra = []): array
{
    return $extra + [
        'key' => 'cheie-de-instalare-lunga', 'name' => 'Club Salsa SRL', 'url' => 'https://salsa.exemplu.ro', 'db_host' => 'localhost', 'db_port' => '3306',
        'db_name' => 'cp_salsa', 'db_user' => 'cp_user', 'db_password' => 'p@ss word', 'admin_phone' => '0722000111', 'plan' => 'basic',
        'cui' => 'RO123456', 'regcom' => 'J32/1/2020', 'address' => 'Str. Mare 1, Sibiu', 'email' => 'contact@salsa.ro', 'phone' => '0722000222',
    ];
}

it('r84: instalarea din browser cere fișierul .install-key și cheia corectă', function () {
    $none = r84Web(r84Base(false));
    expect($none->keyConfigured())->toBeFalse()->and($none->keyMatches('orice'))->toBeFalse();

    $dir = r84Base();
    file_put_contents($dir.'/.install-key', 'scurta');
    expect(r84Web($dir)->keyConfigured())->toBeFalse();

    $w = r84Web(r84Base());
    expect($w->keyConfigured())->toBeTrue()->and($w->keyMatches('cheie-de-instalare-lunga'))->toBeTrue()
        ->and($w->keyMatches('alta-cheie'))->toBeFalse()->and($w->validate(['key' => 'alta-cheie']))->toHaveKey('key');
});

it('r84: formularul de instalare e validat', function () {
    $w = r84Web(r84Base());

    expect($w->validate(r84Form()))->toBe([]);
    expect(array_keys($w->validate(r84Form(['name' => '', 'url' => 'nu-e-url', 'admin_password' => 'scurta', 'plan' => 'x', 'email' => 'nu-e']))))
        ->toContain('name', 'url', 'admin_password', 'plan', 'email');
    expect($w->validate(r84Form(['owner_phone' => '0733'])))->toHaveKey('owner_phone');
});

it('r84: instalarea din browser scrie .env, creează conturile, publică textele și lasă blocajul', function () {
    $dir = r84Base();
    $w = r84Web($dir);

    $r = $w->install(r84Form(['admin_password' => 'parola-lunga-1']));
    $env = file_get_contents($dir.'/.env');

    expect($r['ok'])->toBeTrue()
        ->and($env)->toContain('APP_NAME="Club Salsa SRL"')->toContain('APP_URL=https://salsa.exemplu.ro')->toContain('APP_DEBUG=false')
        ->toContain('DB_CONNECTION=mysql')->toContain('DB_DATABASE=cp_salsa')->toContain('DB_PASSWORD="p@ss word"')->toContain('QUEUE_CONNECTION=sync')
        ->and($env)->toMatch('/^APP_KEY=base64:[A-Za-z0-9+\/=]{43,}$/m')
        ->and($w->installed())->toBeTrue()
        ->and(Admin::query()->where('role', 'superadmin')->where('phone', '0722000111')->exists())->toBeTrue()
        ->and(Terms::enabled())->toBeTrue();
});

it('r84: generateAppKey produce o cheie Laravel validă, iar cheile push se scriu o singură dată', function () {
    $key = Installer::generateAppKey();
    expect($key)->toStartWith('base64:')->and(strlen(base64_decode(substr($key, 7))))->toBe(32);

    $env = tempnam(sys_get_temp_dir(), 'env');
    file_put_contents($env, "APP_NAME=X\n");
    config(['services.push.public_key' => null, 'services.push.private_key' => null]);
    $ecOk = function_exists('openssl_pkey_new') && @openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]) !== false;
    if ($ecOk) {
        expect(Installer::writePushKeys($env, 'mailto:a@b.ro'))->toBeTrue()
            ->and(file_get_contents($env))->toContain('VAPID_PUBLIC_KEY=')->toContain('VAPID_PRIVATE_KEY=')->toContain('VAPID_SUBJECT=mailto:a@b.ro')
            ->and(Installer::writePushKeys($env, 'mailto:a@b.ro'))->toBeFalse();
    } else {
        expect(Installer::writePushKeys($env, 'mailto:a@b.ro'))->toBeFalse()->and(file_get_contents($env))->not->toContain('VAPID');   // fără curbe eliptice nu se strică nimic
    }
    unlink($env);
});

it('r84: install.php și pagina de instalare au sintaxă validă', function () {
    expect(file_exists(public_path('install.php')))->toBeTrue()
        ->and(file_get_contents(public_path('install.php')))->toContain('.install-key')->toContain('X-Robots-Tag');
});
