@php
    $card = 'rounded-2xl border border-border bg-surface p-5';
    $input = 'w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary';
    $money = fn ($n) => number_format((float) $n, 2, ',', '.');
    $signed = fn ($n) => ($n > 0 ? '+' : ($n < 0 ? '−' : '')).number_format(abs((float) $n), 2, ',', '.');
    $fmt = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d.m.Y H:i') : '—';
    $anonymized = $participant->isAnonymized();
    $creditTypeClass = [
        'load' => 'bg-primary-soft text-primary',
        'payment' => 'bg-info/10 text-info',
        'refund' => 'bg-warning/10 text-warning',
        'adjustment' => 'bg-bg text-ink-soft',
    ];
@endphp
<div
    class="max-w-3xl"
    x-data="{
        creditTab: 'load',
        confirmOpen: false,
        confirmTitle: '',
        confirmMessage: '',
        confirmMethod: '',
        confirmArgs: [],
        askConfirm(title, message, method, args = []) {
            this.confirmTitle = title;
            this.confirmMessage = message;
            this.confirmMethod = method;
            this.confirmArgs = args;
            this.confirmOpen = true;
        },
        runConfirm() {
            this.$wire.call(this.confirmMethod, ...this.confirmArgs);
            this.confirmOpen = false;
        },
    }"
>
    <div class="mb-5">
        <a href="{{ route('admin.participants.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm text-ink-soft hover:text-ink">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
            Înapoi la Participanți
        </a>
    </div>

    <div class="mb-5">
        <h2 class="text-xl font-semibold text-ink">{{ $participant->name }}</h2>
        <p class="mt-1 text-sm text-ink-soft">
            {{ $participant->phone ?: 'fără telefon' }} · adăugat {{ $participant->created_at->format('d.m.Y') }}
            @if ($anonymized) · anonimizat {{ $participant->anonymized_at->format('d.m.Y') }} @endif
        </p>
    </div>

    @if ($message)
        <x-alert type="success" class="mb-4">{{ $message }}</x-alert>
    @endif
    @if ($error)
        <x-alert type="error" class="mb-4">{{ $error }}</x-alert>
    @endif

    {{-- Numărători --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
        <div class="rounded-xl border border-border bg-surface px-3 py-2.5">
            <div class="text-[11px] text-ink-soft">Intrări valabile</div>
            <div class="text-lg font-semibold text-ink">{{ $validEntries }}</div>
        </div>
        <div class="rounded-xl border border-border bg-surface px-3 py-2.5">
            <div class="text-[11px] text-ink-soft">Plătite</div>
            <div class="text-lg font-semibold text-ink">{{ $paidEntries }}</div>
        </div>
        <div class="rounded-xl border border-border bg-surface px-3 py-2.5">
            <div class="text-[11px] text-ink-soft">Gratuite</div>
            <div class="text-lg font-semibold text-ink">{{ $freeEntries }}</div>
        </div>
        <div class="rounded-xl border border-border bg-surface px-3 py-2.5">
            <div class="text-[11px] text-ink-soft">Ultima intrare</div>
            <div class="text-sm font-semibold text-ink mt-1">{{ $fmt($lastAt) }}</div>
        </div>
    </div>

    {{-- Cheltuieli --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
        <div class="rounded-xl border border-border bg-surface px-3 py-2.5">
            <div class="text-[11px] text-ink-soft">Cheltuit la bar</div>
            <div class="text-lg font-semibold text-ink">{{ $money($spend->bar_spent) }} <span class="text-xs font-normal text-ink-soft">lei</span></div>
            <div class="text-[11px] text-ink-soft">{{ $spend->bar_sales }} {{ $spend->bar_sales === 1 ? 'bon' : 'bonuri' }}</div>
        </div>
        <div class="rounded-xl border border-border bg-surface px-3 py-2.5">
            <div class="text-[11px] text-ink-soft">Tokeni cumpărați</div>
            <div class="text-lg font-semibold text-ink">{{ number_format($spend->tokens, 0, ',', '.') }}</div>
            <div class="text-[11px] text-ink-soft">{{ $money($spend->tokens_amount) }} lei</div>
        </div>
        <div class="rounded-xl border border-border bg-surface px-3 py-2.5">
            <div class="text-[11px] text-ink-soft">Plătit la intrări</div>
            <div class="text-lg font-semibold text-ink">{{ $money($spend->entries_amount) }} <span class="text-xs font-normal text-ink-soft">lei</span></div>
            <div class="text-[11px] text-ink-soft">{{ $spend->entries_paid }} plătite</div>
        </div>
        <div class="rounded-xl border border-border bg-surface px-3 py-2.5">
            <div class="text-[11px] text-ink-soft">Prima intrare</div>
            <div class="text-sm font-semibold text-ink mt-1">{{ $fmt($firstAt) }}</div>
        </div>
    </div>

    {{-- Date --}}
    @unless ($anonymized)
        <div class="{{ $card }} mb-4">
            <h3 class="text-sm font-semibold text-ink">Date</h3>
            <div class="mt-3 grid grid-cols-1 sm:grid-cols-5 gap-2">
                <input type="text" wire:model="name" maxlength="120" placeholder="Nume" class="sm:col-span-2 {{ $input }}">
                <input type="text" inputmode="tel" wire:model="phone" maxlength="20" placeholder="Telefon" x-on:keydown.enter.prevent="$wire.save()" class="sm:col-span-2 {{ $input }}">
                <x-btn variant="warning" wire:click="save">Salvează</x-btn>
            </div>
            <p class="mt-2 text-xs text-ink-soft">Telefonul e cheia participantului: când își face cont în aplicație cu același număr, istoricul se păstrează.</p>
        </div>
    @endunless

    {{-- Portofel de credite --}}
    <div class="{{ $card }} mb-4">
        <div class="flex items-center justify-between gap-3">
            <h3 class="text-sm font-semibold text-ink">Portofel de credite</h3>
            <div class="text-right">
                <div class="text-2xl font-semibold text-ink">{{ $money($creditBalance) }} <span class="text-xs font-normal text-ink-soft">lei</span></div>
            </div>
        </div>
        <p class="mt-1 text-xs text-ink-soft leading-relaxed">1 credit = 1 leu. Nu expiră. Fără refund automat — doar manual, cu motiv.</p>

        {{-- Tab-uri: cele 3 acțiuni una câte una, nu deodată --}}
        <div class="mt-4 flex items-center gap-1 border-b border-border">
            <button type="button" @click="creditTab = 'load'"
                    :class="creditTab === 'load' ? 'border-primary text-primary' : 'border-transparent text-ink-soft hover:text-ink'"
                    class="px-3 py-2 text-xs font-medium border-b-2 -mb-px transition-colors">
                Încărcare manuală
            </button>
            <button type="button" @click="creditTab = 'adjust'"
                    :class="creditTab === 'adjust' ? 'border-primary text-primary' : 'border-transparent text-ink-soft hover:text-ink'"
                    class="px-3 py-2 text-xs font-medium border-b-2 -mb-px transition-colors">
                Ajustare
            </button>
            <button type="button" @click="creditTab = 'refund'"
                    :class="creditTab === 'refund' ? 'border-primary text-primary' : 'border-transparent text-ink-soft hover:text-ink'"
                    class="px-3 py-2 text-xs font-medium border-b-2 -mb-px transition-colors">
                Refund manual
            </button>
        </div>

        <div class="mt-3">
            {{-- Încărcare manuală --}}
            <div x-show="creditTab === 'load'" x-cloak>
                <div class="space-y-1.5 max-w-sm">
                    <input type="text" inputmode="decimal" wire:model="creditLoadAmount" placeholder="Sumă (lei)" class="{{ $input }}">
                    <input type="text" wire:model="creditLoadNote" maxlength="255" placeholder="Motiv (obligatoriu)" class="{{ $input }}">
                    <x-btn variant="primary" wire:click="loadCredits">Încarcă</x-btn>
                </div>
            </div>

            {{-- Ajustare --}}
            <div x-show="creditTab === 'adjust'" x-cloak>
                <div class="space-y-1.5 max-w-sm">
                    <input type="text" inputmode="decimal" wire:model="creditAdjustAmount" placeholder="ex. 20 sau -20" class="{{ $input }}">
                    <input type="text" wire:model="creditAdjustReason" maxlength="255" placeholder="Motiv (obligatoriu)" class="{{ $input }}">
                    <x-btn variant="neutral" wire:click="adjustCredits">Ajustează</x-btn>
                </div>
            </div>

            {{-- Refund manual --}}
            <div x-show="creditTab === 'refund'" x-cloak>
                <div class="space-y-1.5 max-w-sm">
                    <input type="text" inputmode="decimal" wire:model="creditRefundAmount" placeholder="Sumă (lei)" class="{{ $input }}">
                    <input type="text" wire:model="creditRefundReason" maxlength="255" placeholder="Motiv (obligatoriu)" class="{{ $input }}">
                    <x-btn variant="danger" outline wire:click="refundCredits">Refundează</x-btn>
                </div>
            </div>
        </div>

        @if ($creditTransactions->isNotEmpty())
            <div class="mt-4 pt-4 border-t border-border">
                <div class="text-xs font-medium text-ink-soft mb-2">Istoric credite</div>
                <div class="space-y-2">
                    @foreach ($creditTransactions as $ct)
                        <div wire:key="ct-{{ $ct->id }}" class="rounded-xl border border-border px-3.5 py-2">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="inline-flex items-center rounded-full text-xs font-medium px-2 py-0.5 {{ $creditTypeClass[$ct->type] ?? 'bg-bg text-ink-soft' }}">{{ $ct->typeLabel() }}</span>
                                <span class="text-sm font-medium text-ink">{{ $signed($ct->amount) }} lei</span>
                                <span class="text-xs text-ink-soft">{{ $ct->sourceLabel() }}</span>
                            </div>
                            <div class="mt-0.5 text-xs text-ink-soft">
                                {{ $ct->occurred_at->format('d.m.Y H:i') }}
                                @if ($ct->createdBy) · {{ $ct->createdBy->name }} @endif
                            </div>
                            @if ($ct->note)
                                <div class="mt-0.5 text-xs text-ink-soft">{{ $ct->note }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
                @if ($totalCreditTransactions > $creditTransactions->count())
                    <p class="mt-2 text-[11px] text-ink-soft">Se afișează ultimele {{ $creditTransactions->count() }} din {{ $totalCreditTransactions }}.</p>
                @endif
            </div>
        @endif
    </div>

    {{-- Card de fidelitate --}}
    @if ($loyaltyOn || $loyaltyEnrolled)
        <div class="{{ $card }} mb-4">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-ink">Card de fidelitate</h3>
                @if (! $loyaltyEnrolled)
                    <x-btn variant="primary" size="sm" wire:click="toggleEnroll">Înrolează la fidelitate</x-btn>
                @endif
            </div>

            @if (! $loyaltyEnrolled)
                <p class="mt-2 text-xs text-ink-soft leading-relaxed">Participantul nu e înrolat — nu are card. Odată înrolat, primește automat o ștampilă la fiecare intrare identificată pe o petrecere care acordă fidelitate.</p>
            @else
                {{-- Card (stânga) + ajustare manuală (dreapta; sub card pe ecran îngust) --}}
                <div class="mt-3 flex flex-col sm:flex-row sm:items-start gap-4 sm:gap-6">
                    {{-- Vizual: card tip credit card, gradient terracotta --}}
                    <div class="w-full max-w-xs shrink-0 rounded-2xl p-5 text-white shadow-md" style="background: linear-gradient(135deg, var(--color-primary-bright), var(--color-primary) 55%, var(--color-primary-dark));">
                        <div class="min-w-0">
                            <div class="text-base font-semibold truncate">{{ $participant->name }}</div>
                            <div class="text-xs text-white/80">{{ $participant->phone ?: 'fără telefon' }}</div>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2.5 max-w-[266px]">
                            @for ($i = 0; $i < $loyaltyCard->stamps_required; $i++)
                                @php($s = $loyaltyStamps->get($i))
                                <div wire:key="circle-{{ $i }}" class="w-9 h-9 rounded-full flex items-center justify-center border-2 {{ $s ? 'bg-white/25 border-white' : 'border-white/40' }}" title="{{ $s ? $s->stamped_at->format('d.m.Y') : 'necompletat' }}">
                                    @if ($s)
                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                    @else
                                        <span class="text-xs text-white/60">{{ $i + 1 }}</span>
                                    @endif
                                </div>
                            @endfor
                            @php($free = $loyaltyStamps->get($loyaltyCard->stamps_required))
                            <div class="w-9 h-9 rounded-full flex items-center justify-center border-2 border-dashed {{ $free ? 'bg-white/25 border-white' : 'border-white/60' }}" title="{{ $free ? 'intrare gratis folosită '.$free->stamped_at->format('d.m.Y') : 'intrare gratis' }}">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 12v10H4V12"/><path d="M2 7h20v5H2z"/><path d="M12 22V7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7Z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7Z"/></svg>
                            </div>
                        </div>
                        <div class="mt-3 flex items-center justify-between gap-3 text-[11px] text-white/70">
                            <span>
                                creat {{ $loyaltyCard->created_at->format('d.m.Y') }}
                                @if ($lastAt) · ultima intrare {{ \Illuminate\Support\Carbon::parse($lastAt)->format('d.m.Y') }} @endif
                            </span>
                            <span class="shrink-0">#{{ $loyaltyCardNumber }}</span>
                        </div>
                    </div>

                    {{-- Ajustare manuală --}}
                    <div class="min-w-0 sm:flex-1 sm:max-w-sm">
                        <div class="text-xs font-medium text-ink-soft mb-2">Ajustează ștampile</div>
                        <div class="space-y-1.5 max-w-sm">
                            <input type="text" inputmode="numeric" wire:model="loyaltyAdjustDelta" placeholder="ex. 3 sau -1" class="{{ $input }}">
                            <input type="text" wire:model="loyaltyAdjustReason" maxlength="255" placeholder="Motiv (obligatoriu)" class="{{ $input }}">
                            <x-btn variant="neutral" wire:click="adjustLoyaltyStamps">Ajustează</x-btn>
                        </div>
                        <p class="mt-1.5 text-[11px] text-ink-soft">Util și pentru a prelua ștampilele unui card fizic mai vechi.</p>
                    </div>
                </div>

                {{-- Istoric ștampile --}}
                @if ($loyaltyHistory->isNotEmpty())
                    <div class="mt-4 pt-4 border-t border-border">
                        <div class="text-xs font-medium text-ink-soft mb-2">Istoric ștampile</div>
                        <div class="space-y-2">
                            @foreach ($loyaltyHistory as $row)
                                <div wire:key="lh-{{ $row->key }}" class="rounded-xl border border-border px-3.5 py-2 {{ $row->voided ? 'bg-bg' : '' }}">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="inline-flex items-center rounded-full text-xs font-medium px-2 py-0.5 {{ $row->voided ? 'bg-bg text-ink-soft line-through' : 'bg-primary-soft text-primary' }}">
                                            {{ \App\Models\LoyaltyStamp::SOURCE_LABELS[$row->source] ?? $row->source }}{{ $row->count > 1 ? ' ×'.$row->count : '' }}
                                        </span>
                                        <span class="text-xs text-ink-soft">{{ $row->stamped_at->format('d.m.Y H:i') }}</span>
                                        <span class="text-xs text-ink-soft">· Card #{{ $row->card_number }}</span>
                                        @if ($row->party_name)
                                            <span class="text-xs text-ink-soft">· {{ $row->party_name }}</span>
                                        @endif
                                    </div>
                                    @if ($row->reason)
                                        <div class="mt-0.5 text-xs text-ink-soft">{{ $row->reason }}</div>
                                    @endif
                                    @if ($row->voided)
                                        <div class="mt-0.5 text-xs text-danger">anulată: {{ $row->void_reason }}</div>
                                    @elseif ($row->activeCount < $row->count)
                                        <div class="mt-0.5 text-xs text-danger">{{ $row->count - $row->activeCount }} din {{ $row->count }} anulate</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Stack de carduri arhivate --}}
                @if ($loyaltyArchived->isNotEmpty())
                    <div class="mt-4 pt-4 border-t border-border">
                        <div class="text-xs font-medium text-ink-soft mb-2">Carduri anterioare (completate)</div>
                        <div class="space-y-1.5">
                            @foreach ($loyaltyArchived as $ac)
                                <div wire:key="lc-{{ $ac->id }}" class="rounded-xl border border-border px-3.5 py-2 text-xs text-ink-soft flex items-center justify-between">
                                    <span>Card #{{ $ac->number() }} — {{ $ac->totalCircles() }} ștampile</span>
                                    <span>completat {{ $ac->completed_at?->format('d.m.Y') }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endif
        </div>

        {{-- Înrolare, ca popup --}}
        @if ($enrollOpen)
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-ink/40" wire:click="toggleEnroll"></div>
                <div class="relative bg-surface rounded-2xl border border-border shadow-lg max-w-sm w-full p-6">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-base font-semibold text-ink">Înrolează la fidelitate</h3>
                        <button type="button" wire:click="toggleEnroll" aria-label="Închide" class="h-7 w-7 inline-flex items-center justify-center rounded-lg text-ink-soft hover:bg-bg">×</button>
                    </div>
                    <p class="mt-2 text-xs text-ink-soft leading-relaxed">Pornește un card nou. Dacă participantul are deja un card fizic cu ștampile adunate, le poți prelua acum.</p>
                    <div class="mt-3 space-y-2">
                        <input type="text" inputmode="numeric" wire:model="enrollInitialStamps" placeholder="Ștampile deja adunate (opțional, implicit 0)"
                               x-on:keydown.enter.prevent="$wire.enrollLoyalty()"
                               class="{{ $input }}">
                        @if ($error)
                            <p class="text-sm text-danger">{{ $error }}</p>
                        @endif
                    </div>
                    <div class="mt-4 flex items-center justify-end gap-3">
                        <button type="button" wire:click="toggleEnroll" class="text-sm font-medium text-ink-soft hover:text-ink px-3 py-2">Renunță</button>
                        <x-btn variant="primary" wire:click="enrollLoyalty">Înrolează</x-btn>
                    </div>
                </div>
            </div>
        @endif
    @endif

    {{-- Pe petreceri --}}
    @if ($spend->parties->isNotEmpty())
        <div class="{{ $card }} mb-4">
            <h3 class="text-sm font-semibold text-ink">Pe petreceri</h3>
            <p class="mt-1 text-xs text-ink-soft">Cheltuit la bar = plățile vânzărilor finalizate, fără beneficii (include plățile cu tokeni, la valoarea lor în lei). Nu se adună cu tokenii cumpărați.</p>
            <div class="mt-3 space-y-2">
                @foreach ($spend->parties as $row)
                    <div wire:key="sp-{{ $row->party_id ?? 'none' }}" class="rounded-xl border border-border px-3.5 py-2">
                        <div class="text-sm font-medium text-ink">
                            {{ $row->party }}
                            @if ($row->date) <span class="font-normal text-ink-soft">· {{ $row->date->format('d.m.Y') }}</span> @endif
                        </div>
                        <div class="mt-0.5 text-xs text-ink-soft">
                            Intrări {{ $row->entries }} ({{ $money($row->entries_amount) }} lei)
                            · Bar {{ $money($row->bar_spent) }} lei ({{ $row->bar_sales }} {{ $row->bar_sales === 1 ? 'bon' : 'bonuri' }})
                            · Tokeni {{ number_format($row->tokens, 0, ',', '.') }}@if ($row->tokens > 0) ({{ $money($row->tokens_amount) }} lei)@endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Istoric --}}
    <div class="{{ $card }} mb-4">
        <h3 class="text-sm font-semibold text-ink">Istoric intrări</h3>
        @if ($entries->isEmpty())
            <p class="mt-2 text-sm text-ink-soft">Nicio intrare încă.</p>
        @else
            <div class="mt-3 space-y-2">
                @foreach ($entries as $e)
                    <div wire:key="pe-{{ $e->id }}" class="rounded-xl border border-border px-3.5 py-2 {{ $e->isCancelled() ? 'bg-bg' : 'bg-surface' }}">
                        <div class="text-sm font-medium {{ $e->isCancelled() ? 'text-ink-soft line-through' : 'text-ink' }}">
                            {{ $e->party?->name ?? 'Petrecere ștearsă' }} · {{ $e->ticket_type }} · {{ $e->price_paid > 0 ? $money($e->price_paid).' lei' : 'gratuit' }}
                        </div>
                        <div class="mt-0.5 text-xs text-ink-soft">
                            {{ $e->entered_at->format('d.m.Y H:i') }}
                            @if ($e->isCancelled()) · <span class="text-danger">anulată: {{ $e->cancel_reason }}</span> @endif
                        </div>
                    </div>
                @endforeach
            </div>
            @if ($totalEntries > $entries->count())
                <p class="mt-2 text-[11px] text-ink-soft">Se afișează ultimele {{ $entries->count() }} din {{ $totalEntries }}.</p>
            @endif
        @endif
    </div>

    {{-- DXA: adaugat (runda 39). Transferuri de bilete (doar istoric). --}}
    @if ($transfers->isNotEmpty())
        <div class="{{ $card }} mb-4" data-ticket-transfers>
            <h3 class="text-sm font-semibold text-ink">Bilete trimise / primite</h3>
            <div class="mt-3 space-y-2">
                @foreach ($transfers as $tr)
                    <div wire:key="tt-{{ $tr->id }}" class="rounded-xl border border-border px-3.5 py-2">
                        <div class="text-sm font-medium text-ink">
                            {{ $tr->ticket?->party?->name ?? 'Petrecere ștearsă' }} · {{ $tr->ticket?->ticket_type }}
                        </div>
                        <div class="mt-0.5 text-xs text-ink-soft">
                            {{ $tr->created_at->format('d.m.Y H:i') }} ·
                            @if ($tr->from_participant_id === $participant->id)
                                trimis către {{ $tr->to?->name }} ({{ $tr->to_phone }})
                            @else
                                primit de la {{ $tr->from?->name }}
                            @endif
                            @if (! $tr->to_had_account) · fără cont la momentul trimiterii{{ $tr->sms_sent ? ', SMS trimis' : ', SMS netrimis' }} @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Ștergere / anonimizare --}}
    <div class="{{ $card }}">
        <h3 class="text-sm font-semibold text-ink">Date personale</h3>
        @if ($totalEntries === 0)
            <p class="mt-1 text-xs text-ink-soft leading-relaxed">Participantul n-are nicio intrare, deci poate fi șters complet.</p>
            <div class="mt-3">
                <x-btn variant="danger" outline
                       x-on:click="askConfirm('Șterge participantul', 'Ștergi definitiv acest participant? Acțiunea nu poate fi anulată.', 'delete')">
                    Șterge participantul
                </x-btn>
            </div>
        @elseif (! $anonymized)
            <p class="mt-1 text-xs text-ink-soft leading-relaxed">
                Are intrări înregistrate, deci nu se șterge. Poți să-l anonimizezi: numele și telefonul se golesc, iar intrările rămân doar ca număr în statistici. Nu se poate anula.
            </p>
            <div class="mt-3">
                <x-btn variant="danger" outline
                       x-on:click="askConfirm('Anonimizează participantul', 'Anonimizezi acest participant? Numele și telefonul se șterg definitiv.', 'anonymize')">
                    Anonimizează
                </x-btn>
            </div>
        @else
            <p class="mt-1 text-xs text-ink-soft">Participantul a fost anonimizat; intrările rămân doar ca număr în statistici.</p>
        @endif
    </div>

    {{-- Modal de confirmare, pentru ștergere/anonimizare --}}
    <div x-show="confirmOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-ink/40" @click="confirmOpen = false"></div>
        <div class="relative bg-surface rounded-2xl border border-border shadow-lg max-w-sm w-full p-6">
            <h3 class="text-base font-semibold text-ink" x-text="confirmTitle"></h3>
            <p class="mt-2 text-sm text-ink-soft" x-text="confirmMessage"></p>
            <div class="mt-6 flex items-center justify-end gap-3">
                <button type="button" @click="confirmOpen = false"
                        class="text-sm font-medium text-ink-soft hover:text-ink px-3 py-2">
                    Renunță
                </button>
                <button type="button" @click="runConfirm()"
                        class="rounded-lg bg-danger hover:bg-danger/90 text-white text-sm font-medium px-4 py-2 transition-colors">
                    Confirmă
                </button>
            </div>
        </div>
    </div>
</div>
