{{--
    Afiseaza mesajele flash 'status' (succes, verde) si 'error' (rosu), fiecare cu "X".
    Foloseste session()->pull(): mesajul e CONSUMAT la afisare. Fara asta, un flash setat
    intr-o actiune Livewire mai ramane vizibil si la urmatorul request (chiar si dupa ce
    l-ai inchis cu X, ar reaparea).
    Utilizare: <x-flash class="mb-4" />  (clasele se aplica fiecarei alerte)
--}}
@php
    $status = session()->pull('status');
    $error = session()->pull('error');
@endphp

@if ($status)
    {{-- Se aduce în vizor: după „Salvează” de jos mesajul din capul paginii altfel nu se vede. --}}
    <div wire:key="flash-status" x-data x-init="$el.scrollIntoView({ behavior: 'smooth', block: 'center' })"><x-alert type="success" :class="$attributes->get('class')">{{ $status }}</x-alert></div>
@endif

@if ($error)
    <div wire:key="flash-error" x-data x-init="$el.scrollIntoView({ behavior: 'smooth', block: 'center' })"><x-alert type="error" :class="$attributes->get('class')">{{ $error }}</x-alert></div>
@endif
