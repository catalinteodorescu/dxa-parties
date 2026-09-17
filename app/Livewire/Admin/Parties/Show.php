<?php

namespace App\Livewire\Admin\Parties;

use App\Models\Party;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class Show extends Component
{
    public Party $party;

    public function mount(Party $party): void
    {
        abort_unless(Auth::guard('admin')->check(), 403);

        $this->party = $party;
    }

    public function render()
    {
        return view('livewire.admin.parties.show');
    }
}
