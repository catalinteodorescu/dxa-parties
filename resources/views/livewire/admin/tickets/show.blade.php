{{-- DXA: adaugat (runda 66). Detaliul unui bilet. Variabile: $t (Ticket cu party, owner, holder, order.participant), $siblings, $transfers, $entry, $code, $canCancel, $refundable. --}}
@php
    $card = 'rounded-2xl border border-border bg-surface p-5';
    $lei = fn ($n) => number_format((float) $n, 2, ',', '.').' lei';
    $person = fn ($p, $fallback = '—') => $p && ! $p->isAnonymized()
        ? '<a href="'.e(route('admin.participants.show', $p->id)).'" wire:navigate class="text-primary hover:underline">'.e($p->name).'</a> <span class="text-ink-soft">'.e($p->phone ?: '').'</span>'
        : e($p ? 'Participant anonimizat' : $fallback);
    $order = $t->order;
@endphp
<div class="max-w-3xl" x-data="{
        cancelOpen: false,
        cancelReason: '',
        cancelRefund: true,
        tryCancel() {
            if (this.cancelReason.trim().length < 3) { return; }
            this.$wire.call('cancel', this.cancelReason, this.cancelRefund);
            this.cancelOpen = false;
        },
    }">
    @if ($message)
        <x-alert type="success" class="mb-4" data-ticket-message>{{ $message }}</x-alert>
    @endif
    @if ($error)
        <x-alert type="error" class="mb-4" data-ticket-error>{{ $error }}</x-alert>
    @endif
    <div class="mb-4">
        <a href="{{ route('admin.tickets.index') }}" wire:navigate class="text-sm text-ink-soft hover:text-ink">← Bilete</a>
        <h2 class="mt-1 text-xl font-semibold text-ink">{{ $t->ticket_type }} · {{ $t->party?->name ?? 'Petrecere ștearsă' }}</h2>
        <p class="mt-1 text-sm text-ink-soft">
            <span data-ticket-status>{{ \App\Services\AdminTickets::STATUS_LABELS[$t->status] ?? $t->status }}</span>
            · cod <span class="font-mono" data-ticket-code>{{ $t->shortCode() }}</span>
            · creat {{ $t->created_at->format('d.m.Y H:i') }}
        </p>
        @if ($canCancel)
            @permitsAction('cancel_tickets')
                <div class="mt-3">
                    <x-btn variant="danger" outline x-on:click="cancelReason = ''; cancelRefund = true; cancelOpen = true" data-ticket-cancel-btn>Anulează biletul</x-btn>
                </div>
            @endpermitsAction
        @endif
    </div>

    <div class="{{ $card }} mb-4">
        <h3 class="text-sm font-semibold text-ink">Bilet</h3>
        <dl class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1.5 text-sm">
            <div><dt class="text-xs text-ink-soft">Titular</dt><dd class="text-ink">{!! $t->holder ? $person($t->holder) : e($t->holder_phone ?: 'fără nume (liber)') !!}</dd></div>
            <div><dt class="text-xs text-ink-soft">În contul lui</dt><dd class="text-ink">{!! $person($t->owner) !!}</dd></div>
            <div><dt class="text-xs text-ink-soft">Preț plătit</dt><dd class="text-ink">{{ (float) $t->price > 0 ? $lei($t->price) : 'Gratuit' }}@if ((float) $t->list_price != (float) $t->price) <span class="text-ink-soft">(listă {{ $lei($t->list_price) }})</span>@endif</dd></div>
            <div><dt class="text-xs text-ink-soft">Plată</dt><dd class="text-ink" data-ticket-payment>{{ \App\Services\AdminTickets::paymentLabel($t) }}</dd></div>
            @if ($code)
                <div><dt class="text-xs text-ink-soft">Cod de reducere</dt><dd class="text-ink">{{ $code->code }} <span class="text-ink-soft">(−{{ $lei($t->discount_amount) }})</span></dd></div>
            @endif
            @if ($t->combo_label)
                <div><dt class="text-xs text-ink-soft">Combo</dt><dd class="text-ink">{{ $t->combo_label }}@if ($t->combo_free) <span class="text-ink-soft">(oferit)</span>@endif</dd></div>
            @endif
            @if ($t->valid_until)
                <div><dt class="text-xs text-ink-soft">Se poate intra până la</dt><dd class="text-ink">{{ $t->valid_until->format('d.m.Y H:i') }}</dd></div>
            @endif
            @if ($t->used_at)
                <div><dt class="text-xs text-ink-soft">Folosit</dt><dd class="text-ink" data-ticket-used>{{ $t->used_at->format('d.m.Y H:i') }}@if ($entry) <span class="text-ink-soft">· intrare #{{ $entry->id }}</span>@endif</dd></div>
            @endif
        </dl>
    </div>

    @if ($order)
        <div class="{{ $card }} mb-4" data-ticket-order>
            <h3 class="text-sm font-semibold text-ink">Comanda #{{ $order->id }}</h3>
            <dl class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1.5 text-sm">
                <div><dt class="text-xs text-ink-soft">Cumpărător</dt><dd class="text-ink">{!! $person($order->participant) !!}</dd></div>
                <div><dt class="text-xs text-ink-soft">Total</dt><dd class="text-ink">{{ $lei($order->total) }} <span class="text-ink-soft">· {{ $order->tickets_count }} {{ $order->tickets_count === 1 ? 'bilet' : 'bilete' }}</span></dd></div>
                <div><dt class="text-xs text-ink-soft">Stare plată</dt><dd class="text-ink">{{ \App\Services\AdminTickets::paymentLabel($t) }}</dd></div>
                @if ($order->paid_at)
                    <div><dt class="text-xs text-ink-soft">Plătită la</dt><dd class="text-ink">{{ $order->paid_at->format('d.m.Y H:i') }}</dd></div>
                @endif
                @if ($order->payment_expires_at && $order->isAwaitingCard())
                    <div><dt class="text-xs text-ink-soft">Rezervată până la</dt><dd class="text-ink">{{ $order->payment_expires_at->format('d.m.Y H:i') }}</dd></div>
                @endif
                @if ($order->payment_ref)
                    <div class="sm:col-span-2"><dt class="text-xs text-ink-soft">Referință {{ $order->payment_provider === 'stripe' ? 'Stripe' : 'plată' }}</dt><dd class="text-ink font-mono text-xs break-all" data-ticket-ref>{{ $order->payment_ref }}</dd></div>
                @endif
            </dl>

            @if ($siblings->isNotEmpty())
                <div class="mt-3 border-t border-border pt-3">
                    <div class="text-xs text-ink-soft mb-1.5">Celelalte bilete din comandă</div>
                    <div class="space-y-1.5">
                        @foreach ($siblings as $s)
                            <a wire:key="sb-{{ $s->id }}" href="{{ route('admin.tickets.show', $s->id) }}" wire:navigate
                               class="flex items-center justify-between gap-3 rounded-xl border border-border px-3 py-2 text-sm hover:border-primary/40">
                                <span class="text-ink">{{ $s->ticket_type }} · {{ $s->holder && ! $s->holder->isAnonymized() ? $s->holder->name : 'fără nume' }}</span>
                                <span class="text-xs text-ink-soft">{{ \App\Services\AdminTickets::STATUS_LABELS[$s->status] ?? $s->status }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif

    <div class="{{ $card }}" data-ticket-history>
        <h3 class="text-sm font-semibold text-ink">Istoric</h3>
        <ol class="mt-2 space-y-1.5 text-sm">
            <li class="text-ink">{{ $t->created_at->format('d.m.Y H:i') }} · {{ $t->status === \App\Models\Ticket::PENDING ? 'rezervat, așteaptă plata cu cardul' : 'cumpărat' }}</li>
            @foreach ($transfers as $tr)
                <li class="text-ink">{{ $tr->created_at?->format('d.m.Y H:i') }} · trimis de {{ $tr->from?->name ?? '—' }} către {{ $tr->to && ! $tr->to->isAnonymized() ? $tr->to->name : ($tr->to_phone ?: 'alt cont') }}</li>
            @endforeach
            @if ($t->used_at)
                <li class="text-ink">{{ $t->used_at->format('d.m.Y H:i') }} · folosit la intrare</li>
            @endif
            @if ($t->status === \App\Models\Ticket::VOID)
                <li class="text-ink-soft" data-ticket-cancelled>{{ $t->cancelled_at?->format('d.m.Y H:i') }} · anulat{{ $t->cancel_reason ? ': '.$t->cancel_reason : '' }}@if ((float) $t->refunded_amount > 0) · returnat în credite {{ $lei($t->refunded_amount) }}@endif</li>
            @endif
        </ol>
    </div>

    @if ($canCancel)
        {{-- Popup: anulare bilet (motiv obligatoriu) --}}
        <div x-show="cancelOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" data-cancel-dialog>
            <div class="absolute inset-0 bg-ink/40" @click="cancelOpen = false"></div>
            <div class="relative bg-surface rounded-2xl border border-border shadow-lg max-w-sm w-full p-6">
                <h3 class="text-base font-semibold text-ink">Anulează biletul</h3>
                <p class="mt-1 text-sm text-ink-soft">{{ $t->ticket_type }} · {{ $t->party?->name }} · cod {{ $t->shortCode() }}</p>
                <p class="mt-2 text-xs text-ink-soft/80">Locul se eliberează și biletul nu mai poate fi folosit la intrare. Banii nu se returnează pe card; rambursarea se face doar în credite.</p>

                <div class="mt-4">
                    <label class="block text-sm font-medium text-ink mb-1.5">Motiv</label>
                    <input type="text" x-model="cancelReason" maxlength="250" placeholder="ex. participantul nu mai poate veni"
                           @keydown.enter.prevent="tryCancel()"
                           class="w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                </div>

                @if ($refundable > 0)
                    <div class="mt-3">
                        <x-checkbox x-model="cancelRefund" data-cancel-refund>Returnează {{ $lei($refundable) }} în credite cumpărătorului</x-checkbox>
                    </div>
                @else
                    <p class="mt-3 text-xs text-ink-soft">Nu e nimic de returnat (bilet gratuit sau de plătit la intrare).</p>
                @endif

                <div class="mt-6 flex items-center justify-end gap-3">
                    <button type="button" @click="cancelOpen = false" class="text-sm font-medium text-ink-soft hover:text-ink px-3 py-2">Renunță</button>
                    <x-btn variant="danger" x-on:click="tryCancel()" data-cancel-confirm>Anulează biletul</x-btn>
                </div>
            </div>
        </div>
    @endif
</div>
