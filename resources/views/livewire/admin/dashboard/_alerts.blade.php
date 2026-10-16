{{-- ============ Secțiune: Atenție ============ --}}
{{-- DXA: adaugat (runda 75). Apare doar când e ceva de rezolvat; fiecare alertă duce direct la pagina potrivită (App\Services\DashboardAlerts). --}}
@if ($alerts->isNotEmpty())
    <section class="mt-6" data-dashboard-alerts>
        <div class="flex items-center gap-2.5 mb-3">
            <svg class="w-5 h-5 text-warning shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
            <h3 class="font-semibold text-ink">Atenție</h3>
            <span class="text-sm text-ink-soft">{{ $alerts->count() }} {{ $alerts->count() === 1 ? 'lucru' : 'lucruri' }} de verificat</span>
        </div>

        {{-- Carduri duble (cât 2 KPI) în aceeași grilă ca restul dashboardului. --}}
        <div class="grid grid-cols-2 lg:grid-cols-6 gap-3 items-start">
            @foreach ($alerts as $alert)
                @php($danger = $alert['level'] === 'danger')
                <div x-data="{ open: false }" data-alert="{{ $alert['key'] }}"
                     class="col-span-2 rounded-xl border bg-surface p-3 {{ $danger ? 'border-danger/40' : 'border-border' }}">
                    <div class="flex items-start gap-3">
                        <span class="shrink-0 grid place-items-center min-w-9 h-9 px-2 rounded-lg text-base font-semibold {{ $danger ? 'bg-danger/10 text-danger' : 'bg-warning/10 text-warning' }}">{{ $alert['count'] }}</span>
                        <div class="min-w-0 flex-1">
                            <a href="{{ $alert['href'] }}" wire:navigate class="block text-sm font-medium text-ink hover:text-primary leading-snug">{{ $alert['title'] }}</a>
                            <span class="mt-0.5 block text-xs text-ink-soft leading-snug">{{ $alert['detail'] }}</span>
                        </div>
                    </div>
                    <div class="mt-2.5 flex items-center justify-between gap-2">
                        <button type="button" @click="open = ! open" :aria-expanded="open" data-alert-help
                                class="rounded-lg border border-border px-2.5 py-1 text-xs font-medium text-ink-soft hover:text-primary hover:border-primary/40 transition-colors">
                            <span x-text="open ? 'Ascunde' : 'Ce înseamnă?'">Ce înseamnă?</span>
                        </button>
                        <a href="{{ $alert['href'] }}" wire:navigate class="text-xs font-medium text-primary hover:underline">Deschide →</a>
                    </div>
                    <p x-show="open" x-cloak class="mt-2.5 rounded-lg bg-bg px-3 py-2 text-xs leading-relaxed text-ink-soft">{{ $alert['help'] }}</p>
                </div>
            @endforeach
        </div>
    </section>
@endif
