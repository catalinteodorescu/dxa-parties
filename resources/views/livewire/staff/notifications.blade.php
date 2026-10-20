{{-- Runda 91. Pagina „Notificări” din Recepție / Bar. Variabile: $pwa (clasa aplicației), $app, $items, $groups, $hasMore, $unread, $pushEnabled. --}}
@php $r = $pwa::ROUTE_PREFIX; @endphp
<div class="space-y-4">
    <div class="flex items-center justify-between gap-3">
        <h1 class="text-xl font-semibold text-ink">Notificări</h1>
        @if ($unread > 0)
            <button type="button" wire:click="markAll" data-mark-all class="h-11 px-3 -mr-3 text-sm font-medium text-primary">Marchează toate ca citite</button>
        @endif
    </div>

    @if ($pushEnabled)
        {{-- Doar activarea: panoul dispare după ce telefonul e abonat. Dezactivarea se face din setările telefonului. --}}
        <div wire:ignore style="display: none" data-push-card data-hide-on="on" x-data x-init="$nextTick(() => appPushInit($el))"
             data-key="{{ \App\Services\Push\Vapid::publicKey() }}" data-csrf="{{ csrf_token() }}"
             data-subscribe="{{ route($r.'.push.subscribe', [], false) }}" data-unsubscribe="{{ route($r.'.push.unsubscribe', [], false) }}" data-test="{{ route($r.'.push.test', [], false) }}">
            <div class="rounded-2xl border border-border bg-surface p-4 space-y-3">
                <div class="text-base font-semibold text-ink">Primește alertele pe telefon</div>
                <p class="text-sm text-ink-soft">Deschiderea, raportările și ce se întâmplă în timpul petrecerii, chiar și cu aplicația închisă.</p>
                <p class="text-sm text-ink-soft" data-msg></p>
                <button type="button" data-act="enable" data-show="off" style="display: none"
                        class="w-full h-12 rounded-lg bg-primary hover:bg-primary-hover text-white text-base font-medium transition-colors">Activează notificările</button>
            </div>
        </div>
    @endif

    @if ($items->isEmpty())
        <div class="rounded-2xl border border-border bg-surface p-6 text-center text-sm text-ink-soft" data-notifications-empty>
            Nu ai nicio notificare încă. Aici apar alertele pentru munca de la {{ $app === 'bar' ? 'bar' : 'recepție' }}.
        </div>
    @else
        @foreach ($groups as $day => $rows)
            <section class="space-y-2" wire:key="day-{{ $day }}">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-ink-soft">{{ $day }}</h2>
                <div class="rounded-2xl border border-border bg-surface divide-y divide-border overflow-hidden">
                    @foreach ($rows as $n)
                        <button type="button" wire:key="n-{{ $n->id }}" wire:click="open({{ $n->id }})" data-notification="{{ $n->id }}" data-unread="{{ $n->read_at ? '0' : '1' }}"
                                class="w-full min-h-16 flex items-start gap-3 px-4 py-3 text-left active:bg-primary-soft {{ $n->read_at ? '' : 'bg-primary-soft/50' }}">
                            <span aria-hidden="true" class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full {{ $n->read_at ? 'bg-transparent' : 'bg-primary' }}"></span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-baseline justify-between gap-3">
                                    <span class="text-base {{ $n->read_at ? 'font-medium' : 'font-semibold' }} text-ink">{{ $n->title }}</span>
                                    <span class="shrink-0 text-xs text-ink-soft">{{ $n->created_at->format('H:i') }}</span>
                                </span>
                                <span class="block text-sm text-ink-soft">{{ $n->body }}</span>
                                <span class="mt-1 block text-[11px] font-semibold uppercase tracking-wide text-ink-soft/70">{{ $n->kindLabel() }}</span>
                            </span>
                        </button>
                    @endforeach
                </div>
            </section>
        @endforeach

        @if ($hasMore)
            <div wire:key="more-{{ $limit }}" wire:intersect="more" class="py-2 text-center text-xs text-ink-soft">Se încarcă…</div>
        @endif
    @endif
</div>
