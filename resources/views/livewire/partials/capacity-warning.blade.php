{{-- DXA: adaugat (runda 13). Atenționare de capacitate pentru Recepție: apare când numărul de intrări valabile atinge „Număr maxim de participanți”
     al petrecerii. Doar informativă (nu blochează intrarea). Variabilă: $party (Party). --}}
@php $cap = $party?->capacityStatus(); @endphp
@if ($cap && $cap->reached)
    <div role="alert" class="rounded-xl border border-warning bg-surface px-4 py-3 text-sm font-semibold text-warning">
        {{ $cap->over ? 'Capacitatea a fost depășită' : 'Capacitatea a fost atinsă' }}: {{ $cap->count }} intrați din maximum {{ $cap->max }} participanți.
        <span class="block text-xs font-normal">Poți continua să înregistrezi intrări; e doar o atenționare.</span>
    </div>
@endif
