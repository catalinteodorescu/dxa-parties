{{-- DXA: adaugat (runda 60). Termeni și condiții. Variabile: $version, $html, $needsAcceptance, $accepted. --}}
<div class="pa-stack" style="gap: 1.25rem">
    <div style="text-align: center">
        <h1 class="pa-h1">Termeni și condiții</h1>
        <p class="pa-soft" style="margin: .4rem 0 0; font-size: .85rem">Versiune din {{ $version->created_at->format('d.m.Y') }}</p>
    </div>

    <div class="pa-glass pa-pad" style="max-height: 60vh; overflow-y: auto; -webkit-overflow-scrolling: touch" tabindex="0" data-terms-body>{{ $html }}</div>

    @if ($needsAcceptance)
        <button type="button" class="pa-btn pa-btn-block" wire:click="accept" wire:loading.attr="disabled" wire:target="accept" data-terms-accept>Am citit și accept</button>
    @elseif ($accepted)
        <p class="pa-soft" style="text-align: center; margin: 0; font-size: .85rem" data-terms-done>Ai acceptat acești termeni.</p>
    @endif
</div>
