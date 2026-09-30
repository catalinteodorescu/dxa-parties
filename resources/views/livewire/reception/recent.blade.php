@php
    $money = fn ($n) => number_format((float) $n, 2, ',', '.');
    $badge = ['entry' => 'bg-info-soft text-info', 'token' => 'bg-warning/10 text-warning', 'credit' => 'bg-success-soft text-success'];
@endphp
<div class="space-y-4"
     x-data="{
        open: false, kind: '', target: '', info: '', reason: '',
        ask(kind, target, info) { this.kind = kind; this.target = target; this.info = info; this.reason = ''; this.open = true; },
        submit() {
            if (this.reason.trim().length < 3) { return; }
            this.$wire.cancelOperation(this.kind, this.target, this.reason);
            this.open = false;
        },
     }">
    <div class="flex items-center justify-between gap-3">
        <div class="min-w-0">
            <h1 class="text-xl font-semibold text-ink">Tranzacții</h1>
        </div>
        @include('livewire.reception._sale-nav', ['current' => 'recent'])
    </div>
    @if ($party)<p class="-mt-1 text-sm text-ink-soft">{{ $party->name }}</p>@endif

    <x-flash />

    {{-- Mesajele apar sus: se aduc în vizor, altfel nu se văd când lista e derulată --}}
    @if ($message)
        <div wire:key="rc-msg-{{ md5($message) }}" x-data x-init="$el.scrollIntoView({ behavior: 'smooth', block: 'center' })"><x-alert type="success">{{ $message }}</x-alert></div>
    @endif
    @if ($error)
        <div wire:key="rc-err-{{ md5($error) }}" x-data x-init="$el.scrollIntoView({ behavior: 'smooth', block: 'center' })"><x-alert type="error">{{ $error }}</x-alert></div>
    @endif

    @if (! $party)
        <div class="rounded-2xl border border-border bg-surface p-5 text-sm text-ink-soft">Alege mai întâi petrecerea.</div>
    @elseif (! $session)
        <div class="rounded-2xl border border-border bg-surface p-5 text-sm text-ink-soft">Nicio sesiune de recepție deschisă și nicio operațiune înregistrată încă la această petrecere.</div>
    @elseif ($operations->isEmpty())
        <div class="rounded-2xl border border-border bg-surface p-5 text-sm text-ink-soft">Nicio operațiune în sesiunea curentă.</div>
    @else
        <div class="space-y-2.5">
            @foreach ($operations as $o)
                <div wire:key="op-{{ $o->kind }}-{{ $o->key }}" class="rounded-2xl border border-border bg-surface px-4 py-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium {{ $badge[$o->kind] }}">{{ $o->label }}</span>
                                <span class="text-xs text-ink-soft">{{ $o->at?->format('H:i') }}</span>
                            </div>
                            <div class="mt-1 text-base font-semibold {{ $o->cancelled ? 'text-ink-soft line-through' : 'text-ink' }}">
                                {{ $o->title }} · {{ $o->amount > 0 ? $money($o->amount).' lei' : 'gratuit' }}
                            </div>
                            @if ($o->methods)<div class="mt-0.5 text-xs text-ink-soft">{{ $o->methods }}</div>@endif
                            @if (! empty($o->code))<div class="mt-0.5 text-xs text-success">Cod {{ $o->code }}</div>@endif
                            @if ($o->people)
                                <div class="mt-0.5 text-xs text-ink">{{ implode(', ', array_slice($o->people, 0, 3)) }}@if (count($o->people) > 3) +{{ count($o->people) - 3 }} @endif</div>
                            @endif
                            @if ($o->cancelled)
                                <div class="mt-1 text-xs text-danger">Anulat: {{ $o->cancel_reason }}</div>
                            @endif
                        </div>
                        @unless ($o->cancelled)
                            <x-btn variant="danger" size="sm" outline
                                   x-on:click="ask('{{ $o->kind }}', '{{ addslashes($o->key) }}', {{ \Illuminate\Support\Js::from($o->title.' · '.$money($o->amount).' lei') }})">Anulează</x-btn>
                        @endunless
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Motivul anulării --}}
    <div x-show="open" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center p-4" style="display: none">
        <div class="absolute inset-0 bg-ink/50" x-on:click="open = false"></div>
        <div class="relative w-full max-w-sm rounded-2xl bg-surface border border-border shadow-lg p-5">
            <h3 class="text-base font-semibold text-ink">Anulezi operațiunea?</h3>
            <p class="mt-1 text-sm text-ink-soft" x-text="info"></p>
            <textarea x-model="reason" rows="2" maxlength="255" placeholder="Motiv (obligatoriu)"
                      class="mt-3 w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary"></textarea>
            <div class="mt-4 grid grid-cols-2 gap-3">
                <x-btn variant="neutral" x-on:click="open = false">Renunț</x-btn>
                <x-btn variant="danger" x-on:click="submit()">Anulează</x-btn>
            </div>
        </div>
    </div>
</div>
