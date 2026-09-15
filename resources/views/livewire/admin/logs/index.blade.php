<div>
    <div class="mb-6">
        <h2 class="text-lg font-semibold text-ink">Jurnal activitate</h2>
        <p class="mt-1 text-sm text-ink-soft">Acțiunile importante din panoul de administrare, cele mai recente primele.</p>
    </div>

    <div class="bg-surface border border-border rounded-2xl overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-border text-left text-ink-soft bg-bg">
                    <th class="px-5 py-3 font-medium whitespace-nowrap">Când</th>
                    <th class="px-5 py-3 font-medium">Cine</th>
                    <th class="px-5 py-3 font-medium">Ce s-a întâmplat</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr class="border-b border-border last:border-0 align-top">
                        <td class="px-5 py-3 text-ink-soft whitespace-nowrap">{{ $log->created_at->format('d.m.Y H:i') }}</td>
                        <td class="px-5 py-3 text-ink whitespace-nowrap">{{ $log->actor_label ?? 'Sistem' }}</td>
                        <td class="px-5 py-3 text-ink">
                            {{ $log->description }}
                            <span class="block mt-0.5 text-xs text-ink-soft/60">{{ $log->action }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-5 py-8 text-center text-ink-soft">Niciun eveniment înregistrat încă.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $logs->links() }}
    </div>
</div>
