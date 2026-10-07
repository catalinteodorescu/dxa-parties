{{-- ============ Secțiune modul: Recepție ============ --}}
{{-- DXA: adaugat (Recepție - raportări) — indicatori din $receptionCards (lista se poate extinde din Dashboard.php). --}}
<section class="mt-8 pt-8 border-t border-border">
    <div class="flex items-center gap-2.5 mb-3">
        <svg class="w-5 h-5 text-ink-soft shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.268 21a2 2 0 0 0 3.464 0"/><path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326"/></svg>
        <h3 class="font-semibold text-ink">Recepție</h3>
        <a href="{{ route('admin.reception.index') }}" wire:navigate class="ml-1 text-sm text-primary hover:underline">Deschide</a>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-6 gap-3">
        @foreach ($receptionCards as $card)
            <x-stat-card :value="$card['value']" :label="$card['label']" :hint="$card['hint']" accent="{{ $card['accent'] }}" :href="$card['href']" wire:key="reception-card-{{ $loop->index }}">
                <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z"/><path d="M14 2v5a1 1 0 0 0 1 1h5"/></svg></x-slot:icon>
            </x-stat-card>
        @endforeach
    </div>
</section>
