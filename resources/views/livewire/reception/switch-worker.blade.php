{{-- DXA: adaugat (runda 71). Buton în antet + dialog „Schimbă lucrătorul”: nume → PIN. Apare doar dacă există alt cont cu PIN. --}}
<div>
@if ($workers->isNotEmpty() || $selected)
    <div x-data="{ open: false }" @keydown.escape.window="open = false; $wire.back()">
        <button type="button" @click="open = true" title="Schimbă lucrătorul" aria-label="Schimbă lucrătorul" data-switch-worker
                class="rounded-lg p-2 bg-white/15 hover:bg-white/25 transition-colors">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 3 4 4-4 4"/><path d="M20 7H4"/><path d="m8 21-4-4 4-4"/><path d="M4 17h16"/></svg>
        </button>

        <div x-show="open" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4 text-ink">
            <div class="absolute inset-0 bg-ink/40" @click="open = false; $wire.back()"></div>
            <div class="relative bg-surface rounded-2xl border border-border shadow-lg max-w-sm w-full p-6">
                @if ($selected)
                    <h3 class="text-base font-semibold text-ink">{{ $selected->name ?: $selected->phone }}</h3>
                    <p class="mt-1 text-sm text-ink-soft">Introdu PIN-ul tău.</p>
                    <input type="password" inputmode="numeric" pattern="[0-9]*" maxlength="4" autocomplete="off" wire:model="pin" data-switch-pin
                           x-init="$nextTick(() => $el.focus())"
                           @input="if ($el.value.length === 4) { $wire.switch() }"
                           @keydown.enter.prevent="$wire.switch()"
                           class="mt-4 w-full rounded-lg border border-border bg-bg px-3 py-2 text-center text-lg tracking-[0.5em] text-ink"
                           style="font-size: 16px">
                    @if ($error)
                        <p class="mt-2 text-sm text-danger" data-switch-error>{{ $error }}</p>
                    @endif
                    <div class="mt-5 flex items-center justify-between gap-3">
                        <button type="button" wire:click="back" class="text-sm font-medium text-ink-soft hover:text-ink px-1 py-2">Înapoi</button>
                        <button type="button" wire:click="switch" class="rounded-lg bg-primary hover:bg-primary-hover text-white text-sm font-medium px-4 py-2 transition-colors">Continuă</button>
                    </div>
                @else
                    <h3 class="text-base font-semibold text-ink">Schimbă lucrătorul</h3>
                    <p class="mt-1 text-sm text-ink-soft">Alege cine preia telefonul.</p>
                    <div class="mt-4 flex flex-col gap-2">
                        @foreach ($workers as $w)
                            <button type="button" wire:key="worker-{{ $w->id }}" wire:click="choose({{ $w->id }})"
                                    class="w-full text-left rounded-xl border border-border bg-bg hover:border-primary px-4 py-3 text-sm font-medium text-ink transition-colors">
                                {{ $w->name ?: $w->phone }}
                            </button>
                        @endforeach
                    </div>
                    <div class="mt-5 text-right">
                        <button type="button" @click="open = false" class="text-sm font-medium text-ink-soft hover:text-ink px-1 py-2">Închide</button>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif
</div>
