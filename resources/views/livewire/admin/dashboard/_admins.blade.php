{{-- ============ Secțiune modul: Administratori (doar superadmin) ============ --}}
@if ($currentAdmin->permitsAny('users', 'logs'))
    <section class="mt-8 pt-8 border-t border-border">
        <div class="flex items-center gap-2.5 mb-3">
            <svg class="w-5 h-5 text-ink-soft shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 21a8 8 0 0 0-16 0"/><circle cx="10" cy="8" r="5"/><path d="M22 20c0-3.37-2-6.5-4-8a5 5 0 0 0-.45-8.3"/></svg>
            <h3 class="font-semibold text-ink">Administratori</h3>
            <a href="{{ route('admin.users.index') }}" wire:navigate class="ml-1 text-sm text-primary hover:underline">Vezi toți</a>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-6 gap-3">
            @if ($currentAdmin->permits('users'))
            <x-stat-card :value="$adminsActive" label="Activi" hint="din {{ $adminsTotal }} în total" accent="neutral" :href="route('admin.users.index')">
                <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></x-slot:icon>
            </x-stat-card>

            @endif

            @if ($currentAdmin->permits('users', 'edit'))
            <x-action-card label="Admin nou" accent="primary" :href="route('admin.users.create')">
                <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg></x-slot:icon>
            </x-action-card>

            @endif

            @if ($currentAdmin->permits('logs'))
            <x-action-card label="Jurnal" accent="neutral" :href="route('admin.logs.index')">
                <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></x-slot:icon>
            </x-action-card>
            @endif
        </div>
    </section>
@endif
