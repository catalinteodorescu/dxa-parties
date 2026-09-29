{{--
    DXA: adaugat (PWA Recepție). Butoanele cu iconiță din antetul ecranelor: Acasă (căsuța) primul; pe ecranele de vânzare urmează celelalte vânzări. Tranzacții și Raportare se deschid doar din Acasă. Pe ele: doar Acasă.
    Variabilă: $current = entry | tokens | credits | recent | report (ecranul curent nu apare printre butoane).
--}}
@php
    $btn = 'rounded-lg p-2 bg-primary-soft text-primary hover:bg-primary/20 transition-colors';
    $svg = 'w-5 h-5';
@endphp
<div class="shrink-0 flex items-center gap-2">
    <a href="{{ route('receptie.home') }}" wire:navigate aria-label="Acasă" title="Acasă" class="{{ $btn }}">
        <svg class="{{ $svg }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
    </a>
    {{-- Pe Tranzacții și Raportare rămâne doar Acasă --}}
    @unless (in_array($current, ['recent', 'report'], true))
    @if ($current !== 'entry')
        <a href="{{ route('receptie.entry') }}" wire:navigate aria-label="Intrare" title="Intrare" class="{{ $btn }}">
            <svg class="{{ $svg }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/></svg>
        </a>
    @endif
    @if ($current !== 'tokens' && \App\Support\PaymentMethods::tokensSellable())
        <a href="{{ route('receptie.tokens') }}" wire:navigate aria-label="Vânzare tokeni" title="Vânzare tokeni" class="{{ $btn }}">
            <svg class="{{ $svg }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v8"/><path d="M8 12h8"/></svg>
        </a>
    @endif
    @if ($current !== 'credits' && \App\Support\PaymentMethods::creditsPurchasable())
        <a href="{{ route('receptie.credits') }}" wire:navigate aria-label="Vânzare credite" title="Vânzare credite" class="{{ $btn }}">
            <svg class="{{ $svg }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/></svg>
        </a>
    @endif
    @endunless
</div>
