{{-- ============ Secțiune modul: Anunțuri ============ --}}
<section class="mt-8 pt-8 border-t border-border">
    <div class="flex items-center gap-2.5 mb-3">
        <svg class="w-5 h-5 text-ink-soft shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 6a13 13 0 0 0 8.4-2.8A1 1 0 0 1 21 4v12a1 1 0 0 1-1.6.8A13 13 0 0 0 11 14H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2z"/><path d="M6 14a12 12 0 0 0 2.4 7.2 2 2 0 0 0 3.2-2.4A8 8 0 0 1 10 14"/><path d="M8 6v8"/></svg>
        <h3 class="font-semibold text-ink">Anunțuri</h3>
        <a href="{{ route('admin.announcements.index') }}" wire:navigate class="ml-1 text-sm text-primary hover:underline">Vezi toate</a>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-6 gap-3">
        <x-stat-card :value="$liveCount" label="Active acum" hint="publicate, în interval" accent="primary" :href="route('admin.announcements.index', ['state' => 'live'])">
            <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg></x-slot:icon>
        </x-stat-card>

        <x-stat-card :value="$scheduledCount" label="Programate" hint="încă neîncepute" accent="warning" :href="route('admin.announcements.index', ['state' => 'scheduled'])">
            <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></x-slot:icon>
        </x-stat-card>

        <x-stat-card :value="$draftCount" label="Ciorne" hint="nepublicate" accent="info" :href="route('admin.announcements.index', ['state' => 'draft'])">
            <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z"/><path d="M14 2v5a1 1 0 0 0 1 1h5"/></svg></x-slot:icon>
        </x-stat-card>

        @if ($latest)
            <x-stat-card :value="number_format($latest->statViews(), 0, ',', '.')" label="Afișări" :hint="$latest->title" accent="purple" :href="route('admin.announcements.edit', $latest)">
                <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 7h6v6"/><path d="m22 7-8.5 8.5-5-5L2 17"/></svg></x-slot:icon>
            </x-stat-card>
        @else
            <x-stat-card value="—" label="Afișări" hint="niciun anunț" accent="purple">
                <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 7h6v6"/><path d="m22 7-8.5 8.5-5-5L2 17"/></svg></x-slot:icon>
            </x-stat-card>
        @endif

        <x-action-card label="Anunț nou" accent="primary" :href="route('admin.announcements.create')">
            <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg></x-slot:icon>
        </x-action-card>
    </div>

    {{-- Widget: ultimele anunțuri (până la 4), lat cât 2 carduri --}}
    <div class="mt-3 grid grid-cols-1 lg:grid-cols-6 gap-3">
        <div class="lg:col-span-2 rounded-xl border border-border bg-surface p-4">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-ink-soft/80 mb-1">Ultimele anunțuri</div>
            @forelse ($latestList as $a)
                @php [$sl, $sc] = $states[$a->state()]; @endphp
                <a href="{{ route('admin.announcements.edit', $a) }}" wire:navigate
                   class="group flex items-center justify-between gap-3 py-2 border-b border-border last:border-0">
                    <span class="text-sm text-ink group-hover:text-primary truncate">{{ $a->title }}</span>
                    <span class="flex items-center gap-2 shrink-0">
                        <span class="text-xs text-ink-soft whitespace-nowrap">
                            @if ($a->starts_at && $a->ends_at)
                                {{ $a->starts_at->format('d.m.y') }}–{{ $a->ends_at->format('d.m.y') }}
                            @elseif ($a->starts_at)
                                din {{ $a->starts_at->format('d.m.y') }}
                            @else
                                —
                            @endif
                        </span>
                        <span class="inline-flex items-center rounded-full text-[11px] font-medium px-2 py-0.5 {{ $sc }}">{{ $sl }}</span>
                    </span>
                </a>
            @empty
                <p class="text-sm text-ink-soft mt-1">Niciun anunț încă.</p>
            @endforelse
        </div>
    </div>
</section>
