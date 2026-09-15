<div
    x-data="{
        openDropdown: null,
        confirmOpen: false,
        confirmTitle: '',
        confirmMessage: '',
        confirmMethod: '',
        confirmArgs: [],
        toggleDropdown(key) {
            this.openDropdown = this.openDropdown === key ? null : key;
        },
        closeDropdowns() {
            this.openDropdown = null;
        },
        askConfirm(title, message, method, args) {
            this.confirmTitle = title;
            this.confirmMessage = message;
            this.confirmMethod = method;
            this.confirmArgs = args;
            this.confirmOpen = true;
            this.openDropdown = null;
        },
        runConfirm() {
            this.$wire.call(this.confirmMethod, ...this.confirmArgs);
            this.confirmOpen = false;
        },
    }"
    @click.outside="closeDropdowns()"
>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-ink">Administratori</h2>
            <p class="mt-1 text-sm text-ink-soft">Conturile care au acces la panoul de administrare.</p>
        </div>
        @if ($currentAdmin->isSuperAdmin())
            <a href="{{ route('admin.users.create') }}" wire:navigate
               class="rounded-lg bg-primary hover:bg-primary-hover text-white text-sm font-medium px-4 py-2.5 transition-colors">
                + Adaugă admin
            </a>
        @endif
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-primary-soft text-primary text-sm px-4 py-3">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 rounded-lg bg-danger/10 text-danger text-sm px-4 py-3">
            {{ session('error') }}
        </div>
    @endif

    <div class="bg-surface border border-border rounded-2xl overflow-visible">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-border text-left text-ink-soft bg-bg">
                    <th class="px-5 py-3 font-medium">Nume</th>
                    <th class="px-5 py-3 font-medium">Telefon</th>
                    @if ($currentAdmin->isSuperAdmin())
                        <th class="px-5 py-3 font-medium">Rol</th>
                    @endif
                    <th class="px-5 py-3 font-medium">Status</th>
                    <th class="px-5 py-3 font-medium">Creat la</th>
                    @if ($currentAdmin->isSuperAdmin())
                        <th class="px-5 py-3 font-medium"></th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($admins as $admin)
                    @php $isSelf = $admin->id === $currentAdmin->id; @endphp
                    <tr class="border-b border-border last:border-0">
                        <td class="px-5 py-3 text-ink">
                            {{ $admin->name ?: '—' }}
                            @if ($isSelf)
                                <span class="text-xs text-ink-soft/70">(tu)</span>
                            @endif
                            @unless ($admin->activated_at)
                                <span class="block text-xs text-ink-soft/70">invitație în așteptare</span>
                            @endunless
                        </td>
                        <td class="px-5 py-3 text-ink-soft">{{ $admin->phone }}</td>

                        {{-- Rol (doar superadmin vede coloana asta) --}}
                        @if ($currentAdmin->isSuperAdmin())
                            <td class="px-5 py-3">
                                @if (! $isSelf)
                                    <div class="relative inline-block" @click.stop>
                                        <button type="button" @click="toggleDropdown('role-{{ $admin->id }}')"
                                                class="inline-flex items-center gap-1 rounded-full text-xs font-medium px-2.5 py-1 transition-colors
                                                       {{ $admin->role === 'superadmin' ? 'bg-primary text-white' : 'bg-bg text-ink-soft' }}">
                                            {{ $admin->role === 'superadmin' ? 'Superadmin' : 'Admin' }}
                                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="6 9 12 15 18 9"/>
                                            </svg>
                                        </button>
                                        <div x-show="openDropdown === 'role-{{ $admin->id }}'" x-cloak
                                             class="absolute z-10 mt-1 w-40 rounded-lg border border-border bg-surface shadow-lg py-1">
                                            <button type="button"
                                                    @click="askConfirm('Schimbă rolul', 'Faci din {{ addslashes($admin->name ?: $admin->phone) }} admin obișnuit?', 'updateRole', [{{ $admin->id }}, 'admin'])"
                                                    class="w-full text-left px-3 py-1.5 text-sm text-ink hover:bg-bg">
                                                Admin
                                            </button>
                                            <button type="button"
                                                    @click="askConfirm('Schimbă rolul', 'Faci din {{ addslashes($admin->name ?: $admin->phone) }} superadmin?', 'updateRole', [{{ $admin->id }}, 'superadmin'])"
                                                    class="w-full text-left px-3 py-1.5 text-sm text-ink hover:bg-bg">
                                                Superadmin
                                            </button>
                                        </div>
                                    </div>
                                @else
                                    <span class="inline-flex items-center rounded-full text-xs font-medium px-2.5 py-1
                                                 {{ $admin->role === 'superadmin' ? 'bg-primary text-white' : 'bg-bg text-ink-soft' }}">
                                        {{ $admin->role === 'superadmin' ? 'Superadmin' : 'Admin' }}
                                    </span>
                                @endif
                            </td>
                        @endif

                        {{-- Status --}}
                        <td class="px-5 py-3">
                            @if ($currentAdmin->isSuperAdmin() && ! $isSelf)
                                <div class="relative inline-block" @click.stop>
                                    <button type="button" @click="toggleDropdown('status-{{ $admin->id }}')"
                                            class="inline-flex items-center gap-1 rounded-full text-xs font-medium px-2.5 py-1 transition-colors
                                                   {{ $admin->is_active ? 'bg-primary-soft text-primary' : 'bg-bg text-ink-soft' }}">
                                        {{ $admin->is_active ? 'Activ' : 'Inactiv' }}
                                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="6 9 12 15 18 9"/>
                                        </svg>
                                    </button>
                                    <div x-show="openDropdown === 'status-{{ $admin->id }}'" x-cloak
                                         class="absolute z-10 mt-1 w-36 rounded-lg border border-border bg-surface shadow-lg py-1">
                                        <button type="button"
                                                @click="askConfirm('Activează contul', 'Sigur vrei să activezi contul lui {{ addslashes($admin->name ?: $admin->phone) }}?', 'updateStatus', [{{ $admin->id }}, true])"
                                                class="w-full text-left px-3 py-1.5 text-sm text-ink hover:bg-bg">
                                            Activ
                                        </button>
                                        <button type="button"
                                                @click="askConfirm('Dezactivează contul', 'Sigur vrei să dezactivezi contul lui {{ addslashes($admin->name ?: $admin->phone) }}? Va fi delogat automat și nu se va mai putea autentifica sau reseta parola.', 'updateStatus', [{{ $admin->id }}, false])"
                                                class="w-full text-left px-3 py-1.5 text-sm text-ink hover:bg-bg">
                                            Inactiv
                                        </button>
                                    </div>
                                </div>
                            @else
                                <span class="inline-flex items-center rounded-full text-xs font-medium px-2.5 py-1
                                             {{ $admin->is_active ? 'bg-primary-soft text-primary' : 'bg-bg text-ink-soft' }}">
                                    {{ $admin->is_active ? 'Activ' : 'Inactiv' }}
                                </span>
                            @endif
                        </td>

                        <td class="px-5 py-3 text-ink-soft">{{ $admin->created_at->format('d.m.Y') }}</td>

                        @if ($currentAdmin->isSuperAdmin())
                            <td class="px-5 py-3 text-right">
                                @unless ($isSelf)
                                    <button type="button"
                                            @click="askConfirm('Șterge admin', 'Sigur vrei să ștergi contul lui {{ addslashes($admin->name ?: $admin->phone) }}? Acțiunea nu poate fi anulată.', 'deleteAdmin', [{{ $admin->id }}])"
                                            class="text-ink-soft hover:text-danger transition-colors" title="Șterge">
                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="3 6 5 6 21 6"/>
                                            <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                                            <path d="M10 11v6M14 11v6"/>
                                            <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                                        </svg>
                                    </button>
                                @endunless
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $currentAdmin->isSuperAdmin() ? 6 : 4 }}" class="px-5 py-8 text-center text-ink-soft">Niciun admin momentan.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $admins->links() }}
    </div>

    {{-- Modal de confirmare, folosit de orice actiune din pill-uri sau stergere --}}
    <div x-show="confirmOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-ink/40" @click="confirmOpen = false"></div>
        <div class="relative bg-surface rounded-2xl border border-border shadow-lg max-w-sm w-full p-6">
            <h3 class="text-base font-semibold text-ink" x-text="confirmTitle"></h3>
            <p class="mt-2 text-sm text-ink-soft" x-text="confirmMessage"></p>
            <div class="mt-6 flex items-center justify-end gap-3">
                <button type="button" @click="confirmOpen = false"
                        class="text-sm font-medium text-ink-soft hover:text-ink px-3 py-2">
                    Anulează
                </button>
                <button type="button" @click="runConfirm()"
                        class="rounded-lg bg-primary hover:bg-primary-hover text-white text-sm font-medium px-4 py-2 transition-colors">
                    Confirmă
                </button>
            </div>
        </div>
    </div>
</div>
