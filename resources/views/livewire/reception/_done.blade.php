{{--
    DXA: adaugat (PWA Recepție). Confirmare pe tot ecranul după o operațiune reușită.
    Variabile: $title, $line, $total (text, lei), $note (opțional), $links (opțional: [['url','label','icon'(token|credit)]], pe aceeași linie).
    Se închide cu „Gata”, cu un tap oriunde sau după 6 secunde (dismissDone din componentă).
--}}
<div class="fixed inset-0 z-[60] bg-primary text-white flex flex-col items-center justify-center px-6 text-center"
     role="status" wire:click="dismissDone" x-data x-init="setTimeout(() => $wire.dismissDone(), {{ empty($links) ? 6000 : 15000 }})"
     style="padding-top: env(safe-area-inset-top); padding-bottom: env(safe-area-inset-bottom)">
    <span class="flex h-28 w-28 items-center justify-center rounded-full bg-white/20">
        <svg class="h-16 w-16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
    </span>
    <h2 class="mt-8 text-3xl font-bold">{{ $title }}</h2>
    <p class="mt-3 text-xl font-medium">{{ $line }}</p>
    <p class="mt-1 text-4xl font-bold">{{ $total }} lei</p>
    @if (! empty($note))
        <p class="mt-5 max-w-xs text-sm text-white/80">{{ $note }}</p>
    @endif

    <div class="mt-10 w-full max-w-xs space-y-3">
        @if (! empty($links))
            <div class="grid grid-cols-2 gap-3">
                @foreach ($links as $l)
                    <a href="{{ $l['url'] }}" wire:navigate x-on:click.stop
                       class="flex items-center justify-center gap-2 rounded-xl bg-white/20 hover:bg-white/30 px-3 py-3.5 text-base font-semibold transition-colors">
                        @if ($l['icon'] === 'token')
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v8"/><path d="M8 12h8"/></svg>
                        @else
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/></svg>
                        @endif
                        {{ $l['label'] }}
                    </a>
                @endforeach
            </div>
        @endif
        <button type="button" wire:click="dismissDone" x-on:click.stop
                class="w-full rounded-xl bg-white text-primary text-lg font-semibold px-4 py-3.5">Gata</button>
    </div>
</div>
