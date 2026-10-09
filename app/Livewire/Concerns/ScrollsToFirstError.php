<?php

namespace App\Livewire\Concerns;

use Illuminate\Validation\ValidationException;

/**
 * DXA: adaugat (runda 68). La salvare cu erori de validare, pagina derulează la primul câmp cu eroare (evenimentul `dxa-scroll-error`,
 * ascultat în layouts/admin.blade.php), în loc să rămână sus, unde utilizatorul nu vede ce n-a mers.
 */
trait ScrollsToFirstError
{
    /** Ca validate(), dar cere derularea la primul câmp invalid când validarea pică. */
    protected function validateOrScroll(): array
    {
        try {
            return $this->validate();
        } catch (ValidationException $e) {
            $this->dispatch('dxa-scroll-error');

            throw $e;
        }
    }
}
