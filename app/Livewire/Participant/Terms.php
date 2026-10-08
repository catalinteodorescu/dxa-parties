<?php

namespace App\Livewire\Participant;

use App\Services\Terms as TermsService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** DXA: adaugat (runda 60). Pagina „Termeni și condiții” (/termeni), publică; dacă ești în cont și nu ai acceptat, apare butonul de acceptare sub text. Fără text publicat → 404. */
#[Layout('layouts.participant', ['title' => 'Termeni și condiții'])]
class Terms extends Component
{
    public function accept(): void
    {
        $me = auth('participant')->user();
        if ($me) {
            TermsService::accept($me);
        }
    }

    public function render()
    {
        $version = TermsService::current();
        abort_unless($version, 404);

        $me = auth('participant')->user();

        return view('livewire.participant.terms', [
            'version' => $version,
            'html' => TermsService::render($version->body),
            'needsAcceptance' => $me && TermsService::needsAcceptance($me),
            'accepted' => $me && ! TermsService::needsAcceptance($me) && (int) $me->terms_version_id > 0,
        ]);
    }
}
