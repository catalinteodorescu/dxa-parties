<?php

namespace App\Livewire\Admin\Legal;

use Livewire\Attributes\Layout;
use Livewire\Component;

/** DXA: adaugat (runda 68). Pagina „Legal” din admin: Termeni și condiții (mutat din Setări) și Politica de confidențialitate. Permisiune proprie: secțiunea „Legal”. */
#[Layout('layouts.admin')]
class Index extends Component
{
    public function render()
    {
        return view('livewire.admin.legal.index');
    }
}
