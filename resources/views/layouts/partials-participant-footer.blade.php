{{-- DXA: adaugat (runda 23). Subsolul aplicației: contact (telefon, email, rețele, adresă) și linkuri legale, din Setări › Aplicație participanți.
     $legalOnly = doar linkurile legale (login / înregistrare). Nu afișează nimic dacă nu e completat nimic. --}}
@php
    $contacts = ($legalOnly ?? false) ? [] : \App\Support\ParticipantAppSettings::contacts();
    $legal = \App\Support\ParticipantAppSettings::legalLinks();
    $terms = \App\Services\Terms::enabled();   // runda 60: Termenii din aplicație apar ca buton (chip), ca adresa și celelalte linkuri
@endphp
@if ($contacts || $legal || $terms)
    <footer class="pa-foot" data-app-footer>
        @if ($contacts)
            <div class="pa-label" style="margin: 0 0 .5rem">Contact</div>
            <div class="pa-foot-items">
                @foreach ($contacts as $c)
                    @if ($c['href'] !== '')
                        <a href="{{ $c['href'] }}" @if (str_starts_with($c['href'], 'http')) target="_blank" rel="noopener" @endif class="pa-chip pa-chip-link">{{ $c['text'] }}</a>
                    @else
                        <span class="pa-chip">{{ $c['text'] }}</span>
                    @endif
                @endforeach
            </div>
        @endif
        @if ($terms)
            <div class="pa-foot-items" @if ($contacts) style="margin-top: .6rem" @endif>
                <a href="{{ route('app.terms') }}" wire:navigate class="pa-chip pa-chip-link" data-footer-terms>Termeni și condiții</a>
            </div>
        @endif
        @if ($legal)
            <div class="pa-foot-legal">
                @foreach ($legal as $l)
                    <a href="{{ $l['href'] }}" target="_blank" rel="noopener" class="pa-link pa-soft">{{ $l['label'] }}</a>
                @endforeach
            </div>
        @endif
    </footer>
@endif
