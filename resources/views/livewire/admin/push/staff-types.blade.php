{{-- DXA: adaugat (runda 77). Setări › Aplicație bar / recepție › Notificări pentru personal. Variabile: $enabled, $types (label, hint, example, on), $phones, $accounts, $without. --}}
<div class="rounded-2xl border border-border bg-surface p-5 space-y-4" data-staff-types="{{ $page }}">
    <div>
        <h3 class="text-sm font-semibold text-ink">Notificări — ce se trimite</h3>
        <p class="mt-1 text-xs text-ink-soft">La login în aplicație, fiecare telefon e invitat să activeze notificările. Aici alegi ce tipuri primesc toate telefoanele din această aplicație; cine lucrează nu poate alege.</p>
    </div>

    @unless ($enabled)
        <div class="rounded-lg border border-warning/30 bg-warning/10 px-3 py-2 text-xs text-ink" data-push-off>
            Notificările nu sunt configurate. Generează cheile cu <span class="font-mono">php artisan dxa:push-keys</span> și pune-le în <span class="font-mono">.env</span> (VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY, VAPID_SUBJECT).
        </div>
    @endunless

    <div class="rounded-xl bg-bg px-3 py-2 text-xs text-ink-soft" data-staff-phones>
        <span class="font-medium text-ink">{{ $phones }} {{ $phones === 1 ? 'telefon abonat' : 'telefoane abonate' }}</span>
        ({{ $accounts }} {{ $accounts === 1 ? 'cont' : 'conturi' }}).
        @if ($without)
            Fără telefon abonat: {{ implode(', ', $without) }}.
        @endif
    </div>

    <div class="divide-y divide-border">
        @foreach ($types as $kindKey => $t)
            <div class="py-3 first:pt-0 last:pb-0" wire:key="stype-{{ $kindKey }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-sm font-medium text-ink">{{ $t['label'] }}</div>
                        <div class="text-xs text-ink-soft">{{ $t['hint'] }}</div>
                    </div>
                    <button type="button" wire:click="toggleStaffType('{{ $kindKey }}')" role="switch" aria-checked="{{ $t['on'] ? 'true' : 'false' }}"
                            aria-label="{{ $t['label'] }}" data-type="{{ $kindKey }}" @if ($t['on']) data-on @endif
                            class="relative mt-0.5 inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors {{ $t['on'] ? 'bg-primary' : 'bg-border' }}">
                        <span class="inline-block h-[18px] w-[18px] transform rounded-full bg-white shadow transition-transform {{ $t['on'] ? 'translate-x-6' : 'translate-x-1' }}"></span>
                    </button>
                </div>
                <div class="mt-2 rounded-xl bg-bg px-3 py-2 {{ $t['on'] ? '' : 'opacity-50' }}" data-example="{{ $kindKey }}">
                    <div class="text-[10px] font-semibold uppercase tracking-wide text-ink-soft/70">Exemplu</div>
                    <div class="mt-0.5 text-sm font-semibold text-ink">{{ $t['example'][0] }}</div>
                    <div class="text-xs text-ink-soft">{{ $t['example'][1] }}</div>
                </div>
            </div>
        @endforeach
    </div>
</div>
