{{-- DXA: adaugat (runda 46 — permisiuni). 403 în layout-ul panoului: meniul rămâne, utilizatorul merge unde are acces. --}}
@component('layouts.admin')
    <div class="max-w-md mx-auto mt-10 bg-surface border border-border rounded-2xl p-6 shadow-sm text-center" data-no-permission>
        <h1 class="text-xl font-semibold text-ink">Nu ai permisiunea necesară</h1>
        <p class="mt-2 text-sm text-ink-soft">
            Contul tău nu are acces la „{{ $section }}”. Dacă ai nevoie de acces, cere-l unui superadmin.
        </p>
        <a href="{{ route('admin.dashboard') }}" class="mt-5 inline-block rounded-lg bg-primary hover:bg-primary-hover text-white text-sm font-medium px-4 py-2.5 transition-colors">Înapoi la dashboard</a>
    </div>
@endcomponent
