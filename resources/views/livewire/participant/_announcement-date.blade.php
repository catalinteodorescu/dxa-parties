{{-- DXA: adaugat (runda 35). Data și ora publicării unui anunț. Variabilă: $announcement. --}}
@if ($announcement->publishedAt())
    <div class="pa-soft" style="font-size: .8rem; font-weight: 700">{{ $announcement->publishedAt()->copy()->locale('ro')->translatedFormat('j M Y, H:i') }}</div>
@endif
