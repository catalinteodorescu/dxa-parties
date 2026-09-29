{{-- DXA: adaugat (PWA Bar). Layout-ul aplicației de bar: același schelet ca la recepție (layouts.reception), cu identitatea BarApp. --}}
@include('layouts.reception', ['pwa' => \App\Support\BarApp::class])
