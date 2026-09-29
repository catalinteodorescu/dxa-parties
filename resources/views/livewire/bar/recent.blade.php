@php
    $money = fn ($n) => number_format((float) $n, 2, ',', '.');
@endphp
<div class="space-y-4"
     x-data="{
        open: false, target: 0, info: '', reason: '',
        ask(target, info) { this.target = target; this.info = info; this.reason = ''; this.open = true; },
        submit() {
            if (this.reason.trim().length < 3) { return; }
            this.$wire.cancelSale(this.target, this.reason);
            this.open = false;
        },
     }">
    <div class="flex items-center justify-between gap-3">
        <div class="min-w-0">
            <h1 class="text-xl font-semibold text-ink">Vânzări recente</h1>
        </div>
        @include('livewire.bar._nav', ['current' => 'recent'])
    </div>
    @if ($party)<p class="-mt-1 text-sm text-ink-soft">{{ $party->name }}</p>@endif

    <x-flash />

    @if ($message)
        <div wire:key="br-msg-{{ md5($message) }}" x-data x-init="$el.scrollIntoView({ behavior: 'smooth', block: 'center' })"><x-alert type="success">{{ $message }}</x-alert></div>
    @endif
    @if ($error)
        <div wire:key="br-err-{{ md5($error) }}" x-data x-init="$el.scrollIntoView({ behavior: 'smooth', block: 'center' })"><x-alert type="error">{{ $error }}</x-alert></div>
    @endif

    @if (! $party)
        <div class="rounded-2xl border border-border bg-surface p-5 text-sm text-ink-soft">Alege mai întâi petrecerea.</div>
    @elseif (! $session)
        <div class="rounded-2xl border border-border bg-surface p-5 text-sm text-ink-soft">Nicio sesiune de vânzări deschisă la această petrecere.</div>
    @else
        @if ($reportSubmitted)
            <div class="rounded-2xl border border-warning/40 bg-warning/10 p-4 text-sm text-ink">Raportarea a fost trimisă: bonurile nu se mai pot anula până o redeschide un admin.</div>
        @endif

        @if ($rows->isEmpty())
            <div class="rounded-2xl border border-border bg-surface p-5 text-sm text-ink-soft">Niciun bon în sesiunea curentă.</div>
        @else
            <div class="space-y-2.5">
                @foreach ($rows as $r)
                    <div wire:key="bs-{{ $r->id }}" class="rounded-2xl border border-border bg-surface px-4 py-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-xs text-ink-soft">{{ $r->at?->format('H:i') }} · bon #{{ $r->id }}</div>
                                <div class="mt-0.5 text-base font-semibold {{ $r->cancelled ? 'text-ink-soft line-through' : 'text-ink' }}">{{ $money($r->total) }} lei</div>
                                <div class="mt-0.5 text-sm text-ink-soft">{{ $r->items }}</div>
                                @if ($r->methods)<div class="mt-0.5 text-xs text-ink-soft">{{ $r->methods }}</div>@endif
                                @if ($r->customer)<div class="mt-0.5 text-xs text-ink">{{ $r->customer }}</div>@endif
                                @if ($r->cancelled)<div class="mt-1 text-xs text-danger">Anulat: {{ $r->cancel_reason }}</div>@endif
                            </div>
                            @if ($r->can_cancel)
                                <x-btn variant="danger" size="sm" outline
                                       x-on:click="ask({{ $r->id }}, {{ \Illuminate\Support\Js::from('Bon #'.$r->id.' · '.$money($r->total).' lei') }})">Anulează</x-btn>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif

    {{-- Motivul anulării --}}
    <div x-show="open" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center p-4" style="display: none">
        <div class="absolute inset-0 bg-ink/50" x-on:click="open = false"></div>
        <div class="relative w-full max-w-sm rounded-2xl bg-surface border border-border shadow-lg p-5">
            <h3 class="text-base font-semibold text-ink">Anulezi bonul?</h3>
            <p class="mt-1 text-sm text-ink-soft" x-text="info"></p>
            <p class="mt-1 text-xs text-ink-soft">Creditele plătite pe acest bon se returnează participantului.</p>
            <textarea x-model="reason" rows="2" maxlength="255" placeholder="Motiv (obligatoriu)"
                      class="mt-3 w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary"></textarea>
            <div class="mt-4 grid grid-cols-2 gap-3">
                <x-btn variant="neutral" x-on:click="open = false">Renunț</x-btn>
                <x-btn variant="danger" x-on:click="submit()">Anulează</x-btn>
            </div>
        </div>
    </div>
</div>
