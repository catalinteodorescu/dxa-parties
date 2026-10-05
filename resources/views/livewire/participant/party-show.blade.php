@php
    use App\Models\Party;
    use App\Support\PartyPublic;

    $image = PartyPublic::imageUrl($party);
    $ended = $state === 'past';
    $days = $party->isFestival() ? ($party->days ?? []) : [];
    $guests = collect($party->guests ?? [])->filter(fn ($g) => trim((string) ($g['name'] ?? '')) !== '');
    $contacts = collect($party->contacts ?? [])->filter(fn ($c) => trim((string) ($c['name'] ?? '')) !== '' || trim((string) ($c['phone'] ?? '')) !== '');
    $fields = collect($party->custom_fields ?? [])->filter(fn ($f) => trim((string) ($f['label'] ?? '')) !== '' && trim((string) ($f['value'] ?? '')) !== '');
    $links = collect($party->links ?? [])->filter(fn ($l) => trim((string) ($l['url'] ?? '')) !== '');
    $styles = collect($party->music_styles ?? [])->filter(fn ($m) => trim((string) ($m['style'] ?? '')) !== '');
    $dresscode = $party->dresscode ?: collect($days)->pluck('dresscode')->filter()->first();
@endphp
<div>
    <div class="pa-hero" style="min-height: 19rem; margin-top: .5rem">
        @if ($image) <img src="{{ $image }}" alt=""> @endif
        <div class="pa-hero-top">
            <a href="{{ route('app.parties') }}" wire:navigate class="pa-btn pa-btn-sm" style="background: rgba(18,8,16,.55); border: 1px solid rgba(255,255,255,.25); box-shadow: none" aria-label="Înapoi la petreceri">←</a>
            @if ($state === 'live') <span class="pa-chip pa-chip-amber">ACUM</span> @elseif ($ended) <span class="pa-chip">ÎNCHEIATĂ</span> @endif
        </div>
        <div class="pa-hero-in">
            <div><span class="pa-chip">{{ $party->isFestival() ? 'FESTIVAL' : 'PETRECERE' }}</span></div>
            <h1 class="pa-h1" style="font-size: 2.2rem">{{ $party->name }}</h1>
        </div>
    </div>

    <section class="pa-section" style="margin-top: 1rem">
        <div class="pa-glass pa-pad pa-stack" style="gap: .9rem">
            <div><div class="pa-label" style="margin: 0">Data</div><div style="font-weight: 800">{{ PartyPublic::dateLabel($party) }}</div></div>
            @if (PartyPublic::timeLabel($party) !== '')
                <div><div class="pa-label" style="margin: 0">{{ $party->isFestival() ? 'Durată' : 'Program' }}</div><div style="font-weight: 800">{{ PartyPublic::timeLabel($party) }}</div></div>
            @endif
            @if ($party->location_name || $party->location_address)
                <div>
                    <div class="pa-label" style="margin: 0">Locație</div>
                    <div style="font-weight: 800">{{ $party->location_name }}</div>
                    @if ($party->location_address) <div class="pa-soft" style="font-size: .9rem">{{ $party->location_address }}</div> @endif
                    @if ($party->location_url) <a href="{{ $party->location_url }}" target="_blank" rel="noopener" class="pa-link" style="font-size: .9rem; display: inline-flex; align-items: center; gap: .3rem">Vezi pe hartă <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg></a> @endif
                </div>
            @endif
            @if ($dresscode)
                <div><div class="pa-label" style="margin: 0">Dresscode</div><div style="font-weight: 800">{{ $dresscode }}</div></div>
            @endif
        </div>
    </section>

    @if ($party->description)
        <section class="pa-section">
            <h2 class="pa-h2">Despre petrecere</h2>
            {{-- Descrierea e restrânsă la ~10 rânduri; butonul apare doar dacă textul e mai lung (runda 30). --}}
            <div x-data="{ open: false, long: false }" x-init="$nextTick(() => { long = $refs.d.scrollHeight > $refs.d.clientHeight + 2 })">
                <div x-ref="d" class="pa-prose pa-soft pa-clamp10" :class="{ 'pa-clamp10': ! open }">{{ $party->description }}</div>
                <button type="button" x-show="long || open" x-cloak class="pa-link" style="margin-top: .5rem; font-size: .9rem; font-weight: 800" @click="open = ! open" x-text="open ? 'Arată mai puțin' : 'Arată toată descrierea'">Arată toată descrierea</button>
            </div>
        </section>
    @endif

    @if ($days)
        <section class="pa-section">
            <h2 class="pa-h2">Programul zilelor</h2>
            @foreach ($days as $day)
                @php $d = ! empty($day['date']) ? \Illuminate\Support\Carbon::parse($day['date'])->locale('ro') : null; @endphp
                <div class="pa-glass pa-pad pa-stack" wire:key="day-{{ $loop->index }}">
                    <div class="pa-between">
                        <b>{{ $d?->translatedFormat('l, j F') }}</b>
                        <span class="pa-soft" style="font-size: .85rem">{{ trim(PartyPublic::hm($day['start_time'] ?? null).' – '.PartyPublic::hm($day['end_time'] ?? null), ' –') }}</span>
                    </div>
                    @foreach (collect($day['program'] ?? [])->filter(fn ($p) => trim((string) ($p['title'] ?? '')) !== '') as $item)
                        <div class="pa-row" style="align-items: flex-start" wire:key="day-{{ $loop->parent->index }}-item-{{ $loop->index }}">
                            <span class="pa-chip pa-chip-amber" style="flex: none">{{ PartyPublic::hm($item['start'] ?? null) ?: '—' }}</span>
                            <div>
                                <div style="font-weight: 800">{{ $item['title'] }}</div>
                                <div class="pa-soft" style="font-size: .85rem">
                                    {{ collect([Party::PROGRAM_TYPES[$item['type'] ?? ''] ?? null, $item['guest'] ?? null, $item['room'] ?? null])->filter()->implode(' · ') }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </section>
    @endif

    @if ($guests->isNotEmpty())
        <section class="pa-section">
            <h2 class="pa-h2">Invitații serii</h2>
            <div class="pa-glass pa-pad" style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem .5rem">
                @foreach ($guests as $g)
                    @php
                        $style = ($g['style'] ?? '') === 'other' ? ($g['style_other'] ?? '') : (Party::GUEST_STYLES[$g['style'] ?? ''] ?? '');
                        $photo = ! empty($g['photo_path']) ? asset('storage/'.$g['photo_path']) : null;
                    @endphp
                    <div style="display: flex; flex-direction: column; align-items: center; gap: .4rem; text-align: center" wire:key="guest-{{ $loop->index }}">
                        @if ($photo)
                            <img src="{{ $photo }}" alt="" class="pa-avatar" loading="lazy">
                        @else
                            <div class="pa-avatar" aria-hidden="true">{{ PartyPublic::initials($g['name']) }}</div>
                        @endif
                        <span style="font-weight: 800; font-size: .85rem; line-height: 1.2">{{ $g['name'] }}</span>
                        <span class="pa-soft" style="font-size: .75rem; line-height: 1.2">{{ collect([$style, $g['country'] ?? null])->filter()->implode(' · ') }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($styles->isNotEmpty())
        <section class="pa-section">
            <h2 class="pa-h2">Stiluri muzicale</h2>
            <div style="display: flex; flex-wrap: wrap; gap: .5rem">
                @foreach ($styles as $m)
                    @php $freq = isset($m['frequency']) && is_numeric($m['frequency']) && (int) $m['frequency'] > 0 ? (int) $m['frequency'] : null; @endphp
                    <span class="pa-chip" wire:key="style-{{ $loop->index }}">{{ ($freq ? $freq.'× ' : '').$m['style'] }}</span>
                @endforeach
            </div>
        </section>
    @endif

    <section class="pa-section">
        <div class="pa-between">
            <h2 class="pa-h2">Tipuri de bilete</h2>
            @if ($tickets && ! $ended) <span class="pa-soft" style="font-size: .75rem; font-weight: 700">se actualizează în timp real</span> @endif
        </div>

        @if ($party->is_free)
            <div class="pa-tier on"><div style="flex: 1; font-weight: 800">Intrare gratuită</div></div>
        @elseif (! $tickets)
            <div class="pa-glass pa-pad pa-soft" style="font-size: .92rem">Prețurile vor fi anunțate în curând.</div>
        @else
            @foreach ($tickets as $t)
                <div class="pa-stack" style="gap: .5rem" wire:key="ticket-{{ $loop->index }}">
                    @if (count($tickets) > 1) <div style="font-weight: 800">{{ $t['name'] }}</div> @endif
                    @foreach ($t['rows'] as $r)
                        <div class="pa-tier {{ $r['on'] ? 'on' : '' }}" wire:key="ticket-{{ $loop->parent->index }}-row-{{ $loop->index }}">
                            <div style="flex: 1; min-width: 0">
                                <div class="pa-row" style="gap: .5rem">
                                    <span style="font-weight: 800">{{ $r['label'] }}</span>
                                    @if ($r['on']) <span class="pa-chip pa-chip-amber" style="height: 1.5rem">ACUM</span> @endif
                                </div>
                                @if ($r['note'] !== '') <div class="pa-soft" style="font-size: .8rem">{{ $r['note'] }}</div> @endif
                            </div>
                            <span style="text-align: right">
                                @if ($r['was'] !== null) <s class="pa-soft pa-was" style="display: block; font-size: .8rem; font-weight: 700">{{ PartyPublic::lei($r['was']) }}</s> @endif
                                <span class="pa-price" style="font-size: 1.15rem; {{ $r['on'] ? '' : 'color: var(--pa-ink)' }}">{{ PartyPublic::lei($r['price']) }}</span>
                            </span>
                        </div>
                    @endforeach
                    @foreach ($t['combos'] ?? [] as $c)
                        <div class="pa-tier" wire:key="ticket-{{ $loop->parent->index }}-combo-{{ $c['key'] }}">
                            <div style="flex: 1; min-width: 0">
                                <div style="font-weight: 800">Combo {{ $c['key'] }}</div>
                                <div class="pa-soft" style="font-size: .8rem">Plătești {{ $c['buy'] }}, primești {{ $c['size'] }} bilete{{ $c['free'] === 1 ? ' (1 gratis)' : ' ('.$c['free'].' gratis)' }}</div>
                                @if ($c['note'] ?? null) <div style="font-size: .78rem; font-weight: 700">{{ $c['note'] }}</div> @endif
                            </div>
                            <span class="pa-chip pa-chip-amber">{{ $c['size'] }} bilete</span>
                        </div>
                    @endforeach
                </div>
            @endforeach
        @endif

        @if (! $ended)
            @if ($saleBlock === null)
                @include('livewire.participant._buy', ['party' => $party, 'me' => $me, 'options' => $options, 'maxQty' => $maxQty, 'quote' => $quote, 'quoteError' => $quoteError, 'combos' => $combos, 'count' => $count])
            @else
                <div class="pa-glass pa-pad pa-soft" style="font-size: .92rem">{{ $party->online_sales ? $saleBlock : 'Biletele se cumpără la intrare.' }}</div>
            @endif
        @endif
    </section>

    @if ($contacts->isNotEmpty() || $fields->isNotEmpty() || $links->isNotEmpty())
        <section class="pa-section">
            <h2 class="pa-h2">Informații utile</h2>
            <div class="pa-glass pa-pad pa-stack" style="gap: .9rem">
                @foreach ($fields as $f)
                    <div wire:key="field-{{ $loop->index }}"><div class="pa-label" style="margin: 0">{{ $f['label'] }}</div><div style="font-weight: 800">{{ $f['value'] }}</div></div>
                @endforeach
                @if ($contacts->isNotEmpty())
                    <div>
                        <div class="pa-label" style="margin: 0">Contact</div>
                        @foreach ($contacts as $c)
                            <div style="font-weight: 800" wire:key="contact-{{ $loop->index }}">
                                {{ $c['name'] ?? '' }}
                                @if (! empty($c['phone'])) <a href="tel:{{ preg_replace('/[^\d+]/', '', $c['phone']) }}" class="pa-link">{{ $c['phone'] }}</a> @endif
                                @if (! empty($c['note'])) <span class="pa-soft" style="font-size: .8rem; font-weight: 600">· {{ $c['note'] }}</span> @endif
                            </div>
                        @endforeach
                    </div>
                @endif
                @foreach ($links as $l)
                    <div wire:key="link-{{ $loop->index }}"><a href="{{ $l['url'] }}" target="_blank" rel="noopener" class="pa-link" style="display: inline-flex; align-items: center; gap: .4rem"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.07 0l3-3a5 5 0 0 0-7.07-7.07l-1.5 1.5"/><path d="M14 11a5 5 0 0 0-7.07 0l-3 3a5 5 0 0 0 7.07 7.07l1.5-1.5"/></svg>{{ $l['label'] ?: $l['url'] }}</a></div>
                @endforeach
            </div>
        </section>
    @endif
</div>
