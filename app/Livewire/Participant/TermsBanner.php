<?php

namespace App\Livewire\Participant;

use App\Services\Terms;
use Livewire\Component;

/** DXA: adaugat (runda 60). Banner ne-blocant în aplicație: apare când contul n-a acceptat ultima versiune care cere acceptare. */
class TermsBanner extends Component
{
    public function accept(): void
    {
        $me = auth('participant')->user();
        if ($me) {
            Terms::accept($me);
        }
    }

    public function render()
    {
        $me = auth('participant')->user();

        return view('livewire.participant.terms-banner', ['show' => $me && Terms::needsAcceptance($me)]);
    }
}
