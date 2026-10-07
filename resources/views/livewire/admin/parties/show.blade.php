<div class="max-w-2xl">
    @php
        $p = $party;
        $states = [
            'live'     => ['Acum',        'bg-primary-soft text-primary'],
            'upcoming' => ['Viitoare',    'bg-warning/10 text-warning'],
            'past'     => ['Trecută',     'bg-ink/5 text-ink-soft'],
            'inactive' => ['Dezactivată', 'bg-surface border border-border text-ink-soft'],
            'draft'    => ['Ciornă',      'bg-info-soft text-info'],
        ];
        [$stateLabel, $stateClasses] = $states[$p->state()];
        $zile = ['duminică', 'luni', 'marți', 'miercuri', 'joi', 'vineri', 'sâmbătă'];
        $guestStyles = \App\Models\Party::GUEST_STYLES;
        $programTypes = \App\Models\Party::PROGRAM_TYPES;
        $paymentLabels = \App\Support\PaymentMethods::describe($p->payment_methods ?? []);
        $card = 'bg-surface border border-border rounded-2xl p-5 sm:p-6';
        $fmt = function ($v) {
            $v = (float) $v;
            return ($v == floor($v) ? number_format($v, 0, ',', '.') : number_format($v, 2, ',', '.')).' lei';
        };

        $price = $p->currentPrice();
        if ($price === null) {
            $priceSummary = null;
        } elseif ((float) $price === 0.0) {
            $priceSummary = 'Gratuit';
        } else {
            $priceSummary = ($p->hasMultipleTicketTypes() ? 'de la ' : '').$fmt($price);
        }
    @endphp

    {{-- Header --}}
    <div class="flex items-center justify-between gap-3 mb-5">
        <a href="{{ route('admin.parties.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm text-ink-soft hover:text-ink">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
            Înapoi la listă
        </a>
        <div class="flex items-center gap-2">
        <x-btn variant="info" size="sm" outline :href="route('admin.parties.attendees', $p)" wire:navigate>
            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            Participanți
        </x-btn>
        <x-btn variant="success" size="sm" outline :href="route('admin.parties.stats', $p)" wire:navigate>
            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg>
            Statistici
        </x-btn>
        @permits('parties', 'edit')
        <x-btn variant="warning" size="sm" outline :href="route('admin.parties.edit', $p)" wire:navigate>
            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/></svg>
            Editează
        </x-btn>
        @endpermits
        </div>
    </div>

    {{-- Hero --}}
    <div class="{{ $card }} mb-4">
        @if ($p->imageUrl())
            <img src="{{ $p->imageUrl() }}" alt="" class="w-full h-52 sm:h-64 object-cover rounded-xl border border-border mb-4">
        @endif

        <div class="flex items-center gap-2 flex-wrap">
            <span class="inline-flex items-center rounded-full text-xs font-medium px-2 py-0.5 {{ $stateClasses }}">{{ $stateLabel }}</span>
            <span class="inline-flex items-center rounded-full text-xs font-medium px-2 py-0.5 {{ $p->isFestival() ? 'bg-purple/10 text-purple' : 'bg-bg text-ink-soft' }}">{{ $p->isFestival() ? 'Festival' : 'Simplă' }}</span>
            <span class="inline-flex items-center rounded-full bg-bg text-ink-soft text-xs px-2 py-0.5">{{ $p->audience === 'auth' ? 'Doar logați' : 'Toți' }}</span>
            @if ($p->in_carousel)
                <span class="inline-flex items-center rounded-full bg-bg text-ink-soft text-xs px-2 py-0.5">Carusel</span>
            @endif
        </div>

        <h2 class="mt-2 text-xl font-semibold text-ink">{{ $p->name }}</h2>

        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-sm text-ink-soft">
            <span class="inline-flex items-center gap-1.5">
                <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5.8 11.3 2 22l10.7-3.79"/><path d="M4 3h.01"/><path d="M22 8h.01"/><path d="M15 2h.01"/><path d="M22 20h.01"/><path d="m22 2-2.24.75a2.9 2.9 0 0 0-1.96 3.12c.1.86-.57 1.63-1.45 1.63h-.38c-.86 0-1.6.6-1.76 1.44L14 10"/><path d="m22 13-.82-.33c-.86-.34-1.82.2-1.98 1.11c-.11.7-.72 1.22-1.43 1.22H17"/><path d="m11 2 .33.82c.34.86-.2 1.82-1.11 1.98C9.52 4.9 9 5.52 9 6.23V7"/><path d="M11 13c1.93 1.93 2.83 4.17 2 5-.83.83-3.07-.07-5-2-1.93-1.93-2.83-4.17-2-5 .83-.83 3.07.07 5 2Z"/></svg>
                @if ($p->isFestival() && $p->end_date && $p->start_date && $p->end_date->ne($p->start_date))
                    {{ $p->start_date->translatedFormat('d.m.Y') }} – {{ $p->end_date->format('d.m.Y') }}
                @elseif ($p->starts_at)
                    {{ $zile[$p->starts_at->dayOfWeek] }}, {{ $p->starts_at->format('d.m.Y') }}@if (! $p->isFestival() && $p->starts_at), {{ $p->starts_at->format('H:i') }}@if ($p->ends_at)–{{ $p->ends_at->format('H:i') }}@endif @endif
                @endif
            </span>
            @if ($priceSummary)
                <span class="inline-flex items-center gap-1.5 {{ $priceSummary === 'Gratuit' ? 'text-primary font-medium' : '' }}">
                    <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    {{ $priceSummary }}
                </span>
            @endif
        </div>

        @if ($p->location_name)
            <div class="mt-2 flex items-center gap-1.5 text-sm text-ink-soft">
                <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>
                <span>{{ $p->location_name }}@if ($p->location_address) · {{ $p->location_address }}@endif</span>
                @if ($p->location_url)
                    <a href="{{ $p->location_url }}" target="_blank" rel="noopener" class="text-primary hover:underline shrink-0">hartă</a>
                @endif
            </div>
        @endif
    </div>

    {{-- Contoare reale (runda 40) --}}
    <div class="{{ $card }} mb-4">
        <h3 class="text-sm font-semibold text-ink mb-2">Interes în aplicație</h3>
        <div class="flex flex-wrap gap-6">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-ink-soft/60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                <div>
                    <div class="text-lg font-semibold text-ink leading-tight">{{ number_format($p->statViews(), 0, ',', '.') }}</div>
                    <div class="text-xs text-ink-soft">Afișări</div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-ink-soft/60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 4.1 12 6"/><path d="m5.1 8-2.9-.8"/><path d="m6 12-1.9 2"/><path d="M7.2 2.2 8 5.1"/><path d="M9.037 9.69a.498.498 0 0 1 .653-.653l11 4.5a.5.5 0 0 1-.074.949l-4.349 1.041a1 1 0 0 0-.74.739l-1.04 4.35a.5.5 0 0 1-.95.074z"/></svg>
                <div>
                    <div class="text-lg font-semibold text-ink leading-tight">{{ number_format($p->statOpens(), 0, ',', '.') }}</div>
                    <div class="text-xs text-ink-soft">Deschideri</div>
                </div>
            </div>
            <div class="flex items-center gap-2" data-interested-count>
                <svg class="w-5 h-5 text-ink-soft/60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
                <div>
                    <div class="text-lg font-semibold text-ink leading-tight">{{ number_format($p->interests()->count(), 0, ',', '.') }}</div>
                    <div class="text-xs text-ink-soft">Interesați</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Stiluri muzică --}}
    @if (! empty($p->music_styles))
        <div class="{{ $card }} mb-4">
            <h3 class="text-sm font-semibold text-ink mb-2">Stiluri muzică</h3>
            <div class="flex flex-wrap items-center gap-2 text-sm">
                @foreach ($p->music_styles as $i => $ms)
                    <span class="inline-flex items-center rounded-full bg-bg text-ink text-xs px-2.5 py-1">
                        {{ $ms['frequency'] ?? '?' }}× {{ $ms['style'] ?? '' }}
                    </span>
                    @if (! $loop->last)
                        <svg class="w-3 h-3 text-ink-soft/50 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    @endif
                @endforeach
                <svg class="w-3 h-3 text-ink-soft/50 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                <span class="text-xs text-ink-soft/60 italic">se reia</span>
            </div>
        </div>
    @endif

    {{-- Program (festival) --}}
    @if ($p->isFestival() && ! empty($p->days))
        <div class="{{ $card }} mb-4 space-y-4">
            <h3 class="text-sm font-semibold text-ink">Program</h3>
            @foreach ($p->days as $day)
                @php $dc = ! empty($day['date']) ? \Illuminate\Support\Carbon::parse($day['date']) : null; @endphp
                <div class="rounded-xl border border-border p-4">
                    <div class="flex items-center justify-between gap-2 flex-wrap">
                        <span class="font-medium text-ink">
                            @if ($dc){{ ucfirst($zile[$dc->dayOfWeek]) }}, {{ $dc->format('d.m.Y') }}@endif
                        </span>
                        <span class="text-sm text-ink-soft">
                            @if (! empty($day['start_time'])){{ substr($day['start_time'], 0, 5) }}@if (! empty($day['end_time']))–{{ substr($day['end_time'], 0, 5) }}@endif @endif
                        </span>
                    </div>
                    @if (! empty($day['dresscode']))
                        <div class="mt-1 text-sm text-ink-soft">Dresscode: {{ $day['dresscode'] }}</div>
                    @endif

                    @if (! empty($day['program']))
                        <div class="mt-3 space-y-2">
                            @foreach ($day['program'] as $item)
                                <div class="flex items-start gap-3 text-sm">
                                    <span class="w-24 shrink-0 text-ink-soft tabular-nums">
                                        @if (! empty($item['start'])){{ substr($item['start'], 0, 5) }}@if (! empty($item['end']))–{{ substr($item['end'], 0, 5) }}@endif @else—@endif
                                    </span>
                                    <div class="min-w-0">
                                        <div class="text-ink">
                                            {{ $item['title'] ?? '' }}
                                            @if (! empty($item['type']))
                                                <span class="text-ink-soft">· {{ $programTypes[$item['type']] ?? $item['type'] }}</span>
                                            @endif
                                        </div>
                                        @if (! empty($item['guest']) || ! empty($item['room']))
                                            <div class="text-ink-soft text-xs mt-0.5">
                                                {{ $item['guest'] ?? '' }}@if (! empty($item['guest']) && ! empty($item['room'])) · @endif@if (! empty($item['room'])){{ $item['room'] }}@endif
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @elseif (! $p->isFestival() && $p->dresscode)
        <div class="{{ $card }} mb-4">
            <h3 class="text-sm font-semibold text-ink mb-1">Dresscode</h3>
            <p class="text-sm text-ink-soft">{{ $p->dresscode }}</p>
        </div>
    @endif

    {{-- Invitați (festival) --}}
    @if ($p->isFestival() && ! empty($p->guests))
        <div class="{{ $card }} mb-4">
            <h3 class="text-sm font-semibold text-ink mb-3">Invitați</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach ($p->guests as $g)
                    @php
                        $styleLabel = ! empty($g['style'])
                            ? (($g['style'] === 'other' && ! empty($g['style_other'])) ? $g['style_other'] : ($guestStyles[$g['style']] ?? $g['style']))
                            : null;
                    @endphp
                    <div class="flex items-center gap-3 rounded-xl border border-border p-3">
                        @if (! empty($g['photo_path']))
                            <img src="{{ asset('storage/'.$g['photo_path']) }}" class="w-12 h-12 object-cover rounded-lg border border-border shrink-0">
                        @else
                            <div class="w-12 h-12 rounded-lg border border-dashed border-border bg-bg flex items-center justify-center text-ink-soft/40 shrink-0">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            </div>
                        @endif
                        <div class="min-w-0">
                            <div class="text-sm font-medium text-ink truncate">
                                @if (! empty($g['url']))
                                    <a href="{{ $g['url'] }}" target="_blank" rel="noopener" class="hover:text-primary">{{ $g['name'] ?? '' }}</a>
                                @else
                                    {{ $g['name'] ?? '' }}
                                @endif
                            </div>
                            <div class="text-xs text-ink-soft">
                                {{ $styleLabel }}@if ($styleLabel && ! empty($g['country'])) · @endif@if (! empty($g['country'])){{ $g['country'] }}@endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Bilete --}}
    <div class="{{ $card }} mb-4">
        <h3 class="text-sm font-semibold text-ink mb-2">Bilete</h3>
        @if ($p->is_free)
            <p class="text-sm text-primary font-medium">Intrare gratuită.</p>
        @elseif (! empty($p->ticket_types))
            <div class="space-y-2">
                @foreach ($p->ticket_types as $t)
                    @if (isset($t['price']))
                        <div class="flex items-start justify-between gap-3 py-2 border-b border-border last:border-0">
                            <div class="min-w-0">
                                <div class="text-sm text-ink">{{ $t['name'] ?: 'Intrare' }}</div>
                                @if (! empty($t['discounts']))
                                    <div class="mt-0.5 text-xs text-ink-soft space-y-0.5">
                                        @foreach ($t['discounts'] as $d)
                                            @if (isset($d['price']))
                                                <div>{{ $d['label'] ?: 'Ofertă' }}: {{ $fmt($d['price']) }}@if (! empty($d['until'])) · până la {{ \App\Models\Party::formatUntil($d['until']) }}@endif</div>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            <div class="text-sm font-medium text-ink whitespace-nowrap">{{ $fmt($t['price']) }}</div>
                        </div>
                    @endif
                @endforeach
            </div>
        @else
            <p class="text-sm text-ink-soft">Nespecificat.</p>
        @endif
    </div>

    {{-- Plată --}}
    @if (! empty($p->payment_methods))
        <div class="{{ $card }} mb-4">
            <h3 class="text-sm font-semibold text-ink mb-2">Modalități de plată</h3>
            <div class="flex flex-wrap gap-2">
                @foreach ($paymentLabels as $label)
                    <span class="inline-flex items-center rounded-full bg-bg text-ink-soft text-xs px-2.5 py-1">{{ $label }}</span>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Contacte --}}
    @if (! empty($p->contacts))
        <div class="{{ $card }} mb-4">
            <h3 class="text-sm font-semibold text-ink mb-2">Contact</h3>
            <div class="space-y-2">
                @foreach ($p->contacts as $c)
                    <div class="text-sm">
                        <span class="text-ink font-medium">{{ $c['name'] ?? '' }}</span>@if (! empty($c['phone'])) <span class="text-ink-soft">— {{ $c['phone'] }}</span>@endif
                        @if (! empty($c['note']))<div class="text-xs text-ink-soft">{{ $c['note'] }}</div>@endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Linkuri --}}
    @if (! empty($p->links))
        <div class="{{ $card }} mb-4">
            <h3 class="text-sm font-semibold text-ink mb-3">Linkuri</h3>
            <div class="flex flex-wrap gap-2">
                @foreach ($p->links as $l)
                    <a href="{{ $l['url'] }}" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-1.5 rounded-lg border border-border px-3 py-2 text-sm text-ink hover:border-primary hover:text-primary">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
                        {{ $l['label'] ?: 'Deschide' }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Câmpuri suplimentare --}}
    @if (! empty($p->custom_fields))
        <div class="{{ $card }} mb-4">
            <h3 class="text-sm font-semibold text-ink mb-2">Detalii</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2">
                @foreach ($p->custom_fields as $f)
                    <div class="flex justify-between gap-3 text-sm py-1 border-b border-border/60">
                        <span class="text-ink-soft">{{ $f['label'] ?? '' }}</span>
                        <span class="text-ink text-right">{{ $f['value'] ?? '' }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Descriere --}}
    @if ($p->description)
        <div class="{{ $card }} mb-4">
            <h3 class="text-sm font-semibold text-ink mb-2">Descriere</h3>
            <p class="text-sm text-ink-soft whitespace-pre-line leading-relaxed">{{ $p->description }}</p>
        </div>
    @endif
</div>
