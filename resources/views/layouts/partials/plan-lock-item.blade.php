{{-- DXA: adaugat (runda 79 — planuri). Rând de meniu pentru o funcție blocată de plan: lacăt + pagina „nu e inclusă în plan”. Parametri: `section` (secțiune de permisiuni) SAU `feature` (+ opțional `label`). --}}
@php
    $lockFeature = $feature ?? \App\Support\Features::forSection($section);
    $lockLabel = $label ?? (isset($section) ? (\App\Support\Permissions::SECTIONS[$section][0] ?? $lockFeature) : \App\Support\Features::label($lockFeature));
@endphp
<a href="{{ route('admin.plan-locked', $lockFeature) }}" wire:navigate @click="sidebarOpen = false" data-plan-lock="{{ $lockFeature }}"
   class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-ink-soft/60 hover:bg-bg"
   title="Nu e inclusă în planul tău">
    <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
    <span class="truncate">{{ $lockLabel }}</span>
</a>
