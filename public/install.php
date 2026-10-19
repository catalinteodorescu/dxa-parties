<?php

/**
 * Instalare și actualizare din browser, pentru hosting partajat (fără terminal). Vezi docs/INSTALARE-CPANEL.md.
 * Nu face nimic fără fișierul `.install-key` din rădăcina aplicației (în afara lui public/); după instalare ȘTERGE acel fișier.
 */

use App\Services\Installer;
use App\Services\WebInstaller;
use Illuminate\Contracts\Console\Kernel;

// Verificare de versiune înainte de orice altceva (scrisă compatibil cu PHP vechi, ca mesajul să apară și pe 7.x).
if (version_compare(PHP_VERSION, '8.4.1', '<')) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="ro"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Versiune PHP</title></head>'
        .'<body style="font:16px/1.5 system-ui,sans-serif;max-width:640px;margin:40px auto;padding:0 16px">'
        .'<h1>Versiunea PHP nu e suficientă</h1>'
        .'<p>Serverul rulează <b>PHP '.htmlspecialchars(PHP_VERSION).'</b>, iar aplicația cere <b>PHP 8.4 sau mai nou</b>.</p>'
        .'<p>Ce poți face: în cPanel › <b>MultiPHP Manager</b> alege PHP 8.4 pentru acest domeniu/subdomeniu (versiunea se setează per domeniu). '
        .'Dacă interfața e blocată, încearcă una dintre aceste linii la începutul fișierului <code>.htaccess</code> din rădăcina aplicației:</p>'
        .'<pre style="background:#f1f1f5;padding:12px;border-radius:8px;overflow:auto">AddHandler application/x-httpd-ea-php84 .php</pre>'
        .'<p>sau, pe hosturi cu CloudLinux:</p>'
        .'<pre style="background:#f1f1f5;padding:12px;border-radius:8px;overflow:auto">AddHandler application/x-httpd-alt-php84___lsphp .php</pre>'
        .'<p>Dacă niciuna nu merge, cere hostingului PHP 8.4 pentru acest domeniu.</p></body></html>';
    exit;
}

define('LARAVEL_START', microtime(true));

$base = dirname(__DIR__);
$h = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

function page(string $title, string $body): void
{
    echo '<!doctype html><html lang="ro"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">'
        .'<title>'.htmlspecialchars($title).'</title><style>'
        .'body{font:16px/1.5 system-ui,sans-serif;background:#f6f6f8;color:#22222b;margin:0;padding:24px}main{max-width:640px;margin:0 auto;background:#fff;border:1px solid #e3e3ea;border-radius:16px;padding:28px}'
        .'h1{font-size:1.3rem;margin:0 0 .5rem}h2{font-size:1rem;margin:1.5rem 0 .5rem}label{display:block;font-size:.85rem;color:#555;margin:.8rem 0 .2rem}'
        .'input,select{width:100%;box-sizing:border-box;padding:.6rem .7rem;border:1px solid #cfcfd8;border-radius:8px;font:inherit}button{margin-top:1.4rem;padding:.7rem 1.2rem;border:0;border-radius:8px;background:#22222b;color:#fff;font:inherit;cursor:pointer}'
        .'.err{color:#b3261e;font-size:.85rem;margin:.2rem 0 0}.box{background:#fdecea;border:1px solid #f5c2bd;border-radius:8px;padding:.7rem 1rem;margin:1rem 0}.ok{background:#e8f5e9;border-color:#b7dfba}'
        .'pre{background:#f1f1f5;border-radius:8px;padding:.8rem;overflow:auto;font-size:.8rem;white-space:pre-wrap}.row{display:grid;grid-template-columns:1fr 1fr;gap:12px}small{color:#777}'
        .'</style></head><body><main>'.$body.'</main></body></html>';
}

/** Afișează o eroare de instalare (doar după verificarea cheii): mesaj, loc și începutul stivei de apeluri. */
function failPage(Throwable $e): void
{
    $trace = implode("\n", array_slice(explode("\n", $e->getTraceAsString()), 0, 12));
    page('Eroare la instalare', '<h1>Instalarea a eșuat</h1><div class="box"><b>'.htmlspecialchars(get_class($e)).'</b><br>'.htmlspecialchars($e->getMessage()).'</div>'
        .'<p><small>'.htmlspecialchars($e->getFile()).':'.(int) $e->getLine().'</small></p><pre>'.htmlspecialchars($trace).'</pre>'
        .'<p>Trimite-mi acest mesaj. Poți relua instalarea după ce e rezolvat (pașii deja făcuți nu se strică).</p>');
}

if (! is_file($base.'/vendor/autoload.php')) {
    page('Instalare', '<h1>Lipsește folderul vendor</h1><p>Pachetul încărcat nu conține folderul <code>vendor/</code>. Încarcă pachetul complet pregătit pentru instalare.</p>');
    exit;
}

require $base.'/vendor/autoload.php';

$boot = function () use ($base) {
    $app = require $base.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
};
$installer = new WebInstaller($base, $boot);

if (! $installer->keyConfigured()) {
    page('Instalare', '<h1>Cheia de instalare lipsește</h1><p>Din motive de siguranță, această pagină nu funcționează până nu creezi, în <b>rădăcina aplicației</b> (nu în <code>public</code>), un fișier numit <code>.install-key</code> care conține un șir lung la alegere (minimum '.WebInstaller::MIN_KEY_LENGTH.' caractere). Îl creezi în File Manager din cPanel, apoi reîncarci pagina.</p>');
    exit;
}

$in = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : [];
$installed = $installer->installed();
$mode = $installed ? 'update' : 'install';

$field = function (string $name, string $label, string $value = '', string $type = 'text', string $hint = '') use ($h, &$errors, $in) {
    $v = $in[$name] ?? $value;
    $e = $errors[$name] ?? '';

    return '<label for="'.$name.'">'.$h($label).'</label><input id="'.$name.'" name="'.$name.'" type="'.$type.'" value="'.($type === 'password' ? '' : $h($v)).'">'
        .($hint ? '<small>'.$h($hint).'</small>' : '').($e ? '<div class="err">'.$h($e).'</div>' : '');
};
$errors = [];

if ($in) {
    if (! $installer->keyMatches((string) ($in['key'] ?? ''))) {
        sleep(2);
        $errors['key'] = 'Cheia de instalare nu e corectă.';
    } elseif ($mode === 'update' && ($in['action'] ?? '') === 'check') {
        try {
            $boot();
            $rows = Installer::checks();
        } catch (Throwable $e) {
            failPage($e);
            exit;
        }
        $bad = 0;
        $list = '';
        foreach ($rows as $row) {
            if (! $row['ok']) {
                $bad++;
            }
            $list .= '<li>'.($row['ok'] ? '✔ ' : '✘ ').$h($row['label']).(! $row['ok'] && $row['hint'] ? ' — <small>'.$h($row['hint']).'</small>' : '').'</li>';
        }
        page('Verificare', '<h1>Verificarea instalării</h1><ul style="list-style:none;padding:0">'.$list.'</ul><p>'.($bad === 0 ? 'Totul e în regulă.' : $bad.' lucruri de rezolvat.').'</p><p>Șterge fișierul <code>.install-key</code> după ce ai terminat.</p>');
        exit;
    } elseif ($mode === 'update' && ($in['action'] ?? '') === 'demo') {
        try {
            $r = $installer->demo();
        } catch (Throwable $e) {
            failPage($e);
            exit;
        }
        page('Date demo', '<h1>'.($r['ok'] ? 'Date demo încărcate' : 'Încărcarea a eșuat').'</h1><pre>'.$h($r['output']).'</pre><p>Conturile demo au telefoanele 0700000001 … 0700000020.</p><p>Șterge acum fișierul <code>.install-key</code>.</p>');
        exit;
    } elseif ($mode === 'update') {
        try {
            $r = $installer->update();
        } catch (Throwable $e) {
            failPage($e);
            exit;
        }
        page('Actualizare', '<h1>'.($r['ok'] ? 'Actualizare terminată' : 'Actualizarea a eșuat').'</h1><pre>'.$h($r['output']).'</pre><p>Șterge acum fișierul <code>.install-key</code> din rădăcina aplicației.</p>');
        exit;
    } else {
        $errors = $installer->validate($in);
        if (! $errors && ($dbError = $installer->checkDatabase($in))) {
            $errors['db_name'] = $dbError;
        }
        if (! $errors) {
            register_shutdown_function(function () {
                $err = error_get_last();
                if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                    if (! headers_sent()) {
                        header('Content-Type: text/html; charset=utf-8');
                    }
                    echo '<pre style="white-space:pre-wrap">Eroare fatală: '.htmlspecialchars($err['message']).' în '.htmlspecialchars($err['file']).':'.(int) $err['line'].'</pre>';
                }
            });
            try {
                $r = $installer->install($in);
            } catch (Throwable $e) {
                failPage($e);
                exit;
            }
            // Executabilul CLI al aceleiași versiuni PHP care rulează acum pagina (pe CloudLinux e /opt/alt/phpNN/usr/bin/php, nu /usr/local/bin/php).
            $php = '/usr/local/bin/php';
            if (preg_match('#^(.*/)(lsphp|php-cgi|php-fpm|php-cli)$#', (string) PHP_BINARY, $m) && is_file($m[1].'php')) {
                $php = $m[1].'php';
            }
            $cron = '*/15 * * * * '.$php.' '.$base.'/artisan schedule:run >> /dev/null 2>&1';
            page('Instalare', '<h1>'.($r['ok'] ? 'Instalare terminată' : 'Instalarea a eșuat').'</h1>'
                .'<pre>'.$h($r['output']).'</pre>'
                .($r['ok'] ? '<div class="box ok"><b>Parola administratorului</b> apare mai sus (dacă nu ai ales una) și nu se mai afișează. Notează-o acum.</div>'
                    .'<h2>Ce mai ai de făcut</h2><ol><li><b>Șterge fișierul <code>.install-key</code></b> din rădăcina aplicației (File Manager).</li>'
                    .'<li>În cPanel › <b>Cron Jobs</b> adaugă (la fiecare 15 minute; verifică întâi cu <code>php -v</code> că executabilul de mai jos este PHP 8.4): <pre>'.$h($cron).'</pre></li>'
                    .'<li>Completează în <code>.env</code> (File Manager › Edit) SMS-ul și, dacă e cazul, Stripe-ul clientului; apoi intră în aplicație la <code>/admin</code>.</li></ol>' : ''));
            exit;
        }
    }
}

if ($mode === 'update') {
    page('Actualizare', '<h1>Actualizare</h1><p>Aplicația este deja instalată. După ce ai încărcat versiunea nouă a codului, apasă butonul: rulează migrările și golește cache-urile.</p>'
        .'<form method="post">'.$field('key', 'Cheia de instalare', '', 'password').'<button name="action" value="update">Actualizează</button> <button name="action" value="check" style="background:#fff;color:#22222b;border:1px solid #cfcfd8">Verifică instalarea</button></form>'
        .'<h2>Instanță de demonstrație</h2><p><small>Încarcă date fictive (petreceri, participanți, bilete, bar) și activează DEMO_MODE. Folosește doar pe o instanță demo, nu pe una cu clienți reali.</small></p>'
        .'<form method="post" onsubmit="return confirm(\'Încarci date demo pe această instalare?\')">'.$field('key', 'Cheia de instalare', '', 'password').'<button name="action" value="demo">Încarcă date demo</button></form>');
    exit;
}

$host = ($_SERVER['HTTP_HOST'] ?? '');
$scheme = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

page('Instalare', '<h1>Instalare aplicație</h1><p>Completează datele clientului. Baza de date o creezi mai întâi în cPanel › MySQL Databases (bază + utilizator, cu toate drepturile).</p>'
    .'<form method="post" autocomplete="off">'
    .$field('key', 'Cheia de instalare (din fișierul .install-key)', '', 'password')
    .'<h2>Organizator (client)</h2>'
    .$field('name', 'Denumirea organizatorului')
    .$field('url', 'Adresa instalării', $scheme.'://'.$host)
    .'<h2>Baza de date</h2><div class="row"><div>'.$field('db_host', 'Server', 'localhost').'</div><div>'.$field('db_port', 'Port', '3306').'</div></div>'
    .$field('db_name', 'Numele bazei de date').$field('db_user', 'Utilizator').$field('db_password', 'Parola bazei de date', '', 'password')
    .'<h2>Administrator (superadmin)</h2>'
    .$field('admin_name', 'Nume', 'Administrator').$field('admin_phone', 'Telefon (login)')
    .$field('admin_password', 'Parolă', '', 'password', 'Lasă gol ca să se genereze una și să ți-o arăt o singură dată.')
    .'<h2>Plan</h2><label for="plan">Plan</label><select id="plan" name="plan"><option value="basic">Basic</option><option value="pro"'.((($in['plan'] ?? '') === 'pro') ? ' selected' : '').'>Pro</option></select>'
    .'<h2>Date pentru textele legale <small>(opțional; fără ele se completează ulterior din admin › Legal)</small></h2>'
    .$field('cui', 'CUI').$field('regcom', 'Nr. înregistrare la Registrul Comerțului').$field('address', 'Sediul').$field('email', 'E-mail de contact', '', 'email').$field('phone', 'Telefon de contact')
    .'<h2>Cont de proprietar <small>(furnizorul; opțional)</small></h2>'
    .$field('owner_phone', 'Telefon proprietar').$field('owner_password', 'Parolă proprietar', '', 'password')
    .'<button>Instalează</button></form>');
