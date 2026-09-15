<?php

namespace App\Livewire\Admin\Users;

use App\Models\Admin;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
class Index extends Component
{
    use WithPagination;

    public function updateStatus(int $adminId, bool $active): void
    {
        $current = Auth::guard('admin')->user();

        abort_unless($current->isSuperAdmin(), 403);

        if ($adminId === $current->id) {
            session()->flash('error', 'Nu îți poți schimba propriul status de aici.');

            return;
        }

        $target = Admin::findOrFail($adminId);
        $target->update(['is_active' => $active]);

        ActivityLogger::log(
            'admin.status.updated',
            ($active ? 'A activat' : 'A dezactivat').' contul lui '.ActivityLogger::label($target).'.',
            $target,
        );

        session()->flash('status', 'Statusul a fost actualizat.');
    }

    public function updateRole(int $adminId, string $role): void
    {
        $current = Auth::guard('admin')->user();

        abort_unless($current->isSuperAdmin(), 403);
        abort_unless(in_array($role, ['admin', 'superadmin'], true), 422);

        if ($adminId === $current->id) {
            session()->flash('error', 'Nu îți poți schimba propriul rol de aici.');

            return;
        }

        $target = Admin::findOrFail($adminId);
        $target->update(['role' => $role]);

        ActivityLogger::log(
            'admin.role.updated',
            'A schimbat rolul lui '.ActivityLogger::label($target).' în '.($role === 'superadmin' ? 'superadmin' : 'admin').'.',
            $target,
        );

        session()->flash('status', 'Rolul a fost actualizat.');
    }

    public function deleteAdmin(int $adminId): void
    {
        $current = Auth::guard('admin')->user();

        abort_unless($current->isSuperAdmin(), 403);

        if ($adminId === $current->id) {
            session()->flash('error', 'Nu îți poți șterge propriul cont de aici.');

            return;
        }

        $target = Admin::findOrFail($adminId);
        $target->delete();

        ActivityLogger::log('admin.deleted', 'A șters contul lui '.ActivityLogger::label($target).'.', $target);

        session()->flash('status', 'Adminul a fost șters.');
    }

    public function render()
    {
        return view('livewire.admin.users.index', [
            'admins' => Admin::orderBy('name')->paginate(15),
            'currentAdmin' => Auth::guard('admin')->user(),
        ]);
    }
}
