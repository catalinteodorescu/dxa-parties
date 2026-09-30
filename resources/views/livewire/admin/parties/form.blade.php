<div class="max-w-2xl">
    @php
        $isEditing = $party && $party->exists;
        $in = 'w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary';
        $sel = 'appearance-none w-full rounded-lg border border-border bg-white px-3.5 py-2.5 pr-9 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary cursor-pointer';
        $lbl = 'block text-sm font-medium text-ink';
        $card = 'bg-surface border border-border rounded-2xl p-5 sm:p-6 space-y-5';
        $err = 'error-msg mt-1.5 text-sm text-danger';
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
                                <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
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

        {{-- ============ Când (SIMPLĂ + FESTIVAL) ============ --}}
        <div class="{{ $card }}">
            <h3 class="text-sm font-semibold text-ink">Când</h3>

            {{-- Petrecere simplă: o seară --}}
            <div x-show="$wire.kind === 'basic'" x-cloak class="space-y-5">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="start_date" class="{{ $lbl }}">Data</label>
                        <input type="date" id="start_date" wire:model="start_date" class="accent-primary [color-scheme:light] mt-1.5 {{ $in }}">
                        @if ($kind === 'basic') @error('start_date') <p class="{{ $err }}">{{ $message }}</p> @enderror @endif
                    </div>
                    <div>
                        <label for="start_time" class="{{ $lbl }}">Ora început</label>
                        <input type="time" step="300" id="start_time" wire:model="start_time" class="mt-1.5 {{ $in }}">
                        @error('start_time') <p class="{{ $err }}">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="end_time" class="{{ $lbl }}">Ora sfârșit</label>
                        <input type="time" step="300" id="end_time" wire:model="end_time" class="mt-1.5 {{ $in }}">
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

            {{-- Festival: interval de zile; „Program pe zile" se completează automat cu zilele dintre cele două date --}}
            <div x-show="$wire.kind === 'festival'" x-cloak class="space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="festival_start_date" class="{{ $lbl }}">Data început</label>
                        <input type="date" id="festival_start_date" wire:model.live="start_date" class="accent-primary [color-scheme:light] mt-1.5 {{ $in }}">
                        @if ($kind === 'festival') @error('start_date') <p class="{{ $err }}">{{ $message }}</p> @enderror @endif
                    </div>
                    <div>
                        <label for="festival_end_date" class="{{ $lbl }}">Data sfârșit</label>
                        <input type="date" id="festival_end_date" wire:model.live="end_date" min="{{ $start_date }}" class="accent-primary [color-scheme:light] mt-1.5 {{ $in }}">
                        @error('end_date') <p class="{{ $err }}">{{ $message }}</p> @enderror
                    </div>
                </div>
                <p class="text-xs text-ink-soft/80">
                    Zilele dintre cele două date apar automat mai jos, la „Program pe zile" (orele și programul fiecărei zile se completează acolo).
                </p>
            </div>
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
                    <div wire:key="day-{{ $day['date'] ?? $di }}" class="rounded-xl border border-border p-3 sm:p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold text-ink">Ziua {{ $di + 1 }}
                                @if (! empty($day['date']))
                                    <span class="font-normal text-ink-soft">· {{ \Illuminate\Support\Carbon::parse($day['date'])->locale('ro')->translatedFormat('l, d.m.Y') }}</span>
                                @endif
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                            <input type="time" step="300" wire:model="days.{{ $di }}.start_time" class="{{ $in }}" title="Ora început">
                            <input type="time" step="300" wire:model="days.{{ $di }}.end_time" class="{{ $in }}" title="Ora sfârșit">
                            <input type="text" wire:model="days.{{ $di }}.dresscode" placeholder="Dresscode" class="{{ $in }}">
                        </div>
                        @error('days.'.$di.'.date') <p class="{{ $err }}">{{ $message }}</p> @enderror

                        <div class="space-y-2">
                            <span class="text-xs font-medium text-ink-soft">Program</span>
                            @foreach ($day['program'] ?? [] as $pi => $item)
                                <div wire:key="day-{{ $day['date'] ?? $di }}-item-{{ $pi }}" class="rounded-lg border border-border bg-bg/40 p-2.5 space-y-2">
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                        <input type="time" step="300" wire:model="days.{{ $di }}.program.{{ $pi }}.start" class="{{ $in }}" title="De la">
                                        <input type="time" step="300" wire:model="days.{{ $di }}.program.{{ $pi }}.end" class="{{ $in }}" title="Până la">
                                        <x-dropdown-select path="days.{{ $di }}.program.{{ $pi }}.type" :options="$programTypes" :selected="$item['type'] ?? ''" placeholder="Tip" />
                                    </div>
                                    <input type="text" wire:model="days.{{ $di }}.program.{{ $pi }}.title" placeholder="Titlu (ex. Workshop Bachata Sensual)" class="{{ $in }}">
                                    <div class="grid grid-cols-1 sm:grid-cols-[1fr_1fr_auto] gap-2 items-start">
                                        <input type="text" list="dxa-guest-names" wire:model="days.{{ $di }}.program.{{ $pi }}.guest" placeholder="Invitat (opțional)" class="{{ $in }}">
                                        <input type="text" wire:model="days.{{ $di }}.program.{{ $pi }}.room" placeholder="Sală (opțional)" class="{{ $in }}">
                                        <button type="button" wire:click="removeProgramItem({{ $di }}, {{ $pi }})" class="h-[42px] w-10 inline-flex items-center justify-center rounded-lg border border-border text-ink-soft hover:border-danger hover:text-danger shrink-0" title="Elimină">
                                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/></svg>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                            <button type="button" wire:click="addProgramItem({{ $di }})" class="inline-flex items-center gap-1.5 text-xs font-medium text-primary hover:underline">
                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                                Adaugă în program
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            @if (empty($days))
                <p class="text-sm text-ink-soft/70 italic">Alege data de început și data de sfârșit din secțiunea „Când" — zilele festivalului apar aici automat.</p>
            @endif
            @error('days') <p class="{{ $err }}">{{ $message }}</p> @enderror
        </div>

        {{-- ============ Locație ============ --}}
        <div class="{{ $card }}">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-ink">Locație</h3>
                <button type="button" wire:click="fillSchoolVenue" class="text-xs font-medium text-primary hover:underline">Completează cu locația școlii</button>
            </div>
            <div>
                <label for="location_name" class="{{ $lbl }}">Nume locație</label>
                <input type="text" id="location_name" wire:model="location_name" placeholder="{{ \App\Support\Branding::name() }}" class="mt-1.5 {{ $in }}">
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
                                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
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
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                Adaugă invitat
            </button>
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
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/></svg>
                        </button>
                    </div>
                    @error('music_styles.'.$i.'.frequency') <p class="{{ $err }}">{{ $message }}</p> @enderror
                @endforeach
            </div>

            <button type="button" wire:click="addMusicStyle" class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
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
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 11v6"/><path d="M14 11v6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            </button>
                        </div>
                        @error('ticket_types.'.$ti.'.price') <p class="{{ $err }}">{{ $message }}</p> @enderror

                        {{-- Reduceri pentru acest bilet --}}
                        <div class="pl-1">
                            <span class="text-xs font-medium text-ink-soft">Reduceri (early-bird sau intrare gratuită până la o oră)</span>
                            <div class="mt-2 space-y-2">
                                @foreach ($type['discounts'] ?? [] as $dii => $disc)
                                    <div wire:key="tt-{{ $ti }}-d-{{ $dii }}" class="grid grid-cols-1 sm:grid-cols-[1fr_7rem_12.5rem_auto] gap-2 items-start">
                                        <input type="text" wire:model="ticket_types.{{ $ti }}.discounts.{{ $dii }}.label" placeholder="Etichetă (ex. Early bird, Gratuit până la 22:30)" class="{{ $in }}">
                                        <input type="number" step="0.01" min="0" wire:model="ticket_types.{{ $ti }}.discounts.{{ $dii }}.price" placeholder="Preț" class="{{ $in }}">
                                        <input type="datetime-local" wire:model="ticket_types.{{ $ti }}.discounts.{{ $dii }}.until" class="accent-primary [color-scheme:light] {{ $in }}" title="Valabil până la (data și ora)">
                                        <button type="button" wire:click="removeTicketDiscount({{ $ti }}, {{ $dii }})" class="h-[42px] w-10 inline-flex items-center justify-center rounded-lg border border-border text-ink-soft hover:border-danger hover:text-danger shrink-0" title="Elimină">
                                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/></svg>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                            <button type="button" wire:click="addTicketDiscount({{ $ti }})" class="mt-2 inline-flex items-center gap-1.5 text-xs font-medium text-primary hover:underline">
                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                                Adaugă reducere
                            </button>
                        </div>
                    </div>
                @endforeach

                <button type="button" wire:click="addTicketType" class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                    Adaugă tip de bilet
                </button>
            </div>
        </div>

        {{-- ============ Modalități plată ============ --}}
        <div class="{{ $card }}">
            <div>
                <h3 class="text-sm font-semibold text-ink">Modalități de plată</h3>
                <p class="mt-1 text-xs text-ink-soft leading-relaxed">
                    Alege ce se acceptă la această petrecere. Lista vine din
                    <a href="{{ route('admin.settings.index') }}" wire:navigate class="text-primary hover:underline">Setări</a>,
                    unde se adaugă și metodele noi. Tokenii se folosesc doar la bar; creditele plătesc și intrarea, și barul; la bar, cash se acceptă mereu.
                </p>
                @if (empty($payment_methods))
                    <p class="mt-1 text-xs text-warning">Nicio metodă bifată — se acceptă toate metodele active din Setări.</p>
                @endif
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                @foreach ($paymentChoices as $key => $choice)
                    <label wire:key="pay-{{ $key }}" class="flex items-center gap-2.5 text-sm {{ $choice['enabled'] ? 'text-ink' : 'text-ink-soft' }}">
                        <input type="checkbox" value="{{ $key }}" wire:model="payment_methods" class="w-4 h-4 rounded border-border accent-primary" style="accent-color: var(--color-primary);">
                        {{ $choice['label'] }}
                        @unless ($choice['enabled']) <span class="text-xs text-warning">(dezactivată)</span> @endunless
                        @if ($key === 'token') <span class="text-xs text-ink-soft/70">doar la bar</span> @endif
                    </label>
                @endforeach
            </div>
        </div>

        {{-- ============ Card de fidelitate ============ --}}
        @if ($loyaltyEnabled)
            <div class="{{ $card }}">
                <label class="flex items-start gap-2.5 text-sm text-ink">
                    <input type="checkbox" wire:model="loyalty_eligible" class="mt-0.5 w-4 h-4 rounded border-border accent-primary" style="accent-color: var(--color-primary);">
                    <span>
                        <span class="font-medium">Acordă ștampile de fidelitate</span>
                        <span class="block mt-0.5 text-xs text-ink-soft leading-relaxed">
                            Participanții înrolați primesc automat o ștampilă la o intrare identificată. Controlează și acceptarea plății „Beneficiu" la intrare (folosită pentru bonusul de fidelitate — card digital complet sau card fizic).
                        </span>
                    </span>
                </label>
            </div>
        @endif

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
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 11v6"/><path d="M14 11v6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
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
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                Adaugă persoană de contact
            </button>
        </div>

        {{-- ============ Coduri de reducere (DXA: Coduri de reducere) ============ --}}
        <div class="{{ $card }}" x-show="!$wire.is_free" x-cloak>
            <div>
                <h3 class="text-sm font-semibold text-ink">Coduri de reducere</h3>
                <p class="mt-1 text-xs text-ink-soft leading-relaxed">
                    Pentru cumpărarea intrării din aplicația participanților. Poți avea mai multe coduri (de ex. câte unul pe promotor). Un cod se aplică peste prețul curent, fiecărui bilet din comandă, și nu se cumulează cu alt cod. Fiecare bilet cu reducere este o utilizare a codului.
                </p>
            </div>

            @php $trash = '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 11v6"/><path d="M14 11v6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>'; @endphp

            <div class="space-y-3">
                @forelse ($discount_codes as $i => $dc)
                    @php
                        $tierOptions = ['' => 'Alege treapta…'];
                        foreach ($codeTierLabels as $tl) { $tierOptions[$tl] = $tl; }
                        $curTier = trim((string) ($dc['tier_label'] ?? ''));
                        if ($curTier !== '' && ! collect($codeTierLabels)->contains(fn ($l) => mb_strtolower($l) === mb_strtolower($curTier))) {
                            $tierOptions[$curTier] = $curTier.' (nu mai există)';
                        }
                        $uses = isset($dc['id']) ? ($codeUses[$dc['id']] ?? 0) : 0;
                    @endphp
                    <div wire:key="dc-{{ $dc['id'] ?? 'n' }}-{{ $i }}" class="rounded-xl border border-border p-3 space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-[1fr_auto_auto] gap-2 items-start">
                            <div>
                                <input type="text" wire:model="discount_codes.{{ $i }}.code" placeholder="COD (ex. ANA10)" autocapitalize="characters" class="{{ $in }} font-mono uppercase tracking-wide">
                                @error('discount_codes.'.$i.'.code') <p class="{{ $err }}">{{ $message }}</p> @enderror
                            </div>
                            <button type="button" wire:click="generateDiscountCode({{ $i }})" class="h-[42px] px-3 inline-flex items-center rounded-lg border border-border text-sm text-ink-soft hover:border-primary hover:text-primary">Generează</button>
                            <button type="button" wire:click="removeDiscountCode({{ $i }})" class="h-[42px] w-10 inline-flex items-center justify-center rounded-lg border border-border text-ink-soft hover:border-danger hover:text-danger shrink-0" title="Șterge codul">{!! $trash !!}</button>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <div>
                                <div class="flex items-center gap-2">
                                    <x-select wire:model="discount_codes.{{ $i }}.promoter_id" :options="$promoterOptions" placeholder="Fără promotor" class="flex-1 min-w-0" />
                                    <button type="button" wire:click="openPromoterModal({{ $i }})" title="Promotor nou" aria-label="Promotor nou"
                                            class="h-[42px] w-10 inline-flex items-center justify-center rounded-lg border border-border text-ink-soft hover:border-primary hover:text-primary shrink-0">
                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                                    </button>
                                </div>
                                @error('discount_codes.'.$i.'.promoter_id') <p class="{{ $err }}">{{ $message }}</p> @enderror
                            </div>
                            <input type="text" wire:model="discount_codes.{{ $i }}.note" placeholder="Notă internă (opțional)" class="{{ $in }}">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 items-start">
                            <div>
                                <x-select wire:model="discount_codes.{{ $i }}.type" :live="true" :options="\App\Models\PartyDiscountCode::TYPES" />
                                @error('discount_codes.'.$i.'.type') <p class="{{ $err }}">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                @if (($dc['type'] ?? '') === 'tier')
                                    <x-select wire:model="discount_codes.{{ $i }}.tier_label" :live="true" :options="$tierOptions" />
                                    @error('discount_codes.'.$i.'.tier_label') <p class="{{ $err }}">{{ $message }}</p> @enderror
                                @else
                                    <div class="relative">
                                        <input type="text" inputmode="decimal" wire:model="discount_codes.{{ $i }}.value" placeholder="{{ ($dc['type'] ?? '') === 'amount' ? 'Reducere (lei)' : 'Reducere (%)' }}" class="{{ $in }} pr-12">
                                        <span class="pointer-events-none absolute inset-y-0 right-3.5 flex items-center text-sm text-ink-soft">{{ ($dc['type'] ?? '') === 'amount' ? 'lei' : '%' }}</span>
                                    </div>
                                    @error('discount_codes.'.$i.'.value') <p class="{{ $err }}">{{ $message }}</p> @enderror
                                @endif
                            </div>
                        </div>

                        <div>
                            <span class="text-xs font-medium text-ink-soft">Se aplică la <span class="font-normal">(nimic bifat = toate biletele)</span></span>
                            <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1.5">
                                @foreach ($codeTicketNames as $tn)
                                    <label wire:key="dc-{{ $i }}-tn-{{ $loop->index }}" class="inline-flex items-center gap-2 text-sm text-ink">
                                        <input type="checkbox" value="{{ $tn }}" wire:model="discount_codes.{{ $i }}.ticket_types" class="w-4 h-4 rounded border-border accent-primary" style="accent-color: var(--color-primary);">
                                        {{ $tn }}
                                    </label>
                                @endforeach
                            </div>
                            @error('discount_codes.'.$i.'.ticket_types') <p class="{{ $err }}">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <div>
                                <label class="text-xs font-medium text-ink-soft">Valabil de la</label>
                                <input type="datetime-local" wire:model="discount_codes.{{ $i }}.valid_from" class="mt-1 accent-primary [color-scheme:light] {{ $in }}">
                                @error('discount_codes.'.$i.'.valid_from') <p class="{{ $err }}">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="text-xs font-medium text-ink-soft">Valabil până la</label>
                                <input type="datetime-local" wire:model="discount_codes.{{ $i }}.valid_until" class="mt-1 accent-primary [color-scheme:light] {{ $in }}">
                                @error('discount_codes.'.$i.'.valid_until') <p class="{{ $err }}">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <div>
                                <label class="text-xs font-medium text-ink-soft">Limită totală (bilete cu reducere) <span class="font-normal">— gol = nelimitat</span></label>
                                <input type="text" inputmode="numeric" wire:model="discount_codes.{{ $i }}.max_uses" placeholder="nelimitat" class="mt-1 {{ $in }}">
                                @error('discount_codes.'.$i.'.max_uses') <p class="{{ $err }}">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="text-xs font-medium text-ink-soft">Limită per participant (bilete) <span class="font-normal">— gol = nelimitat</span></label>
                                <input type="text" inputmode="numeric" wire:model="discount_codes.{{ $i }}.max_uses_per_participant" placeholder="nelimitat" class="mt-1 {{ $in }}">
                                @error('discount_codes.'.$i.'.max_uses_per_participant') <p class="{{ $err }}">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-3 flex-wrap">
                            <label class="inline-flex items-center gap-2.5 text-sm text-ink">
                                <input type="checkbox" wire:model="discount_codes.{{ $i }}.is_active" class="w-4 h-4 rounded border-border accent-primary" style="accent-color: var(--color-primary);">
                                Activ
                            </label>
                            @if (! empty($dc['id']))
                                <span class="text-xs text-ink-soft">Folosit pentru {{ $uses }} {{ $uses === 1 ? 'bilet' : 'bilete' }}</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-ink-soft">Niciun cod încă.</p>
                @endforelse
            </div>

            <button type="button" wire:click="addDiscountCode" class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                Adaugă cod de reducere
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
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/></svg>
                        </button>
                    </div>
                    @error('links.'.$i.'.url') <p class="{{ $err }}">{{ $message }}</p> @enderror
                @endforeach
            </div>
            <button type="button" wire:click="addLink" class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
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
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/></svg>
                        </button>
                    </div>
                @endforeach
            </div>
            <button type="button" wire:click="addCustomField" class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
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
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z"/><path d="M20 2v4"/><path d="M22 4h-4"/><circle cx="4" cy="20" r="2"/></svg>
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

    {{-- Popup centrat: promotor nou (din butonul + de lângă selectul de promotor) --}}
    @if ($promoterModalRow !== null)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data x-on:keydown.escape.window="$wire.closePromoterModal()">
            <div class="absolute inset-0 bg-ink/40" wire:click="closePromoterModal"></div>
            <div class="relative bg-surface rounded-2xl border border-border shadow-lg max-w-sm w-full p-6" role="dialog" aria-modal="true" aria-labelledby="promoter-modal-title">
                <h3 id="promoter-modal-title" class="text-base font-semibold text-ink">Promotor nou</h3>
                <p class="mt-1 text-xs text-ink-soft">Se adaugă în evidența promotorilor și se alege pentru acest cod.</p>

                <form wire:submit="savePromoter" class="mt-4 space-y-3">
                    <div>
                        <label class="{{ $lbl }} mb-1.5" for="new-promoter-name">Nume</label>
                        <input type="text" id="new-promoter-name" wire:model="newPromoterName" autofocus autocomplete="off" placeholder="ex. Ana Popescu" class="{{ $in }}">
                        @error('newPromoterName') <p class="{{ $err }}">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $lbl }} mb-1.5" for="new-promoter-phone">Telefon <span class="font-normal text-ink-soft">(opțional)</span></label>
                        <input type="text" id="new-promoter-phone" wire:model="newPromoterPhone" autocomplete="off" placeholder="07XXXXXXXX" class="{{ $in }}">
                        @error('newPromoterPhone') <p class="{{ $err }}">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $lbl }} mb-1.5" for="new-promoter-note">Notă <span class="font-normal text-ink-soft">(opțional)</span></label>
                        <input type="text" id="new-promoter-note" wire:model="newPromoterNote" autocomplete="off" class="{{ $in }}">
                        @error('newPromoterNote') <p class="{{ $err }}">{{ $message }}</p> @enderror
                    </div>
                    <div class="pt-2 flex items-center justify-end gap-3">
                        <button type="button" wire:click="closePromoterModal" class="text-sm font-medium text-ink-soft hover:text-ink px-3 py-2">Anulează</button>
                        <x-btn variant="primary" type="submit" wire:loading.attr="disabled" wire:target="savePromoter">Adaugă promotorul</x-btn>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
