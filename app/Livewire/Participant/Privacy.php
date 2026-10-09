<?php

namespace App\Livewire\Participant;

use App\Services\PrivacyPolicy;
use App\Services\Terms;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** DXA: adaugat (runda 68). Pagina „Politica de confidențialitate” (/confidentialitate), publică, fără acceptare. Fără text publicat → 404. */
#[Layout('layouts.participant', ['title' => 'Politica de confidențialitate'])]
class Privacy extends Component
{
    public function render()
    {
        $version = PrivacyPolicy::current();
        abort_unless($version, 404);

        return view('livewire.participant.privacy', ['version' => $version, 'html' => Terms::render($version->body)]);
    }
}
