{{-- DXA: adaugat (runda 53). Biletele rămase după ștergerea contului. Variabilă: $tickets. --}}
<div class="pa-stack" style="gap: 1.25rem">
    <div style="text-align: center">
        <h1 class="pa-h1">Biletele tale</h1>
        <p class="pa-soft" style="margin: .4rem 0 0">Contul tău a fost șters, dar biletele de mai jos rămân valabile. Arată codul la intrare și salvează pagina sau fă o captură de ecran: oricine are linkul poate folosi biletele.</p>
    </div>

    @forelse ($tickets as $t)
        <div wire:key="dt-{{ $t->id }}">@include('livewire.participant._ticket-card', ['t' => $t, 'public' => true])</div>
    @empty
        <div class="pa-glass pa-pad pa-stack" style="text-align: center" data-no-tickets>
            <div style="font-weight: 800">Nu mai sunt bilete valabile</div>
            <div class="pa-soft" style="font-size: .9rem">Biletele au fost folosite, anulate sau petrecerea s-a încheiat.</div>
        </div>
    @endforelse
</div>
