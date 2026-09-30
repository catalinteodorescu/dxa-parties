{{-- DXA: adaugat (runda 18). Lazy load: când santinela intră în ecran, componenta încarcă următoarea tranșă (metoda more()).
     Cheia depinde de $limit, ca elementul să fie observat din nou după fiecare încărcare. Variabile: $limit. --}}
<div wire:key="more-{{ $limit }}" wire:intersect="more" class="pa-soft" style="text-align: center; font-size: .8rem; padding: .5rem 0" aria-live="polite">Se încarcă…</div>
