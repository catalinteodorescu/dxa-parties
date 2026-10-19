{{-- DXA: adaugat (runda 79 — planuri). Funcția nu e inclusă în planul instalării: pagina „disponibil în …”, în layout-ul panoului. --}}
@php
    $label = \App\Support\Features::label($feature);
    $plans = \App\Services\Plan\PlanGate::plansWith($feature);
@endphp
@component('layouts.admin')
    <div class="max-w-md mx-auto mt-10 bg-surface border border-border rounded-2xl p-6 shadow-sm text-center" data-plan-locked>
        <div class="mx-auto w-11 h-11 rounded-full bg-primary-soft text-primary flex items-center justify-center">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        </div>
        <h1 class="mt-3 text-xl font-semibold text-ink">„{{ $label }}” nu e inclusă în planul tău</h1>
        <p class="mt-2 text-sm text-ink-soft">
            Planul actual este {{ \App\Services\Plan\PlanGate::planName() }}.
            @if ($plans)
                Funcția este disponibilă în {{ implode(', ', $plans) }}. Pentru a o activa, contactează furnizorul aplicației.
            @else
                Pentru a o activa, contactează furnizorul aplicației.
            @endif
        </p>
        <a href="{{ route('admin.dashboard') }}" class="mt-5 inline-block rounded-lg bg-primary hover:bg-primary-hover text-white text-sm font-medium px-4 py-2.5 transition-colors">Înapoi la dashboard</a>
    </div>
@endcomponent
