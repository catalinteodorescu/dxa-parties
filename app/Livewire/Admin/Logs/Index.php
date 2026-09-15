<?php

namespace App\Livewire\Admin\Logs;

use App\Models\AdminActivityLog;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
class Index extends Component
{
    use WithPagination;

    public function mount(): void
    {
        abort_unless(Auth::guard('admin')->user()->isSuperAdmin(), 403);
    }

    public function render()
    {
        return view('livewire.admin.logs.index', [
            'logs' => AdminActivityLog::latest('created_at')->paginate(20),
        ]);
    }
}
