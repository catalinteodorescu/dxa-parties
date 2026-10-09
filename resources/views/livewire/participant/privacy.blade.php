{{-- DXA: adaugat (runda 68). Politica de confidențialitate (informare, fără acceptare). Variabile: $version, $html. --}}
<div class="pa-stack" style="gap: 1.25rem">
    <div style="text-align: center">
        <h1 class="pa-h1">Politica de confidențialitate</h1>
        <p class="pa-soft" style="margin: .4rem 0 0; font-size: .85rem">Versiune din {{ $version->created_at->format('d.m.Y') }}</p>
    </div>

    <div class="pa-glass pa-pad" style="max-height: 60vh; overflow-y: auto; -webkit-overflow-scrolling: touch" tabindex="0" data-privacy-body>{{ $html }}</div>
</div>
