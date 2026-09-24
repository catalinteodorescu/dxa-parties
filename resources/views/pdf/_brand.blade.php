{{-- Antet comun al PDF-urilor: logo-ul scolii (varianta pentru fundal deschis). --}}
@php $brandLogo = \App\Support\Branding::logoDataUri('on_light'); @endphp
@if ($brandLogo)
    <div style="margin-bottom: 10px;"><img src="{{ $brandLogo }}" alt="{{ \App\Support\Branding::name() }}" style="height: 34px;"></div>
@endif
