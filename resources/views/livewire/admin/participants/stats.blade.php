@php
    $card = 'rounded-2xl border border-border bg-surface p-5';
    $int = fn ($n) => number_format((float) $n, 0, ',', '.');
    $money = fn ($n) => number_format((float) $n, 2, ',', '.');
@endphp
<div class="max-w-3xl">
    <div class="mb-5">
        <h2 class="text-xl font-semibold text-ink">Participanți · Statistici</h2>
        <p class="mt-1 text-sm text-ink-soft">Tablou de ansamblu — participanții ca oameni, nu ca tranzacții.</p>
    </div>

    {{-- Privire generală --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-4">
        <div class="{{ $card }}">
            <div class="text-[11px] text-ink-soft">Participanți identificați</div>
            <div class="text-2xl font-semibold text-ink">{{ $int($totalParticipants) }}</div>
        </div>
        <div class="{{ $card }}">
            <div class="text-[11px] text-ink-soft">Cu cel puțin o intrare</div>
            <div class="text-2xl font-semibold text-ink">{{ $int($participantsWithEntries) }}</div>
        </div>
        <div class="{{ $card }}">
            <div class="text-[11px] text-ink-soft">Intrări identificate</div>
            <div class="text-2xl font-semibold text-ink">{{ $int($totalIdentifiedEntries) }}</div>
        </div>
        <div class="{{ $card }}">
            <div class="text-[11px] text-ink-soft">1 intrare</div>
            <div class="text-2xl font-semibold text-ink">{{ $int($distribution['1']) }}</div>
            <p class="mt-0.5 text-[11px] text-ink-soft">o singură dată</p>
        </div>
        <div class="{{ $card }}">
            <div class="text-[11px] text-ink-soft">2–5 intrări</div>
            <div class="text-2xl font-semibold text-ink">{{ $int($distribution['2-5']) }}</div>
            <p class="mt-0.5 text-[11px] text-ink-soft">recurenți</p>
        </div>
        <div class="{{ $card }}">
            <div class="text-[11px] text-ink-soft">6+ intrări</div>
            <div class="text-2xl font-semibold text-ink">{{ $int($distribution['6+']) }}</div>
            <p class="mt-0.5 text-[11px] text-ink-soft">fideli</p>
        </div>
    </div>

    {{-- Participanți noi --}}
    <div class="{{ $card }} mb-4">
        <div class="flex items-center justify-between gap-3">
            <div>
                <div class="text-[11px] text-ink-soft">Participanți noi luna asta</div>
                <div class="text-2xl font-semibold text-ink">{{ $int($newThisMonth) }}</div>
            </div>
            <div class="text-right text-xs text-ink-soft">
                luna trecută: <span class="font-medium text-ink">{{ $int($newLastMonth) }}</span>
            </div>
        </div>
    </div>

    {{-- Cei mai fideli --}}
    <div class="{{ $card }} mb-4">
        <h3 class="text-sm font-semibold text-ink">Cei mai fideli</h3>
        <p class="mt-1 text-xs text-ink-soft">Cele mai multe intrări valabile, all-time.</p>
        @if ($topLoyal->isEmpty())
            <p class="mt-3 text-sm text-ink-soft">Niciun participant cu intrări încă.</p>
        @else
            <div class="mt-3 space-y-1.5">
                @foreach ($topLoyal as $p)
                    <a wire:key="tl-{{ $p->id }}" href="{{ route('admin.participants.show', $p) }}" wire:navigate
                       class="flex items-center justify-between gap-3 rounded-xl border border-border px-3.5 py-2 hover:border-primary/40 hover:bg-primary-soft/40 transition-colors">
                        <span class="text-sm font-medium text-ink">{{ $loop->iteration }}. {{ $p->name }}</span>
                        <span class="text-xs text-ink-soft">{{ (int) $p->entries_count }} {{ (int) $p->entries_count === 1 ? 'intrare' : 'intrări' }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Cei mai mari cheltuitori --}}
    <div class="{{ $card }} mb-4">
        <h3 class="text-sm font-semibold text-ink">Cei mai mari cheltuitori</h3>
        <p class="mt-1 text-xs text-ink-soft">Intrări + bar, all-time (fără beneficii — nu-s cheltuială).</p>
        @if ($topSpenders->isEmpty())
            <p class="mt-3 text-sm text-ink-soft">Nicio cheltuială înregistrată încă.</p>
        @else
            <div class="mt-3 space-y-1.5">
                @foreach ($topSpenders as $p)
                    <a wire:key="ts-{{ $p->id }}" href="{{ route('admin.participants.show', $p) }}" wire:navigate
                       class="flex items-center justify-between gap-3 rounded-xl border border-border px-3.5 py-2 hover:border-primary/40 hover:bg-primary-soft/40 transition-colors">
                        <span class="text-sm font-medium text-ink">{{ $loop->iteration }}. {{ $p->name }}</span>
                        <span class="text-xs text-ink-soft">{{ $money((float) $p->entries_amount + (float) $p->bar_spent) }} lei</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Cei mai activi recent --}}
    <div class="{{ $card }}">
        <h3 class="text-sm font-semibold text-ink">Cei mai activi recent</h3>
        <p class="mt-1 text-xs text-ink-soft">Cele mai multe intrări în ultimele 3 luni.</p>
        @if ($topRecent->isEmpty())
            <p class="mt-3 text-sm text-ink-soft">Nicio intrare în ultimele 3 luni.</p>
        @else
            <div class="mt-3 space-y-1.5">
                @foreach ($topRecent as $p)
                    <a wire:key="tr-{{ $p->id }}" href="{{ route('admin.participants.show', $p) }}" wire:navigate
                       class="flex items-center justify-between gap-3 rounded-xl border border-border px-3.5 py-2 hover:border-primary/40 hover:bg-primary-soft/40 transition-colors">
                        <span class="text-sm font-medium text-ink">{{ $loop->iteration }}. {{ $p->name }}</span>
                        <span class="text-xs text-ink-soft">{{ (int) $p->recent_count }} {{ (int) $p->recent_count === 1 ? 'intrare' : 'intrări' }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
