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
    <x-alert type="success" wire:key="flash-status" :class="$attributes->get('class')">{{ $status }}</x-alert>
@endif

@if ($error)
    <x-alert type="error" wire:key="flash-error" :class="$attributes->get('class')">{{ $error }}</x-alert>
@endif
