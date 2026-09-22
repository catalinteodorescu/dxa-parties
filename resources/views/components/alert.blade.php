@props([
    'type' => 'success',      // success (verde) | error (rosu) | info (neutru)
    'dismissible' => true,    // buton "X" care scoate alerta din pagina
    'dismissProp' => null,    // proprietate Livewire de resetat la inchidere (alerte care vin din stare, nu din flash)
    'dismissValue' => false,  // valoarea cu care se reseteaza proprietatea (false pt. bool, null pt. ?string)
])

@php
    $styles = [
        'success' => 'bg-success-soft text-success',
        'error'   => 'bg-danger/10 text-danger',
        'info'    => 'bg-bg border border-border text-ink-soft',
    ];

    // La inchidere: (optional) resetam proprietatea din componenta, FARA request imediat
    // (al treilea argument = false; se trimite odata cu urmatorul request), apoi scoatem
    // elementul din DOM. Il scoatem, nu doar il ascundem cu x-show: altfel Livewire ar
    // pastra elementul ascuns si o alerta NOUA cu acelasi text n-ar mai aparea.
    $onClose = ($dismissProp ? '$wire.$set(\''.$dismissProp.'\', '.\Illuminate\Support\Js::from($dismissValue).', false); ' : '')
        .'$root.remove()';
@endphp

<div x-data
     role="{{ $type === 'error' ? 'alert' : 'status' }}"
     {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-lg text-sm px-4 py-3 '.($styles[$type] ?? $styles['success'])]) }}>
    <div class="flex-1 min-w-0">{{ $slot }}</div>

    @if ($dismissible)
        <button type="button" aria-label="Închide" @click="{{ $onClose }}"
                class="shrink-0 -mr-1.5 -mt-1 inline-flex items-center justify-center w-6 h-6 rounded-md opacity-60 hover:opacity-100 hover:bg-black/5 transition">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>
    @endif
</div>
