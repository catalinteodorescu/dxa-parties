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
        $paymentMethods = \App\Models\Party::PAYMENT_METHODS;
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
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            Înapoi la listă
        </a>
        <x-btn variant="warning" size="sm" outline :href="route('admin.parties.edit', $p)" wire:navigate>
            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
            Editează
        </x-btn>
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
                <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="2" x2="12" y2="4.5"/><circle cx="12" cy="13" r="7.5"/><line x1="12" y1="5.5" x2="12" y2="20.5"/><line x1="4.5" y1="13" x2="19.5" y2="13"/><path d="M6.2 9c3.6 1.6 8 1.6 11.6 0"/><path d="M6.2 17c3.6-1.6 8-1.6 11.6 0"/><path d="M9 5.8c-1.6 4.6-1.6 9.8 0 14.4"/><path d="M15 5.8c1.6 4.6 1.6 9.8 0 14.4"/></svg>
                @if ($p->isFestival() && $p->end_date && $p->start_date && $p->end_date->ne($p->start_date))
                    {{ $p->start_date->translatedFormat('d.m.Y') }} – {{ $p->end_date->format('d.m.Y') }}
                @elseif ($p->starts_at)
                    {{ $zile[$p->starts_at->dayOfWeek] }}, {{ $p->starts_at->format('d.m.Y') }}@if (! $p->isFestival() && $p->starts_at), {{ $p->starts_at->format('H:i') }}@if ($p->ends_at)–{{ $p->ends_at->format('H:i') }}@endif @endif
                @endif
            </span>
            @if ($priceSummary)
                <span class="inline-flex items-center gap-1.5 {{ $priceSummary === 'Gratuit' ? 'text-primary font-medium' : '' }}">
                    <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    {{ $priceSummary }}
                </span>
            @endif
        </div>

        @if ($p->location_name)
            <div class="mt-2 flex items-center gap-1.5 text-sm text-ink-soft">
                <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 12-9 12s-9-5-9-12a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                <span>{{ $p->location_name }}@if ($p->location_address) · {{ $p->location_address }}@endif</span>
                @if ($p->location_url)
                    <a href="{{ $p->location_url }}" target="_blank" rel="noopener" class="text-primary hover:underline shrink-0">hartă</a>
                @endif
            </div>
        @endif
    </div>

    {{-- Statistici (demo) --}}
    <div class="{{ $card }} mb-4">
        <h3 class="text-sm font-semibold text-ink mb-2">Statistici <span class="text-ink-soft/60 font-normal">(demo)</span></h3>
        <div class="flex flex-wrap gap-6">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-ink-soft/60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <div>
                    <div class="text-lg font-semibold text-ink leading-tight">{{ number_format($p->demoViews(), 0, ',', '.') }}</div>
                    <div class="text-xs text-ink-soft">Afișări</div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-ink-soft/60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3l7 7-3 1 2 5-2 1-2-5-3 2z"/></svg>
                <div>
                    <div class="text-lg font-semibold text-ink leading-tight">{{ number_format($p->demoClicks(), 0, ',', '.') }}</div>
                    <div class="text-xs text-ink-soft">Click-uri</div>
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
                        <svg class="w-3 h-3 text-ink-soft/50 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    @endif
                @endforeach
                <svg class="w-3 h-3 text-ink-soft/50 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
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
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c0-3.5 3-6 7-6s7 2.5 7 6"/></svg>
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
                                                <div>{{ $d['label'] ?: 'Ofertă' }}: {{ $fmt($d['price']) }}@if (! empty($d['until'])) · până la {{ \Illuminate\Support\Carbon::parse($d['until'])->format('d.m.Y') }}@endif</div>
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
                @foreach ($p->payment_methods as $m)
                    <span class="inline-flex items-center rounded-full bg-bg text-ink-soft text-xs px-2.5 py-1">{{ $paymentMethods[$m] ?? $m }}</span>
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
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/></svg>
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
