{{-- ============ Secțiune modul: Participanți ============ --}}
{{-- DXA: adaugat (Participanți - dashboard). Tablou scurt: participanți, tokeni în circulație, carduri de
     fidelitate active (doar cât fidelitatea e activă global) — fiecare cu link direct la pagina lui. --}}
<section class="mt-8 pt-8 border-t border-border">
    <div class="flex items-center gap-2.5 mb-3">
        <svg class="w-5 h-5 text-ink-soft shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><path d="M16 3.128a4 4 0 0 1 0 7.744"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><circle cx="9" cy="7" r="4"/></svg>
        <h3 class="font-semibold text-ink">Participanți</h3>
        <a href="{{ route('admin.participants.index') }}" wire:navigate class="ml-1 text-sm text-primary hover:underline">Vezi toți</a>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-6 gap-3">
        <x-stat-card :value="number_format($participantsTotal, 0, ',', '.')" label="Participanți" hint="identificați" accent="primary" :href="route('admin.participants.index')">
            <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></x-slot:icon>
        </x-stat-card>

        <x-stat-card :value="number_format($participantsNewThisMonth, 0, ',', '.')" label="Noi luna asta" hint="participanți" accent="info" :href="route('admin.participants.stats')">
            <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg></x-slot:icon>
        </x-stat-card>

        <x-stat-card :value="number_format($tokensCirculation, 0, ',', '.')" label="Tokeni în circulație" hint="vânduți − încasați" accent="warning" :href="route('admin.reception.tokens')">
            <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/></svg></x-slot:icon>
        </x-stat-card>

        {{-- DXA: adaugat (Credite): sold total în circulație, cu link la pagina Credite. --}}
        @if ($creditsVisible)
            <x-stat-card :value="number_format($creditsOutstanding, 2, ',', '.').' lei'" label="Credite în circulație" hint="sold total participanți" accent="success" :href="route('admin.credits.index')">
                <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/></svg></x-slot:icon>
            </x-stat-card>
        @endif

        @if ($loyaltyOn)
            <x-stat-card :value="number_format($loyaltyActiveCards, 0, ',', '.')" label="Carduri active" hint="fidelitate" accent="purple" :href="route('admin.loyalty.cards')">
                <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg></x-slot:icon>
            </x-stat-card>
        @endif

        <x-action-card label="Statistici" accent="neutral" :href="route('admin.participants.stats')">
            <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg></x-slot:icon>
        </x-action-card>
    </div>
</section>
