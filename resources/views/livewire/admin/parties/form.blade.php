<div class="max-w-2xl">
    @php
        $isEditing = $party && $party->exists;
        $in = 'w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary';
        $sel = 'appearance-none w-full rounded-lg border border-border bg-white px-3.5 py-2.5 pr-9 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary cursor-pointer';
        $lbl = 'block text-sm font-medium text-ink';
        $card = 'bg-surface border border-border rounded-2xl p-5 sm:p-6 space-y-5';
        $err = 'error-msg mt-1.5 text-sm text-danger';
        $paymentMethods = \App\Models\Party::PAYMENT_METHODS;
        $guestStyles = \App\Models\Party::GUEST_STYLES;
        $programTypes = \App\Models\Party::PROGRAM_TYPES;
        $guestNames = collect($guests)->pluck('name')->filter()->values();
    @endphp

    <div class="mb-6">
        <h2 class="text-lg font-semibold text-ink">{{ $isEditing ? 'Editează petrecerea' : 'Petrecere nouă' }}</h2>
        <p class="mt-1 text-sm text-ink-soft leading-relaxed">Petrecere simplă (o seară) sau festival pe mai multe zile.</p>
    </div>

    <x-flash class="mb-4" />

    <form wire:submit="save" class="space-y-4"
          x-data
          x-on:scroll-to-error.window="$nextTick(() => { const e = $root.querySelector('.error-msg'); if (e) { e.scrollIntoView({ behavior: 'smooth', block: 'center' }); const f = e.closest('div')?.querySelector('input, select, textarea'); if (f) f.focus({ preventScroll: true }); } })">

        {{-- ============ Identitate + tip ============ --}}
        <div class="{{ $card }}">
            <div>
                <label class="{{ $lbl }} mb-1.5">Tip petrecere</label>
                <div class="inline-flex rounded-lg border border-border p-1 bg-bg">
                    <button type="button" wire:click="$set('kind','basic')"
                            @class(['px-4 py-1.5 rounded-md text-sm font-medium transition-colors', 'bg-surface text-primary shadow-sm' => $kind === 'basic', 'text-ink-soft' => $kind !== 'basic'])>Simplă</button>
                    <button type="button" wire:click="$set('kind','festival')"
                            @class(['px-4 py-1.5 rounded-md text-sm font-medium transition-colors', 'bg-surface text-primary shadow-sm' => $kind === 'festival', 'text-ink-soft' => $kind !== 'festival'])>Festival</button>
                </div>
            </div>

            <div>
                <label for="name" class="{{ $lbl }}">Denumire</label>
                <input type="text" id="name" wire:model="name" autofocus class="mt-1.5 {{ $in }}">
                @error('name') <p class="{{ $err }}">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="{{ $lbl }}">Imagine <span class="text-ink-soft/60 font-normal">(opțional)</span></label>
                <div class="mt-1.5 flex items-start gap-4">
                    <div class="shrink-0">
                        @if ($image)
                            <img src="{{ $image->temporaryUrl() }}" alt="" class="w-28 h-28 object-cover rounded-xl border border-border">
                        @elseif ($existingImage && ! $removeImage)
                            <img src="{{ asset('storage/'.$existingImage) }}" alt="" class="w-28 h-28 object-cover rounded-xl border border-border">
                        @else
                            <div class="w-28 h-28 rounded-xl border border-dashed border-border bg-bg flex items-center justify-center text-ink-soft/40">
                                <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
                            </div>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <input type="file" id="image" wire:model="image" accept="image/*"
                               class="block w-full text-sm text-ink-soft file:mr-3 file:rounded-lg file:border-0 file:bg-primary file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-primary-hover file:cursor-pointer">
                        <div wire:loading wire:target="image" class="mt-2 text-xs text-ink-soft">Se încarcă imaginea…</div>
                        @if (($existingImage && ! $removeImage) || $image)
                            <button type="button" wire:click="clearImage" class="mt-2 text-xs font-medium text-danger hover:underline">Elimină imaginea</button>
                        @endif
                        @error('image') <p class="{{ $err }}">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- ============ Când (SIMPLĂ) ============ --}}
        <div class="{{ $card }}" x-show="$wire.kind === 'basic'" x-cloak>
            <h3 class="text-sm font-semibold text-ink">Când</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="start_date" class="{{ $lbl }}">Data</label>
                    <input type="date" id="start_date" wire:model="start_date" class="accent-primary [color-scheme:light] mt-1.5 {{ $in }}">
                    @error('start_date') <p class="{{ $err }}">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="start_time" class="{{ $lbl }}">Ora început</label>
                    <input type="time" step="900" id="start_time" wire:model="start_time" class="mt-1.5 {{ $in }}">
                    @error('start_time') <p class="{{ $err }}">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="end_time" class="{{ $lbl }}">Ora sfârșit</label>
                    <input type="time" step="900" id="end_time" wire:model="end_time" class="mt-1.5 {{ $in }}">
                    @error('end_time') <p class="{{ $err }}">{{ $message }}</p> @enderror
                </div>
            </div>
            <p class="text-xs text-ink-soft/80">Dacă ora de sfârșit e mai mică decât cea de început (ex. 21:00 → 03:00), se consideră automat a doua zi.</p>

            <div>
                <label for="dresscode" class="{{ $lbl }}">Dresscode <span class="text-ink-soft/60 font-normal">(opțional)</span></label>
                <input type="text" id="dresscode" wire:model="dresscode" placeholder="ex. Elegant / All white / Casual" class="mt-1.5 {{ $in }}">
                @error('dresscode') <p class="{{ $err }}">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- ============ Invitați (FESTIVAL) ============ --}}
        <div class="{{ $card }}" x-show="$wire.kind === 'festival'" x-cloak>
            <h3 class="text-sm font-semibold text-ink">Invitați</h3>

            <div class="space-y-3">
                @foreach ($guests as $i => $g)
                    <div wire:key="guest-{{ $i }}" class="rounded-xl border border-border p-3">
                        <div class="flex items-start gap-3">
                            <div class="shrink-0">
                                @if (isset($guestPhotos[$i]) && $guestPhotos[$i])
                                    <img src="{{ $guestPhotos[$i]->temporaryUrl() }}" class="w-16 h-16 object-cover rounded-lg border border-border">
                                @elseif (! empty($g['photo_path']))
                                    <img src="{{ asset('storage/'.$g['photo_path']) }}" class="w-16 h-16 object-cover rounded-lg border border-border">
                                @else
                                    <div class="w-16 h-16 rounded-lg border border-dashed border-border bg-bg flex items-center justify-center text-ink-soft/40">
                                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c0-3.5 3-6 7-6s7 2.5 7 6"/></svg>
                                    </div>
                                @endif
                            </div>

                            <div class="min-w-0 flex-1 space-y-2">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    <input type="text" wire:model="guests.{{ $i }}.name" placeholder="Nume" class="{{ $in }}">
                                    <input type="text" wire:model="guests.{{ $i }}.country" placeholder="Țară (ex. RO, ES, CU)" class="{{ $in }}">
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    <x-dropdown-select path="guests.{{ $i }}.style" :options="$guestStyles" :selected="$g['style'] ?? ''" placeholder="Stil" />
                                    @if (($g['style'] ?? '') === 'other')
                                        <input type="text" wire:model="guests.{{ $i }}.style_other" placeholder="Care stil?" class="{{ $in }}">
                                    @endif
                                </div>
                                <input type="url" wire:model="guests.{{ $i }}.url" placeholder="Link (opțional): Instagram, site…" class="{{ $in }}">
                                @error('guests.'.$i.'.url') <p class="{{ $err }}">{{ $message }}</p> @enderror

                                <div class="flex items-center gap-3 pt-0.5">
                                    <label class="text-xs font-medium text-primary hover:underline cursor-pointer">
                                        <input type="file" wire:model="guestPhotos.{{ $i }}" accept="image/*" class="hidden">
                                        {{ (! empty($g['photo_path']) || isset($guestPhotos[$i])) ? 'Schimbă poza' : 'Adaugă poză' }}
                                    </label>
                                    @if (! empty($g['photo_path']) || isset($guestPhotos[$i]))
                                        <button type="button" wire:click="clearGuestPhoto({{ $i }})" class="text-xs font-medium text-danger hover:underline">Elimină poza</button>
                                    @endif
                                    <div wire:loading wire:target="guestPhotos.{{ $i }}" class="text-xs text-ink-soft">Se încarcă…</div>
                                    <button type="button" wire:click="removeGuest({{ $i }})" class="ml-auto text-xs font-medium text-ink-soft hover:text-danger">Șterge invitatul</button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <button type="button" wire:click="addGuest" class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Adaugă invitat
            </button>
        </div>

        {{-- ============ Program pe zile (FESTIVAL) ============ --}}
        <div class="{{ $card }}" x-show="$wire.kind === 'festival'" x-cloak>
            <h3 class="text-sm font-semibold text-ink">Program pe zile</h3>

            @if ($guestNames->isNotEmpty())
                <datalist id="dxa-guest-names">
                    @foreach ($guestNames as $gn)
                        <option value="{{ $gn }}"></option>
                    @endforeach
                </datalist>
            @endif

            <div class="space-y-4">
                @foreach ($days as $di => $day)
                    <div wire:key="day-{{ $di }}" class="rounded-xl border border-border p-3 sm:p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold text-ink">Ziua {{ $di + 1 }}</span>
                            <button type="button" wire:click="removeDay({{ $di }})" class="text-xs font-medium text-ink-soft hover:text-danger">Șterge ziua</button>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-2">
                            <input type="date" wire:model="days.{{ $di }}.date" class="accent-primary [color-scheme:light] {{ $in }}">
                            <input type="time" step="900" wire:model="days.{{ $di }}.start_time" class="{{ $in }}" title="Ora început">
                            <input type="time" step="900" wire:model="days.{{ $di }}.end_time" class="{{ $in }}" title="Ora sfârșit">
                            <input type="text" wire:model="days.{{ $di }}.dresscode" placeholder="Dresscode" class="{{ $in }}">
                        </div>
                        @error('days.'.$di.'.date') <p class="{{ $err }}">{{ $message }}</p> @enderror

                        <div class="space-y-2">
                            <span class="text-xs font-medium text-ink-soft">Program</span>
                            @foreach ($day['program'] ?? [] as $pi => $item)
                                <div wire:key="day-{{ $di }}-item-{{ $pi }}" class="rounded-lg border border-border bg-bg/40 p-2.5 space-y-2">
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                        <input type="time" step="900" wire:model="days.{{ $di }}.program.{{ $pi }}.start" class="{{ $in }}" title="De la">
                                        <input type="time" step="900" wire:model="days.{{ $di }}.program.{{ $pi }}.end" class="{{ $in }}" title="Până la">
                                        <x-dropdown-select path="days.{{ $di }}.program.{{ $pi }}.type" :options="$programTypes" :selected="$item['type'] ?? ''" placeholder="Tip" />
                                    </div>
                                    <input type="text" wire:model="days.{{ $di }}.program.{{ $pi }}.title" placeholder="Titlu (ex. Workshop Bachata Sensual)" class="{{ $in }}">
                                    <div class="grid grid-cols-1 sm:grid-cols-[1fr_1fr_auto] gap-2 items-start">
                                        <input type="text" list="dxa-guest-names" wire:model="days.{{ $di }}.program.{{ $pi }}.guest" placeholder="Invitat (opțional)" class="{{ $in }}">
                                        <input type="text" wire:model="days.{{ $di }}.program.{{ $pi }}.room" placeholder="Sală (opțional)" class="{{ $in }}">
                                        <button type="button" wire:click="removeProgramItem({{ $di }}, {{ $pi }})" class="h-[42px] w-10 inline-flex items-center justify-center rounded-lg border border-border text-ink-soft hover:border-danger hover:text-danger shrink-0" title="Elimină">
                                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                            <button type="button" wire:click="addProgramItem({{ $di }})" class="inline-flex items-center gap-1.5 text-xs font-medium text-primary hover:underline">
                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                Adaugă în program
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            <button type="button" wire:click="addDay" class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Adaugă zi
            </button>
            @error('days') <p class="{{ $err }}">{{ $message }}</p> @enderror
        </div>

        {{-- ============ Locație ============ --}}
        <div class="{{ $card }}">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-ink">Locație</h3>
                <button type="button" wire:click="fillDxaVenue" class="text-xs font-medium text-primary hover:underline">Completează cu sala DXA</button>
            </div>
            <div>
                <label for="location_name" class="{{ $lbl }}">Nume locație</label>
                <input type="text" id="location_name" wire:model="location_name" placeholder="Dance Xplosion Academy" class="mt-1.5 {{ $in }}">
                @error('location_name') <p class="{{ $err }}">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="location_address" class="{{ $lbl }}">Adresă <span class="text-ink-soft/60 font-normal">(opțional)</span></label>
                <input type="text" id="location_address" wire:model="location_address" class="mt-1.5 {{ $in }}">
                @error('location_address') <p class="{{ $err }}">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="location_url" class="{{ $lbl }}">Link hartă <span class="text-ink-soft/60 font-normal">(opțional)</span></label>
                <input type="url" id="location_url" wire:model="location_url" placeholder="https://maps.google.com/…" class="mt-1.5 {{ $in }}">
                @error('location_url') <p class="{{ $err }}">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- ============ Stiluri muzică ============ --}}
        <div class="{{ $card }}">
            <h3 class="text-sm font-semibold text-ink">Stiluri muzică</h3>
            <p class="text-xs text-ink-soft/80">Ciclul de redare al DJ-ului, în ordine: câte melodii din fiecare stil, apoi se reia de la primul.</p>

            <div class="space-y-2">
                @foreach ($music_styles as $i => $ms)
                    <div wire:key="ms-{{ $i }}" class="grid grid-cols-[1fr_7rem_auto] gap-2 items-start">
                        <input type="text" wire:model="music_styles.{{ $i }}.style" placeholder="Stil (ex. Bachata)" class="{{ $in }}">
                        <input type="number" step="1" min="1" wire:model="music_styles.{{ $i }}.frequency" placeholder="Nr. melodii" class="{{ $in }}">
                        <button type="button" wire:click="removeMusicStyle({{ $i }})" class="h-[42px] w-10 inline-flex items-center justify-center rounded-lg border border-border text-ink-soft hover:border-danger hover:text-danger shrink-0" title="Elimină">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        </button>
                    </div>
                    @error('music_styles.'.$i.'.frequency') <p class="{{ $err }}">{{ $message }}</p> @enderror
                @endforeach
            </div>

            <button type="button" wire:click="addMusicStyle" class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Adaugă stil
            </button>

            @if (count(array_filter($music_styles, fn ($ms) => trim($ms['style'] ?? '') !== '')) > 1)
                <p class="text-xs text-ink-soft/70">
                    Ciclu: {{ implode(' → ', array_map(fn ($ms) => trim(($ms['style'] ?? '') !== '' ? ($ms['frequency'] ?: '?').'× '.$ms['style'] : ''), array_filter($music_styles, fn ($ms) => trim($ms['style'] ?? '') !== ''))) }} → se reia
                </p>
            @endif
        </div>

        {{-- ============ Preț (tipuri de bilet) ============ --}}
        <div class="{{ $card }}">
            <h3 class="text-sm font-semibold text-ink">Preț</h3>
            <label class="flex items-center gap-2.5 text-sm text-ink">
                <input type="checkbox" wire:model.live="is_free" class="w-4 h-4 rounded border-border accent-primary" style="accent-color: var(--color-primary);">
                Intrare gratuită
            </label>

            <div x-show="!$wire.is_free" x-cloak class="space-y-3">
                <p class="text-xs text-ink-soft/80">Poți avea mai multe tipuri de bilet (ex. Full pass, Party pass, Masterclass), fiecare cu propriile reduceri.</p>

                @foreach ($ticket_types as $ti => $type)
                    <div wire:key="tt-{{ $ti }}" class="rounded-xl border border-border p-3 space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-[1fr_9rem_auto] gap-2 items-start">
                            <input type="text" wire:model="ticket_types.{{ $ti }}.name" placeholder="Nume bilet (ex. Full pass)" class="{{ $in }}">
                            <input type="number" step="0.01" min="0" wire:model="ticket_types.{{ $ti }}.price" placeholder="Preț (lei)" class="{{ $in }}">
                            <button type="button" wire:click="removeTicketType({{ $ti }})" class="h-[42px] w-10 inline-flex items-center justify-center rounded-lg border border-border text-ink-soft hover:border-danger hover:text-danger shrink-0" title="Șterge biletul">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                            </button>
                        </div>
                        @error('ticket_types.'.$ti.'.price') <p class="{{ $err }}">{{ $message }}</p> @enderror

                        {{-- Reduceri pentru acest bilet --}}
                        <div class="pl-1">
                            <span class="text-xs font-medium text-ink-soft">Reduceri (early-bird)</span>
                            <div class="mt-2 space-y-2">
                                @foreach ($type['discounts'] ?? [] as $dii => $disc)
                                    <div wire:key="tt-{{ $ti }}-d-{{ $dii }}" class="grid grid-cols-1 sm:grid-cols-[1fr_7rem_9rem_auto] gap-2 items-start">
                                        <input type="text" wire:model="ticket_types.{{ $ti }}.discounts.{{ $dii }}.label" placeholder="Etichetă (ex. Early bird)" class="{{ $in }}">
                                        <input type="number" step="0.01" min="0" wire:model="ticket_types.{{ $ti }}.discounts.{{ $dii }}.price" placeholder="Preț" class="{{ $in }}">
                                        <input type="date" wire:model="ticket_types.{{ $ti }}.discounts.{{ $dii }}.until" class="accent-primary [color-scheme:light] {{ $in }}" title="Valabil până la">
                                        <button type="button" wire:click="removeTicketDiscount({{ $ti }}, {{ $dii }})" class="h-[42px] w-10 inline-flex items-center justify-center rounded-lg border border-border text-ink-soft hover:border-danger hover:text-danger shrink-0" title="Elimină">
                                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                            <button type="button" wire:click="addTicketDiscount({{ $ti }})" class="mt-2 inline-flex items-center gap-1.5 text-xs font-medium text-primary hover:underline">
                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                Adaugă reducere
                            </button>
                        </div>
                    </div>
                @endforeach

                <button type="button" wire:click="addTicketType" class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Adaugă tip de bilet
                </button>
            </div>
        </div>

        {{-- ============ Modalități plată ============ --}}
        <div class="{{ $card }}">
            <h3 class="text-sm font-semibold text-ink">Modalități de plată</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                @foreach ($paymentMethods as $key => $label)
                    <label class="flex items-center gap-2.5 text-sm text-ink">
                        <input type="checkbox" wire:model="payment_predefined.{{ $key }}" class="w-4 h-4 rounded border-border accent-primary" style="accent-color: var(--color-primary);">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
            <div>
                <span class="text-xs font-medium text-ink-soft">Alte modalități</span>
                <div class="mt-2 space-y-2">
                    @foreach ($payment_custom as $i => $pc)
                        <div wire:key="pay-{{ $i }}" class="flex items-center gap-2">
                            <input type="text" wire:model="payment_custom.{{ $i }}" placeholder="ex. Revolut" class="{{ $in }}">
                            <button type="button" wire:click="removePaymentCustom({{ $i }})" class="h-[42px] w-10 inline-flex items-center justify-center rounded-lg border border-border text-ink-soft hover:border-danger hover:text-danger shrink-0" title="Elimină">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            </button>
                        </div>
                    @endforeach
                </div>
                <button type="button" wire:click="addPaymentCustom" class="mt-2 inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Adaugă modalitate
                </button>
            </div>
        </div>

        {{-- ============ Contact (mai multe persoane) ============ --}}
        <div class="{{ $card }}">
            <h3 class="text-sm font-semibold text-ink">Contact</h3>

            <div class="space-y-3">
                @foreach ($contacts as $i => $c)
                    @php $cid = $c['admin_id'] ?? null; $ca = $cid ? ($contactAdmins[$cid] ?? null) : null; @endphp
                    <div wire:key="contact-{{ $i }}" class="rounded-xl border border-border p-3 space-y-2">
                        <div class="flex items-center gap-2">
                            <x-dropdown-select path="contacts.{{ $i }}.admin_id" :options="$contactOptions" :selected="$c['admin_id'] ?? ''" placeholder="Alege…" class="flex-1" />
                            @if (count($contacts) > 1)
                                <button type="button" wire:click="removeContact({{ $i }})" class="h-[42px] w-10 inline-flex items-center justify-center rounded-lg border border-border text-ink-soft hover:border-danger hover:text-danger shrink-0" title="Șterge contactul">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                                </button>
                            @endif
                        </div>

                        @if ($ca)
                            <div class="rounded-lg bg-bg border border-border px-3.5 py-2.5 text-sm text-ink-soft">
                                <span class="font-medium text-ink">{{ $ca['name'] ?: 'Fără nume' }}</span>{{ $ca['phone'] ? ' — '.$ca['phone'] : '' }}
                            </div>
                        @else
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <input type="text" wire:model="contacts.{{ $i }}.name" placeholder="Nume" class="{{ $in }}">
                                <input type="text" wire:model="contacts.{{ $i }}.phone" placeholder="07XXXXXXXX" class="{{ $in }}">
                            </div>
                        @endif

                        <input type="text" wire:model="contacts.{{ $i }}.note" placeholder="Notă (opțional): ex. Mesaje pe WhatsApp" class="{{ $in }}">
                    </div>
                @endforeach
            </div>

            <button type="button" wire:click="addContact" class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Adaugă persoană de contact
            </button>
        </div>

        {{-- ============ Linkuri ============ --}}
        <div class="{{ $card }}">
            <h3 class="text-sm font-semibold text-ink">Linkuri <span class="text-ink-soft/60 font-normal">(galerie foto, bilete, pagină event…)</span></h3>
            <div class="space-y-2">
                @foreach ($links as $i => $link)
                    <div wire:key="link-{{ $i }}" class="grid grid-cols-1 sm:grid-cols-[10rem_1fr_auto] gap-2 items-start">
                        <input type="text" wire:model="links.{{ $i }}.label" placeholder="Etichetă buton" class="{{ $in }}">
                        <input type="url" wire:model="links.{{ $i }}.url" placeholder="https://…" class="{{ $in }}">
                        <button type="button" wire:click="removeLink({{ $i }})" class="h-[42px] w-10 inline-flex items-center justify-center rounded-lg border border-border text-ink-soft hover:border-danger hover:text-danger shrink-0" title="Elimină">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        </button>
                    </div>
                    @error('links.'.$i.'.url') <p class="{{ $err }}">{{ $message }}</p> @enderror
                @endforeach
            </div>
            <button type="button" wire:click="addLink" class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Adaugă link
            </button>
        </div>

        {{-- ============ Câmpuri suplimentare ============ --}}
        <div class="{{ $card }}">
            <h3 class="text-sm font-semibold text-ink">Câmpuri suplimentare <span class="text-ink-soft/60 font-normal">(ex. Capacitate, Vârstă minimă, Parcare…)</span></h3>
            <div class="space-y-2">
                @foreach ($custom_fields as $i => $field)
                    <div wire:key="cf-{{ $i }}" class="grid grid-cols-1 sm:grid-cols-[12rem_1fr_auto] gap-2 items-start">
                        <input type="text" wire:model="custom_fields.{{ $i }}.label" placeholder="Etichetă" class="{{ $in }}">
                        <input type="text" wire:model="custom_fields.{{ $i }}.value" placeholder="Valoare" class="{{ $in }}">
                        <button type="button" wire:click="removeCustomField({{ $i }})" class="h-[42px] w-10 inline-flex items-center justify-center rounded-lg border border-border text-ink-soft hover:border-danger hover:text-danger shrink-0" title="Elimină">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        </button>
                    </div>
                @endforeach
            </div>
            <button type="button" wire:click="addCustomField" class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Adaugă câmp
            </button>
        </div>

        {{-- ============ Plasare & stare ============ --}}
        <div class="{{ $card }}">
            <h3 class="text-sm font-semibold text-ink">Plasare și vizibilitate</h3>
            <div>
                <label class="{{ $lbl }} mb-1.5">Audiență</label>
                <x-select wire:model="audience" :options="['all' => 'Toți (inclusiv nelogați)', 'auth' => 'Doar utilizatorii logați']" />
            </div>
            <label class="flex items-center gap-2.5 text-sm text-ink">
                <input type="checkbox" wire:model="in_carousel" class="w-4 h-4 rounded border-border accent-primary" style="accent-color: var(--color-primary);">
                În carusel <span class="text-ink-soft/70">— apare și în caruselul din partea de sus a app-ului</span>
            </label>
            <div>
                <label class="{{ $lbl }} mb-1.5">Stare</label>
                <x-select wire:model="status" class="sm:max-w-xs" :options="['published' => 'Publicată', 'draft' => 'Ciornă (nu apare în app)']" />
            </div>
            <label class="flex items-center gap-2.5 text-sm text-ink">
                <input type="checkbox" wire:model="is_active" class="w-4 h-4 rounded border-border accent-primary" style="accent-color: var(--color-primary);">
                Vizibilă în app <span class="text-ink-soft/70">— debifat = ascunsă temporar</span>
            </label>
        </div>

        {{-- ============ Descriere (ultima) ============ --}}
        <div class="{{ $card }}">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-ink">Descriere</h3>
                <x-btn variant="info" size="sm" outline wire:click="generateDescription" wire:loading.attr="disabled" wire:target="generateDescription">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.9 4.8L18.7 9l-4.8 1.9L12 15.7 10.1 10.9 5.3 9l4.8-1.2z"/><path d="M18 15l.7 1.8L20.5 17.5 18.7 18.2 18 20l-.7-1.8L15.5 17.5l1.8-.7z"/></svg>
                    Generează descriere
                </x-btn>
            </div>
            <p class="text-xs text-ink-soft/80">Compune un text din câmpurile completate mai sus. Îl poți edita apoi liber.</p>
            <textarea id="description" wire:model="description" rows="5" class="mt-1 {{ $in }}"></textarea>
            @error('description') <p class="{{ $err }}">{{ $message }}</p> @enderror
        </div>

        {{-- ============ Acțiuni ============ --}}
        <div class="flex items-center gap-4 pt-1">
            <x-btn variant="primary" type="submit" wire:loading.attr="disabled" wire:target="save">
                {{ $isEditing ? 'Salvează modificările' : 'Creează petrecerea' }}
            </x-btn>
            <a href="{{ route('admin.parties.index') }}" wire:navigate class="text-sm text-ink-soft hover:text-ink">Anulează</a>
        </div>

    </form>
</div>
