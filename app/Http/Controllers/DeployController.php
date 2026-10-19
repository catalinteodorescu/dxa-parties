<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Pașii de după urcarea fișierelor pe server (apelat automat de GitHub Actions, fără SSH):
 * migrări, ștergerea cache-urilor compilate (views, config, rute, evenimente, bootstrap/cache) și resetarea OPcache.
 * `vendor/` nu se urcă prin FTP (zeci de mii de fișiere); se actualizează manual când se schimbă composer.lock (vezi docs/DEPLOY.md).
 * Ruta: GET /deploy/{DEPLOY_KEY}. Fără cheie în .env, sau cu cheie greșită, răspunde 404.
 */
class DeployController extends Controller
{
    public function __invoke(Request $request, string $key)
    {
        $expected = (string) config('deploy.key');

        abort_if($expected === '' || ! hash_equals($expected, $key), 404);

        // ?step=down / ?step=up: mentenanța din timpul urcării (vezi deploy.yml). Fără `step`: migrări și curățare cache.
        $step = (string) $request->query('step', '');

        if ($step === 'down' || $step === 'up') {
            return $this->maintenance($step === 'down');
        }

        $log = [];
        $ok = true;

        // Migrările rulează primele; dacă eșuează, restul pașilor se execută oricum (cache-urile vechi ar strica oricum).
        $steps = [
            'migrate' => ['--force' => true],
            'view:clear' => [],
            'config:clear' => [],
            'route:clear' => [],
            'event:clear' => [],
        ];

        foreach ($steps as $command => $arguments) {
            try {
                $output = new BufferedOutput;
                $status = Artisan::call($command, $arguments, $output);
                $log[] = "[{$command}] ".($status === 0 ? 'ok' : "cod {$status}");

                if (($text = trim($output->fetch())) !== '') {
                    $log[] = $text;
                }

                $ok = $ok && $status === 0;
            } catch (\Throwable $e) {
                $ok = false;
                $log[] = "[{$command}] eroare: ".$e->getMessage();
            }
        }

        if (config('deploy.clean_cache')) {
            foreach (array_merge(glob(base_path('bootstrap/cache/*.php')) ?: [], glob(storage_path('framework/views/*.php')) ?: []) as $file) {
                @unlink($file);
            }
            $log[] = '[bootstrap/cache, storage/framework/views] curățat';
        }

        if (function_exists('opcache_reset')) {
            @opcache_reset();
            $log[] = '[opcache] resetat';
        }

        // Diagnostic: ce versiune e pe server (scrisă de CI).
        $version = base_path('bootstrap/version.txt');
        $log[] = '[versiune] '.(is_file($version) ? trim((string) file_get_contents($version)) : 'necunoscută');
        $log[] = '[php] '.PHP_VERSION.', opcache.validate_timestamps='.var_export(ini_get('opcache.validate_timestamps'), true);

        $log[] = $ok ? 'Gata.' : 'Deploy-ul a avut erori (vezi mai sus).';

        return response(implode("\n", $log), $ok ? 200 : 500)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    /** Pune aplicația în mentenanță (pagina errors/503, pre-randată) sau o scoate din mentenanță. */
    private function maintenance(bool $down)
    {
        try {
            if ($down) {
                try {
                    Artisan::call('down', ['--render' => 'errors.503', '--retry' => 60, '--refresh' => 15]);
                    $text = 'mentenanță pornită';
                } catch (\Throwable $e) {
                    // Prima rulare: pagina errors/503 nu e încă pe server; folosim pagina implicită.
                    Artisan::call('down', ['--retry' => 60, '--refresh' => 15]);
                    $text = 'mentenanță pornită (pagina implicită: '.$e->getMessage().')';
                }
            } else {
                Artisan::call('up');
                $text = 'mentenanță oprită';
            }

            // Fișierul down nu trebuie să rămână în cache-ul de opcode după `up`.
            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }

            return response('[mentenanță] '.$text, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
        } catch (\Throwable $e) {
            return response('[mentenanță] eroare: '.$e->getMessage(), 500)->header('Content-Type', 'text/plain; charset=UTF-8');
        }
    }
}
