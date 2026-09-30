{{-- DXA: adaugat (runda 16). Rând restrâns de bilet (ca la intrări). Variabilă: $t. --}}
<div class="pa-between pa-line" style="padding-top: .5rem; font-size: .9rem">
    <div style="min-width: 0">
        <div style="font-weight: 700">{{ $t->party?->name ?? 'Petrecere' }}</div>
        <div class="pa-soft" style="font-size: .8rem">{{ $t->ticket_type }} · {{ $t->party ? \App\Support\PartyPublic::dateLabel($t->party) : '' }}@if ($t->holder) · {{ $t->holder->name }} @endif</div>
    </div>
    <div style="text-align: right; white-space: nowrap">
        <div style="font-weight: 800">{{ (float) $t->price > 0 ? number_format((float) $t->price, 2, ',', '.').' lei' : 'gratis' }}</div>
        <div class="pa-soft" style="font-size: .75rem">{{ ['valid' => 'valabil', 'used' => 'folosit', 'void' => 'anulat'][$t->status] ?? $t->status }}</div>
    </div>
</div>
