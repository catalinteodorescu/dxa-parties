<?php

namespace App\Livewire\Admin\Users;

use App\Livewire\Admin\Users\Concerns\EditsPermissions;
use App\Models\Admin;
use App\Services\ActivityLogger;
use App\Support\Permissions as PermissionCatalog;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (runda 46 — permisiuni). Editarea permisiunilor unui utilizator existent. Doar superadmin-ul.
 * Superadmin-ul are implicit totul, deci pagina nu se deschide pentru un superadmin.
 */
#[Layout('layouts.admin')]
class Permissions extends Component
{
    use EditsPermissions;

    public Admin $target;

    public function mount(Admin $admin): void
    {
        abort_unless(Auth::guard('admin')->user()->isSuperAdmin(), 403);
        abort_if($admin->isSuperAdmin(), 404);

        $this->target = $admin;
        $this->fillPermissions($admin->permissions);
    }

    public function save(): void
    {
        abort_unless(Auth::guard('admin')->user()->isSuperAdmin(), 403);

        $old = $this->target->permissions;
        $new = $this->permissionsPayload();

        $this->target->update(['permissions' => $new]);

        $summary = PermissionCatalog::describeChange($old, $new);

        ActivityLogger::log(
            'admin.permissions.updated',
            'A actualizat permisiunile lui '.ActivityLogger::label($this->target).($summary !== '' ? ': '.$summary : ' (fără modificări)').'.',
            $this->target,
        );

        session()->flash('status', 'Permisiunile au fost salvate.');
        $this->redirectRoute('admin.users.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.users.permissions', [
            'copySources' => $this->copySources($this->target->id),
        ]);
    }
}
