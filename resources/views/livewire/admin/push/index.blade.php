{{-- DXA: adaugat (runda 70). Ultimele notificări + KPI (deschisă din Setări › Aplicație participanți). Variabile: $enabled, $lastRun, $subscribers, $devices, $totalParticipants, $sent7, $delivered7, $byKind, $kindOptions, $items. --}}
<div class="max-w-4xl space-y-5">
    <div>
        <a href="{{ route('admin.settings.participant-app') }}" wire:navigate class="text-xs font-medium text-ink-soft hover:text-ink">← Setări · Aplicație participanți</a>
        <h2 class="mt-1 text-xl font-semibold text-ink">Ultimele notificări</h2>
        <p class="mt-1 text-sm text-ink-soft">Participanții activează notificările o singură dată, din Cont în aplicație, cu un singur întrerupător. Ce tipuri se trimit alegi din Setări › Aplicație participanți › Notificări.</p>
    </div>

    @unless ($enabled)
        <div class="rounded-xl border border-warning/30 bg-warning/10 px-4 py-3 text-sm text-ink" data-push-off>
            Notificările nu sunt configurate. Generează cheile cu <span class="font-mono">php artisan app:push-keys</span> și pune-le în <span class="font-mono">.env</span> (VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY, VAPID_SUBJECT).
        </div>
    @endunless

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-xl border border-border bg-white p-4"><div class="text-xs text-ink-soft">Participanți abonați</div><div class="mt-1 text-2xl font-semibold text-ink" data-push-subscribers>{{ $subscribers }}</div><div class="text-xs text-ink-soft">din {{ $totalParticipants }} cu cont</div></div>
        <div class="rounded-xl border border-border bg-white p-4"><div class="text-xs text-ink-soft">Dispozitive</div><div class="mt-1 text-2xl font-semibold text-ink">{{ $devices }}</div></div>
        <div class="rounded-xl border border-border bg-white p-4"><div class="text-xs text-ink-soft">Trimise (7 zile)</div><div class="mt-1 text-2xl font-semibold text-ink">{{ $sent7 }}</div></div>
        <div class="rounded-xl border border-border bg-white p-4"><div class="text-xs text-ink-soft">Livrate pe dispozitiv (7 zile)</div><div class="mt-1 text-2xl font-semibold text-ink">{{ $delivered7 }}</div></div>
    </div>

    <div class="text-xs text-ink-soft">
        Ultima rulare a trimiterilor programate: <span data-push-lastrun>{{ $lastRun ? \Illuminate\Support\Carbon::parse($lastRun)->format('d.m.Y H:i') : 'niciodată (verifică cron-ul)' }}</span>.
        @if ($byKind)
            Pe tipuri (7 zile): {{ collect($byKind)->map(fn ($n, $k) => (\App\Models\PushNotification::KIND_LABELS[$k] ?? $k).' '.$n)->implode(' · ') }}.
        @endif
    </div>

    <div class="rounded-xl border border-border bg-white">
        <div class="flex items-center justify-between gap-3 border-b border-border px-4 py-3">
            <h3 class="text-sm font-semibold text-ink">Ultimele notificări</h3>
            <x-select wire:model="kind" live class="w-52" :options="$kindOptions" />
        </div>
        <div class="divide-y divide-border">
            @forelse ($items as $n)
                <div class="px-4 py-3 text-sm" wire:key="push-{{ $n->id }}" data-push-row>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="font-medium text-ink">{{ $n->title }} <span class="ml-1 rounded-full bg-bg px-2 py-0.5 text-[10px] font-semibold text-ink-soft">{{ $n->kindLabel() }}</span></div>
                            <div class="text-ink-soft">{{ $n->body }}</div>
                            <div class="mt-0.5 text-xs text-ink-soft/80">Către {{ $n->participant?->label() ?? '—' }}</div>
                        </div>
                        <div class="shrink-0 text-right text-xs text-ink-soft">
                            <div>{{ $n->created_at?->format('d.m.Y H:i') }}</div>
                            <div>{{ $n->delivered_count }}/{{ $n->deliveries_count }} livrate</div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="px-4 py-8 text-center text-sm text-ink-soft">Nicio notificare trimisă încă.</div>
            @endforelse
        </div>
    </div>

    {{ $items->links() }}
</div>
