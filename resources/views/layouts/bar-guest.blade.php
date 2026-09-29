{{-- DXA: adaugat (PWA Bar). Layout-ul de login al aplicației de bar (layouts.reception-guest cu identitatea BarApp). --}}
@include('layouts.reception-guest', ['pwa' => \App\Support\BarApp::class])
