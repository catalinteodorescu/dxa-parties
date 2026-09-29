<?php

namespace App\Livewire\Concerns;

use App\Support\SubmitGuard;
use Closure;
use Illuminate\Support\Str;

/**
 * DXA: adaugat (protecție la retrimitere). Dă componentei o cheie de încercare (`attempt`) și `once()`, care rulează
 * înregistrarea o singură dată pe încercare. Cheia se reînnoiește după o înregistrare reușită.
 */
trait GuardsResubmit
{
    public string $attempt = '';

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    protected function once(Closure $callback): mixed
    {
        if ($this->attempt === '') {
            $this->attempt = (string) Str::uuid();
        }

        $result = SubmitGuard::run($this->attempt, $callback);
        $this->attempt = '';

        return $result;
    }
}
