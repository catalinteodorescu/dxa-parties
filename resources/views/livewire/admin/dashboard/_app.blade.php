{{-- ============ Secțiune: Aplicație ============ --}}
{{-- DXA: adaugat (runda 75). Cifre despre aplicația participanților (App\Services\AppDashboard). --}}
@if ($appStats && $currentAdmin->permits('participants'))
    @php($a = $appStats)
    <section class="mt-8 pt-8 border-t border-border" data-dashboard-app>
        <div class="flex items-center gap-2.5 mb-3">
            <svg class="w-5 h-5 text-ink-soft shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/></svg>
            <h3 class="font-semibold text-ink">Aplicație</h3>
            <a href="{{ route('admin.participants.index') }}" wire:navigate class="ml-1 text-sm text-primary hover:underline">Participanți</a>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-6 gap-3">
            <x-stat-card :value="number_format($a->accounts, 0, ',', '.')" label="Conturi" :hint="$a->new30.' noi în ultimele 30 de zile'" accent="primary" :href="route('admin.participants.index')" />
            <x-stat-card :value="number_format($a->active7, 0, ',', '.')" label="Activi, 7 zile" :hint="$a->active30.' în 30 de zile · de la activarea măsurătorii'" accent="info" />
            <x-stat-card :value="number_format($a->push_people, 0, ',', '.')" label="Notificări pornite" :hint="($a->push_pct !== null ? $a->push_pct.'% din conturi · ' : '').$a->push7.' trimise în 7 zile'" accent="purple" />
            <x-stat-card :value="number_format($a->visits7, 0, ',', '.')" label="Vizite, 7 zile" :hint="number_format($a->visits30, 0, ',', '.').' în 30 de zile (vizitatori unici pe zi)'" accent="neutral" />
            <x-stat-card :value="number_format($a->standalone7, 0, ',', '.')" label="Din aplicația instalată" hint="deschideri unice, ultimele 7 zile" accent="success" />
            <x-stat-card :value="number_format($a->installs30, 0, ',', '.')" label="Instalări, 30 de zile" :hint="'Android; '.number_format($a->installs_all, 0, ',', '.').' în total · iOS nu raportează'" accent="warning" />
        </div>
    </section>
@endif
