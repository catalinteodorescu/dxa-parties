{{-- DXA: adaugat (runda 23), refăcut în runda 69. Subsolul aplicației, pe roluri: Contact (telefon, email, site, adresă), Social (Instagram, Facebook), Legal (Termeni, Confidențialitate).
     Linkuri discrete, albe, subțiri, fără butoane; fiecare rol e un rând cu eticheta mică în stânga. Datele vin din Setări › Aplicație participanți, Setări generale și pagina Legal.
     $legalOnly = doar rândul Legal (login / înregistrare). Nu afișează nimic dacă nu e completat nimic. --}}
@php
    $all = collect(($legalOnly ?? false) ? [] : \App\Support\ParticipantAppSettings::contacts());
    $legal = [];
    if (\App\Services\Terms::enabled()) {
        $legal[] = ['text' => 'Termeni și condiții', 'href' => route('app.terms'), 'attr' => 'data-footer-terms'];
    }
    if (\App\Services\PrivacyPolicy::enabled()) {
        $legal[] = ['text' => 'Politica de confidențialitate', 'href' => route('app.privacy'), 'attr' => 'data-footer-privacy'];
    }
    $rows = array_filter([
        'Contact' => $all->where('group', 'contact')->values()->all(),
        'Social' => $all->where('group', 'social')->values()->all(),
        'Legal' => $legal,
    ]);
@endphp
@if ($rows)
    <footer class="pa-foot" data-app-footer>
        @foreach ($rows as $title => $links)
            <div class="pa-foot-row" data-foot-group="{{ strtolower($title) }}">
                <div class="pa-foot-h">{{ $title }}</div>
                <div class="pa-foot-links">
                    @foreach ($links as $l)
                        @if (($l['href'] ?? '') !== '')
                            <a href="{{ $l['href'] }}" @if (str_starts_with($l['href'], 'http')) target="_blank" rel="noopener" @else wire:navigate @endif class="pa-foot-link" {{ $l['attr'] ?? '' }}>{{ $l['text'] }}</a>
                        @else
                            <span class="pa-foot-link pa-foot-plain">{{ $l['text'] }}</span>
                        @endif
                    @endforeach
                </div>
            </div>
        @endforeach
    </footer>
@endif
