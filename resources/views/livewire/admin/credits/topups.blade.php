@php
    $card = 'rounded-2xl border border-border bg-surface p-5';
    $lei = fn ($n) => number_format((float) $n, 2, ',', '.').' lei';
    $pill = [
        \App\Models\CreditTopup::PENDING => 'bg-warning/10 text-warning',
        \App\Models\CreditTopup::PAID => 'bg-success-soft text-success',
        \App\Models\CreditTopup::EXPIRED => 'bg-bg text-ink-soft',
        \App\Models\CreditTopup::FAILED => 'bg-danger/10 text-danger',
        \App\Models\CreditTopup::CANCELLED => 'bg-bg text-ink-soft',
    ];
@endphp
<div class="max-w-3xl">
    <div class="mb-5">
        <h2 class="text-xl font-semibold text-ink">Credite · Încărcări cu cardul</h2>
        <p class="mt-1 text-sm text-ink-soft">
            Fiecare încercare de a încărca credite cu cardul. O încărcare <strong>plătită</strong> intră singură în credite (confirmarea vine de la Stripe).
            Una <strong>în așteptare</strong> e începută și neplătită încă; dacă nu se plătește, se închide singură ca <strong>expirată</strong> (la cel mult ~15 minute după termen).
            Dacă un participant spune că a plătit și nu are creditele, caută aici încărcarea lui, copiază referința Stripe și caută-o în Stripe › Payments.
            <a href="{{ route('admin.credits.index') }}" wire:navigate class="text-primary hover:underline">Înapoi la Credite</a>
        </p>
    </div>

    <div class="{{ $card }}">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
            <x-select wire:model="filterState" live :options="$stateOptions" />
        </div>
        <p class="mt-2 text-xs text-ink-soft">
            @foreach ($stateOptions as $key => $label)
                @continue($key === '')
                {{ $label }}: {{ (int) ($counts[$key] ?? 0) }}@if (! $loop->last) · @endif
            @endforeach
        </p>

        @if ($topups->isEmpty())
            <p class="mt-3 text-sm text-ink-soft">Nicio încărcare{{ $filterState ? ' în această stare' : ' cu cardul încă' }}.</p>
        @else
            <div class="mt-3 space-y-2">
                @foreach ($topups as $t)
                    <div wire:key="topup-{{ $t->id }}" data-topup="{{ $t->status }}" class="rounded-xl border border-border px-3.5 py-2.5">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2 flex-wrap min-w-0">
                                <span class="inline-flex items-center rounded-full text-xs font-medium px-2 py-0.5 {{ $pill[$t->status] ?? 'bg-bg text-ink-soft' }}">{{ $stateOptions[$t->status] ?? $t->status }}</span>
                                @if ($t->participant && ! $t->participant->isAnonymized())
                                    <a href="{{ route('admin.participants.show', $t->participant_id) }}" wire:navigate class="text-sm font-medium text-ink hover:text-primary">{{ $t->participant->name }}</a>
                                @else
                                    <span class="text-sm text-ink-soft">Participant anonimizat</span>
                                @endif
                            </div>
                            <span class="text-sm font-semibold shrink-0 text-ink">{{ $lei($t->amount) }}@if ((float) $t->bonus > 0) <span class="text-xs font-normal text-ink-soft">+ bonus {{ $lei($t->bonus) }}</span>@endif</span>
                        </div>
                        <div class="mt-0.5 text-xs text-ink-soft">
                            început {{ $t->created_at->format('d.m.Y H:i') }}
                            @if ($t->paid_at) · plătit {{ $t->paid_at->format('d.m.Y H:i') }} @elseif ($t->expires_at) · termen {{ $t->expires_at->format('d.m.Y H:i') }} @endif
                            @if ($t->provider_ref) · ref. <span class="font-mono select-all">{{ $t->provider_ref }}</span> @endif
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-3">
                {{ $topups->onEachSide(1)->links('pagination.dxa') }}
            </div>
        @endif
    </div>
</div>
