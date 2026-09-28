<?php

namespace App\Livewire\Admin\Participants;

use App\Models\Participant;
use App\Models\PartyEntry;
use App\Models\SalePayment;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (Participanți - statistici). Tabloul de ansamblu al participanților ca oameni, nu ca tranzacții
 * (fidelitatea are propria pagină, Carduri; bar/tokeni au paginile lor). Topuri (fideli, cheltuitori, activi
 * recent) + o privire generală (unici vs. intrări, distribuție pe câte intrări are fiecare, participanți noi).
 */
#[Layout('layouts.admin')]
class Stats extends Component
{
    private const TOP_LIMIT = 8;

    public function render()
    {
        $now = now();

        $base = fn () => Participant::query()
            ->whereNull('anonymized_at')
            ->select('participants.*')
            ->selectSub(
                PartyEntry::query()->active()->whereColumn('party_entries.participant_id', 'participants.id')->selectRaw('COUNT(*)'),
                'entries_count'
            )
            ->selectSub(
                PartyEntry::query()->active()->whereColumn('party_entries.participant_id', 'participants.id')->selectRaw('COALESCE(SUM(price_paid), 0)'),
                'entries_amount'
            )
            ->selectSub(
                SalePayment::query()
                    ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
                    ->whereColumn('sales.customer_id', 'participants.id')
                    ->where('sales.status', 'completed')
                    ->where('sale_payments.method', '!=', 'benefit')
                    ->selectRaw('COALESCE(SUM(sale_payments.amount), 0)'),
                'bar_spent'
            );

        // „Cei mai fideli": cele mai multe intrări valabile, all-time.
        // Notă: filtrarea „> 0" se face în PHP, nu prin HAVING pe coloana calculată — SQLite (folosit în teste)
        // nu acceptă HAVING pe o coloană neagregată fără GROUP BY; setul de participanți e mic, deci e ieftin.
        $topLoyal = $base()->orderByDesc('entries_count')->orderBy('name')->get()
            ->filter(fn ($p) => (int) $p->entries_count > 0)
            ->take(self::TOP_LIMIT)->values();

        // „Cei mai mari cheltuitori": intrări + bar (fără beneficii — nu-s cheltuială), all-time.
        $topSpenders = $base()->get()
            ->filter(fn ($p) => ((float) $p->entries_amount + (float) $p->bar_spent) > 0)
            ->sortByDesc(fn ($p) => (float) $p->entries_amount + (float) $p->bar_spent)
            ->take(self::TOP_LIMIT)->values();

        // „Cei mai activi recent": cele mai multe intrări în ultimele 3 luni.
        $since = $now->copy()->subMonths(3);
        $topRecent = Participant::query()
            ->whereNull('anonymized_at')
            ->select('participants.*')
            ->selectSub(
                PartyEntry::query()->active()->where('entered_at', '>=', $since)
                    ->whereColumn('party_entries.participant_id', 'participants.id')->selectRaw('COUNT(*)'),
                'recent_count'
            )
            ->orderByDesc('recent_count')->orderBy('name')->get()
            ->filter(fn ($p) => (int) $p->recent_count > 0)
            ->take(self::TOP_LIMIT)->values();

        // Privire generală.
        $totalParticipants = Participant::query()->whereNull('anonymized_at')->count();
        $totalIdentifiedEntries = PartyEntry::query()->active()->whereNotNull('participant_id')->count();
        $participantsWithEntries = Participant::query()->whereNull('anonymized_at')->whereHas('entries', fn ($q) => $q->active())->count();

        // Distribuție: câți participanți (cu ≥1 intrare) au 1 / 2-5 / 6+ intrări valabile.
        $counts = Participant::query()
            ->whereNull('anonymized_at')
            ->withCount(['entries' => fn ($q) => $q->active()])
            ->get()
            ->pluck('entries_count')
            ->filter(fn ($n) => $n > 0);
        $distribution = [
            '1' => $counts->filter(fn ($n) => $n === 1)->count(),
            '2-5' => $counts->filter(fn ($n) => $n >= 2 && $n <= 5)->count(),
            '6+' => $counts->filter(fn ($n) => $n >= 6)->count(),
        ];

        $thisMonthStart = $now->copy()->startOfMonth();
        $lastMonthStart = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $newThisMonth = Participant::query()->where('created_at', '>=', $thisMonthStart)->count();
        $newLastMonth = Participant::query()->where('created_at', '>=', $lastMonthStart)->where('created_at', '<', $thisMonthStart)->count();

        return view('livewire.admin.participants.stats', [
            'topLoyal' => $topLoyal,
            'topSpenders' => $topSpenders,
            'topRecent' => $topRecent,
            'totalParticipants' => $totalParticipants,
            'totalIdentifiedEntries' => $totalIdentifiedEntries,
            'participantsWithEntries' => $participantsWithEntries,
            'distribution' => $distribution,
            'newThisMonth' => $newThisMonth,
            'newLastMonth' => $newLastMonth,
        ]);
    }
}
