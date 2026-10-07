{{-- DXA: adaugat (runda 46 — permisiuni). Matricea de permisiuni (creare + editare utilizator). Folosește $levels, $actions,
     $copySources din componentă (trait EditsPermissions). --}}
@php
    $short = [0 => 'Fără', 1 => 'Vezi', 2 => 'Modifică', 3 => 'Șterge'];
    $byGroup = collect(\App\Support\Permissions::sections())->groupBy('group', preserveKeys: true);
@endphp

<div class="space-y-4" data-permissions>

    <div>
        <h3 class="text-sm font-semibold text-ink">Permisiuni în panoul admin</h3>
        <p class="mt-1 text-xs text-ink-soft leading-relaxed">
            Pentru fiecare secțiune alege ce poate face. Nivelurile se includ unul pe altul (Modifică îl include pe Vezi,
            Șterge le include pe Vezi și Modifică). Acțiunile sensibile se pornesc separat, sub secțiunea din care fac parte.
        </p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <button type="button" wire:click="setAll('full')" class="rounded-lg border border-border bg-surface hover:bg-bg text-xs font-medium text-ink px-3 py-1.5">Acces complet</button>
        <button type="button" wire:click="setAll('view')" class="rounded-lg border border-border bg-surface hover:bg-bg text-xs font-medium text-ink px-3 py-1.5">Doar vizualizare</button>
        <button type="button" wire:click="setAll('none')" class="rounded-lg border border-border bg-surface hover:bg-bg text-xs font-medium text-ink px-3 py-1.5">Fără acces</button>
    </div>

    @if ($copySources->isNotEmpty())
        <div class="rounded-xl border border-border bg-bg p-3" data-copy-permissions>
            <span class="block text-xs font-medium text-ink-soft">Copiază permisiunile de la alt utilizator</span>
            <div class="mt-1.5 flex flex-col gap-2 sm:flex-row">
                <x-select wire:model="copyFrom" class="min-w-0 flex-1" placeholder="Alege un utilizator…"
                          :options="$copySources->mapWithKeys(fn ($u) => [$u->id => $u->name ? $u->name.' · '.$u->phone : $u->phone])->all()" />
                <button type="button" wire:click="copyPermissions"
                        class="rounded-lg border border-border bg-surface hover:bg-surface-hover text-sm font-medium text-ink px-4 py-2 transition-colors">
                    Copiază
                </button>
            </div>
            @error('copyFrom') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
            <p class="mt-1.5 text-xs text-ink-soft">Valorile se completează mai jos și le poți ajusta înainte de salvare.</p>
        </div>
    @endif

    @foreach (\App\Support\Permissions::GROUPS as $groupKey => $groupLabel)
        @continue (! isset($byGroup[$groupKey]))
        <div class="rounded-xl border border-border bg-surface">
            <div class="px-4 py-2.5 border-b border-border text-xs font-semibold uppercase tracking-wide text-ink-soft/70">{{ $groupLabel }}</div>
            <div class="divide-y divide-border">
                @foreach ($byGroup[$groupKey] as $key => $section)
                    @php
                        $current = (int) ($levels[$key] ?? 0);
                        $sectionActions = \App\Support\Permissions::actionsOf($key);
                    @endphp
                    <div class="px-4 py-3" wire:key="perm-{{ $key }}">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <span class="text-sm font-medium text-ink">{{ $section['label'] }}</span>
                            <div class="inline-flex self-start rounded-lg border border-border overflow-hidden sm:self-auto" role="group">
                                @foreach (range(0, $section['max']) as $lv)
                                    @php $on = $lv === 0 ? $current === 0 : $current >= $lv; @endphp
                                    <button type="button" wire:click="setLevel('{{ $key }}', {{ $lv }})"
                                            data-perm="{{ $key }}" data-level="{{ $lv }}" @if ($on) data-active @endif
                                            class="px-3 py-1.5 text-xs font-medium transition-colors {{ $lv > 0 ? 'border-l border-border' : '' }}
                                                   {{ $on ? ($lv === 0 ? 'bg-ink-soft text-white' : 'bg-primary text-white') : 'bg-surface text-ink-soft hover:bg-bg' }}">
                                        {{ $short[$lv] }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        @if ($sectionActions)
                            <div class="mt-3 pl-3 border-l border-border space-y-2.5" data-section-actions="{{ $key }}">
                                @foreach ($sectionActions as $actionKey => [$actionLabel, $actionHint])
                                    @php $isOn = (bool) ($actions[$actionKey] ?? false); @endphp
                                    <div class="flex items-center justify-between gap-3" wire:key="act-{{ $actionKey }}">
                                        <div class="min-w-0">
                                            <div class="text-sm text-ink">{{ $actionLabel }}</div>
                                            <div class="text-xs text-ink-soft">{{ $actionHint }}</div>
                                        </div>
                                        <button type="button" wire:click="toggleAction('{{ $actionKey }}')" role="switch" aria-checked="{{ $isOn ? 'true' : 'false' }}"
                                                aria-label="{{ $actionLabel }}" data-action="{{ $actionKey }}" @if ($isOn) data-on @endif
                                                class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors {{ $isOn ? 'bg-primary' : 'bg-border' }}">
                                            <span class="inline-block h-[18px] w-[18px] transform rounded-full bg-white shadow transition-transform {{ $isOn ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
