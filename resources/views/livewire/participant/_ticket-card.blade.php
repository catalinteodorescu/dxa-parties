{{-- DXA: adaugat (runda 16). Card de bilet valabil, cu QR propriu (DXA:T:<uuid>). Variabilă: $t (Ticket cu party și holder). --}}
<div class="pa-glass pa-pad pa-stack" style="gap: .75rem; align-items: center; text-align: center; height: 100%; box-sizing: border-box">
    <div style="width: 100%; display: flex; align-items: flex-start; justify-content: space-between; gap: .5rem; text-align: left">
        <div style="min-width: 0">
            <div style="font-weight: 800">{{ $t->party?->name }}</div>
            <div class="pa-soft" style="font-size: .85rem">{{ $t->party ? \App\Support\PartyPublic::dateLabel($t->party) : '' }} · {{ $t->ticket_type }}</div>
        </div>
        <span class="pa-chip pa-chip-amber" style="flex: none">VALABIL</span>
    </div>
    <div wire:ignore style="display: flex; justify-content: center"
         x-data
         x-init="(async () => {
             try {
                 window.__paQr ??= new Promise((res, rej) => { if (window.qrcode) return res(); const s = document.createElement('script'); s.src = @js(asset('vendor/qrcode-generator.js')); s.onload = res; s.onerror = rej; document.head.appendChild(s); });
                 await window.__paQr;
                 const q = qrcode(0, 'M'); q.addData(@js($t->qrPayload())); q.make();
                 $refs.qr.innerHTML = q.createSvgTag({ cellSize: 6, margin: 0, scalable: true });
             } catch (e) {}
         })()">
        <div x-ref="qr" data-ticket-qr="{{ $t->qrPayload() }}" style="width: 200px; height: 200px; background: #fff; padding: 10px; border-radius: .9rem"></div>
    </div>
    <div style="font-size: .9rem">
        @if ($t->holder)
            <span style="font-weight: 800">{{ $t->holder->name }}</span>
        @else
            <span class="pa-soft">Fără nume{{ $t->holder_phone ? ' · '.$t->holder_phone : '' }}</span>
        @endif
        <span class="pa-soft"> · {{ (float) $t->price > 0 ? number_format((float) $t->price, 2, ',', '.').' lei, de plătit la intrare' : 'gratuit' }}</span>
    </div>
    @if ($t->owner_participant_id !== auth('participant')->id() && $t->owner)
        <div class="pa-soft" style="font-size: .85rem">Trimis în contul lui {{ $t->owner->name }}.</div>
    @endif
    @if ($t->valid_until)
        <div class="pa-soft" style="font-size: .85rem; {{ $t->isExpired() ? 'color: #ffb4a8; font-weight: 700' : '' }}">
            @if ($t->isExpired())
                Termenul a trecut ({{ $t->valid_until->format('d.m H:i') }}): la intrare plătești diferența față de prețul de atunci.
            @else
                Valabil la acest preț până la {{ $t->valid_until->format('d.m.Y H:i') }}. După, plătești diferența la intrare.
            @endif
        </div>
    @endif
</div>
