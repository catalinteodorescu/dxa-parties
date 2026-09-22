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
    @click="closeDropdowns()"
    @click.outside="closeDropdowns()"
>
    @php
        $isSuper = $currentAdmin->isSuperAdmin();
        // Sablon de coloane pentru desktop; pe mobil totul devine card stivuit.
        $cols = $isSuper
            ? 'md:grid-cols-[minmax(0,1.6fr)_minmax(0,1.2fr)_minmax(0,0.9fr)_minmax(0,0.9fr)_minmax(0,0.8fr)_44px]'
            : 'md:grid-cols-[minmax(0,1.8fr)_minmax(0,1.2fr)_minmax(0,0.9fr)_minmax(0,0.8fr)]';
    @endphp

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-ink">Administratori</h2>
            <p class="mt-1 text-sm text-ink-soft">Conturile care au acces la panoul de administrare.</p>
        </div>
        @if ($isSuper)
            <x-btn variant="primary" :href="route('admin.users.create')" wire:navigate class="self-start">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Adaugă admin
            </x-btn>
        @endif
    </div>

    <x-flash class="mb-4" />

    {{-- Lista: antet de coloane slim (doar desktop) + fiecare admin = card propriu, spațiat --}}
    <div>

        <div class="hidden md:grid {{ $cols }} gap-4 px-5 py-2
                    text-xs font-semibold uppercase tracking-wide text-ink-soft/70">
            <div>Nume</div>
            <div>Telefon</div>
            @if ($isSuper)<div>Rol</div>@endif
            <div>Status</div>
            <div>Creat la</div>
            @if ($isSuper)<div class="text-right">Acțiuni</div>@endif
        </div>

        <div class="space-y-3">
            @forelse ($admins as $admin)
                @php $isSelf = $admin->id === $currentAdmin->id; @endphp
                <div class="rounded-2xl border border-border bg-surface p-4 space-y-3
                            md:px-5 md:py-3 md:space-y-0 md:grid {{ $cols }} md:items-center md:gap-4">

                    {{-- Nume --}}
                    <div class="flex items-start justify-between gap-3 md:block">
                        <span class="text-xs font-medium text-ink-soft md:hidden">Nume</span>
                        <div class="text-right md:text-left">
                            <span class="text-ink font-medium md:font-normal">{{ $admin->name ?: '—' }}</span>
                            @if ($isSelf)
                                <span class="text-xs text-ink-soft/70">(tu)</span>
                            @endif
                            @unless ($admin->activated_at)
                                <span class="block text-xs text-ink-soft/70">invitație în așteptare</span>
                            @endunless
                        </div>
                    </div>

                    {{-- Telefon --}}
                    <div class="flex items-center justify-between gap-3 md:block">
                        <span class="text-xs font-medium text-ink-soft md:hidden">Telefon</span>
                        <span class="text-ink-soft">{{ $admin->phone }}</span>
                    </div>

                    {{-- Rol (doar superadmin vede si editeaza) --}}
                    @if ($isSuper)
                        <div class="flex items-center justify-between gap-3 md:block">
                            <span class="text-xs font-medium text-ink-soft md:hidden">Rol</span>
                            <div>
                                @if (! $isSelf)
                                    <div class="relative inline-block" @click.stop>
                                        <button type="button" @click="toggleDropdown('role-{{ $admin->id }}')"
                                                class="inline-flex items-center gap-1 rounded-full text-xs font-medium px-2.5 py-1 transition-colors
                                                       {{ $admin->role === 'superadmin' ? 'bg-primary text-white' : 'bg-surface border border-border text-ink-soft' }}">
                                            {{ $admin->role === 'superadmin' ? 'Superadmin' : 'Admin' }}
                                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="6 9 12 15 18 9"/>
                                            </svg>
                                        </button>
                                        <div x-show="openDropdown === 'role-{{ $admin->id }}'" x-cloak
                                             class="absolute z-10 mt-1 w-40 right-0 md:right-auto md:left-0 rounded-lg border border-border bg-surface shadow-lg py-1">
                                            @foreach (['admin' => 'Admin', 'superadmin' => 'Superadmin'] as $roleVal => $roleLabel)
                                                @if ($admin->role === $roleVal)
                                                    <div class="flex items-center justify-between gap-2 px-3 py-1.5 text-sm text-ink-soft/50 cursor-default select-none">
                                                        <span>{{ $roleLabel }}</span>
                                                        <svg class="w-3.5 h-3.5 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                            <polyline points="20 6 9 17 4 12"/>
                                                        </svg>
                                                    </div>
                                                @else
                                                    <button type="button"
                                                            @click="askConfirm('Schimbă rolul', 'Faci din {{ addslashes($admin->name ?: $admin->phone) }} {{ $roleVal === 'superadmin' ? 'superadmin' : 'admin obișnuit' }}?', 'updateRole', [{{ $admin->id }}, '{{ $roleVal }}'])"
                                                            class="w-full text-left px-3 py-1.5 text-sm text-ink hover:bg-bg">
                                                        {{ $roleLabel }}
                                                    </button>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                @else
                                    <span class="inline-flex items-center rounded-full text-xs font-medium px-2.5 py-1
                                                 {{ $admin->role === 'superadmin' ? 'bg-primary text-white' : 'bg-surface border border-border text-ink-soft' }}">
                                        {{ $admin->role === 'superadmin' ? 'Superadmin' : 'Admin' }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- Status --}}
                    <div class="flex items-center justify-between gap-3 md:block">
                        <span class="text-xs font-medium text-ink-soft md:hidden">Status</span>
                        <div>
                            @if ($isSuper && ! $isSelf)
                                <div class="relative inline-block" @click.stop>
                                    <button type="button" @click="toggleDropdown('status-{{ $admin->id }}')"
                                            class="inline-flex items-center gap-1 rounded-full text-xs font-medium px-2.5 py-1 transition-colors
                                                   {{ $admin->is_active ? 'bg-primary-soft text-primary' : 'bg-surface border border-border text-ink-soft' }}">
                                        {{ $admin->is_active ? 'Activ' : 'Inactiv' }}
                                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="6 9 12 15 18 9"/>
                                        </svg>
                                    </button>
                                    <div x-show="openDropdown === 'status-{{ $admin->id }}'" x-cloak
                                         class="absolute z-10 mt-1 w-56 right-0 md:right-auto md:left-0 rounded-lg border border-border bg-surface shadow-lg py-1">
                                        {{-- Activ --}}
                                        @if ($admin->is_active)
                                            <div class="flex items-center justify-between gap-2 px-3 py-1.5 text-sm text-ink-soft/50 cursor-default select-none">
                                                <span>Activ</span>
                                                <svg class="w-3.5 h-3.5 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                    <polyline points="20 6 9 17 4 12"/>
                                                </svg>
                                            </div>
                                        @else
                                            <button type="button"
                                                    @click="askConfirm('Activează contul', 'Sigur vrei să activezi contul lui {{ addslashes($admin->name ?: $admin->phone) }}?', 'updateStatus', [{{ $admin->id }}, true])"
                                                    class="w-full text-left px-3 py-1.5 text-sm text-ink hover:bg-bg">
                                                Activ
                                            </button>
                                        @endif
                                        {{-- Inactiv --}}
                                        @if (! $admin->is_active)
                                            <div class="flex items-center justify-between gap-2 px-3 py-1.5 text-sm text-ink-soft/50 cursor-default select-none">
                                                <span>Inactiv</span>
                                                <svg class="w-3.5 h-3.5 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                    <polyline points="20 6 9 17 4 12"/>
                                                </svg>
                                            </div>
                                        @else
                                            <button type="button"
                                                    @click="askConfirm('Dezactivează contul', 'Sigur vrei să dezactivezi contul lui {{ addslashes($admin->name ?: $admin->phone) }}? Va fi delogat automat și nu se va mai putea autentifica sau reseta parola.', 'updateStatus', [{{ $admin->id }}, false])"
                                                    class="w-full text-left px-3 py-1.5 text-sm text-ink hover:bg-bg">
                                                Inactiv
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @else
                                <span class="inline-flex items-center rounded-full text-xs font-medium px-2.5 py-1
                                             {{ $admin->is_active ? 'bg-primary-soft text-primary' : 'bg-surface border border-border text-ink-soft' }}">
                                    {{ $admin->is_active ? 'Activ' : 'Inactiv' }}
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Creat la --}}
                    <div class="flex items-center justify-between gap-3 md:block">
                        <span class="text-xs font-medium text-ink-soft md:hidden">Creat la</span>
                        <span class="text-ink-soft">{{ $admin->created_at->format('d.m.Y') }}</span>
                    </div>

                    {{-- Acțiuni (doar superadmin) --}}
                    @if ($isSuper)
                        <div class="{{ $isSelf ? 'hidden md:block' : 'flex items-center justify-between gap-3 md:block' }} md:text-right">
                            <span class="text-xs font-medium text-ink-soft md:hidden">Acțiuni</span>
                            @unless ($isSelf)
                                <x-btn variant="danger" size="icon" outline tooltip="Șterge"
                                       x-on:click="askConfirm('Șterge admin', 'Sigur vrei să ștergi contul lui {{ addslashes($admin->name ?: $admin->phone) }}? Acțiunea nu poate fi anulată.', 'deleteAdmin', [{{ $admin->id }}])">
                                    <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="3 6 5 6 21 6"/>
                                        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                                        <path d="M10 11v6M14 11v6"/>
                                        <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                                    </svg>
                                </x-btn>
                            @endunless
                        </div>
                    @endif
                </div>
            @empty
                <div class="rounded-2xl border border-border bg-surface px-5 py-8 text-center text-ink-soft">
                    Niciun admin momentan.
                </div>
            @endforelse
        </div>
    </div>

    <div class="mt-4">
        {{ $admins->onEachSide(1)->links('pagination.dxa') }}
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
