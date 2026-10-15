<?php

namespace App\Livewire\Reception;

use App\Http\Middleware\ApplyAppWorker;
use App\Models\Admin;
use App\Services\ActivityLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * DXA: adaugat (runda 71). Schimbare rapidă între doi lucrători pe același telefon (Recepție / Bar): se alege numele din
 * lista conturilor cu acces la aplicație ȘI cu PIN setat (de admin, în Utilizatori), se introduce PIN-ul, iar aplicația
 * trece pe contul lui. Schimbarea ține DOAR de această aplicație (sesiune, vezi ApplyAppWorker): login-ul, Bar / Recepție (cealaltă) și panoul
 * admin rămân neatinse. Operațiunile următoare din aplicație se înregistrează pe lucrătorul activ.
 */
class SwitchWorker extends Component
{
    /** Prefixul de rută al aplicației (`receptie` / `bar`). */
    public string $prefix = 'receptie';

    public ?int $workerId = null;

    public string $pin = '';

    public ?string $error = null;

    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 300;

    private function appKey(): string
    {
        return $this->prefix === 'bar' ? Admin::APP_BAR : Admin::APP_RECEPTION;
    }

    /** @return Collection<int, Admin> */
    private function workers(): Collection
    {
        $currentId = Auth::guard('admin')->id();

        return Admin::query()
            ->where('is_active', true)
            ->whereNotNull('pin')
            ->where('id', '!=', $currentId)
            ->orderBy('name')
            ->get()
            ->filter(fn (Admin $a) => $a->canAccess($this->appKey()))
            ->values();
    }

    public function choose(int $id): void
    {
        $this->error = null;
        $this->pin = '';
        $this->workerId = $this->workers()->contains('id', $id) ? $id : null;
    }

    public function back(): void
    {
        $this->workerId = null;
        $this->pin = '';
        $this->error = null;
    }

    public function switch(): void
    {
        $this->error = null;
        $worker = $this->workerId ? $this->workers()->firstWhere('id', $this->workerId) : null;

        if (! $worker) {
            $this->back();

            return;
        }

        $keys = ['worker-switch:target:'.$worker->id, 'worker-switch:ip:'.request()->ip()];
        foreach ($keys as $key) {
            if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
                $this->pin = '';
                $this->error = 'Prea multe încercări. Mai încearcă peste '.max(1, (int) ceil(RateLimiter::availableIn($key) / 60)).' min.';

                return;
            }
        }

        if (! preg_match('/^[0-9]{4}$/', $this->pin) || ! Hash::check($this->pin, (string) $worker->pin)) {
            foreach ($keys as $key) {
                RateLimiter::hit($key, self::DECAY_SECONDS);
            }
            ActivityLogger::log('admin.switch.failed', 'PIN greșit la schimbarea lucrătorului în aplicația de '.$this->label().' pentru '.ActivityLogger::label($worker).'.', $worker);
            $this->pin = '';
            $this->error = 'PIN incorect.';

            return;
        }

        foreach ($keys as $key) {
            RateLimiter::clear($key);
        }

        $previous = Auth::guard('admin')->user();

        // Revenirea la contul cu care s-a făcut login șterge marcajul; altfel lucrătorul se ține în sesiune, pe această aplicație.
        if ($worker->id === ApplyAppWorker::loginId()) {
            session()->forget(ApplyAppWorker::key($this->appKey()));
        } else {
            session([ApplyAppWorker::key($this->appKey()) => $worker->id]);
        }
        session()->regenerate();

        ActivityLogger::log('admin.switch', 'A preluat telefonul în aplicația de '.$this->label().' (înainte: '.ActivityLogger::label($previous).').', actor: $worker);

        // Reîncărcare completă: antetul, formularele deschise și datele ecranului trec curat pe noul lucrător.
        $this->js('window.location.reload()');
    }

    private function label(): string
    {
        return $this->prefix === 'bar' ? 'bar' : 'recepție';
    }

    public function render()
    {
        return view('livewire.reception.switch-worker', [
            'workers' => $this->workers(),
            'selected' => $this->workerId ? $this->workers()->firstWhere('id', $this->workerId) : null,
        ]);
    }
}
