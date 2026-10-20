<div>
    @php
        $states = [
            'live'      => ['Activ',      'bg-primary-soft text-primary'],
            'scheduled' => ['Programat',  'bg-warning/10 text-warning'],
            'expired'   => ['Expirat',    'bg-ink/5 text-ink-soft'],
            'inactive'  => ['Dezactivat', 'bg-surface border border-border text-ink-soft'],
            'draft'     => ['Ciornă',     'bg-info-soft text-info'],
        ];
        // DXA: adaugat (Petreceri) — stari pentru pill-urile din widget
        $partyStates = [
            'live'     => ['Acum',        'bg-primary-soft text-primary'],
            'upcoming' => ['Viitoare',    'bg-warning/10 text-warning'],
            'past'     => ['Trecută',     'bg-ink/5 text-ink-soft'],
            'inactive' => ['Dezactivată', 'bg-surface border border-border text-ink-soft'],
            'draft'    => ['Ciornă',      'bg-info-soft text-info'],
        ];

        // DXA: adaugat (Bar - raportari) — stari pentru pill-ul din widget-ul de raportari
        $reportStates = [
            'draft'     => ['Draft',      'bg-primary-soft text-primary'],
            'finalized' => ['Finalizat',  'bg-info-soft text-info'],
        ];
        $money = fn ($n) => number_format((float) $n, 2, ',', '.');
        // DXA: adaugat (Bar - stocuri, alertă stoc negativ persistent) — cantitate fara zerouri de prisos.
        $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, ',', '.'), '0'), ',');
    @endphp

    <div>
        <h2 class="text-lg font-semibold text-ink">Bun venit, {{ $currentAdmin->name }}.</h2>
        <p class="mt-1 text-sm text-ink-soft">Privire de ansamblu asupra activității.</p>
    </div>

    {{-- Secțiunile se afișează în ordinea și cu activarea din Setări › Secțiuni dashboard (App\Support\DashboardSections).
         O secțiune nouă = un fișier `dashboard/_<cheie>.blade.php` + o intrare în DashboardSections::DEFINITIONS (apare în Setări, activă implicit). --}}
    @foreach ($dashboardSections as $sectionKey)
        @include('livewire.admin.dashboard._'.$sectionKey)
    @endforeach

    {{-- Secțiune nouă: vezi nota de mai sus. --}}
</div>
