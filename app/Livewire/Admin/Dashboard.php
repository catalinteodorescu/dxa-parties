<?php

namespace App\Livewire\Admin;

use App\Models\Admin;
use App\Models\Announcement;
use App\Models\MenuCategory;                // DXA: adaugat (Meniu bar)
use App\Models\MenuItem;                    // DXA: adaugat (Meniu bar - produse)
use App\Models\Party;                       // DXA: adaugat (Petreceri)
use App\Models\StockItem;                   // DXA: adaugat (Bar - stocuri)
use App\Models\StockReport;                 // DXA: adaugat (Bar - raportari)
use App\Models\StockRequisition;            // DXA: adaugat (Bar - necesare)
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class Dashboard extends Component
{
    public function render()
    {
        $now = now();

        $publishedActive = fn () => Announcement::query()
            ->where('status', 'published')
            ->where('is_active', true);

        $liveCount = $publishedActive()
            ->where(fn ($s) => $s->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($s) => $s->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->count();

        $latestList = Announcement::orderByDesc('id')->limit(4)->get();

        // DXA: adaugat (Petreceri)
        $partyPublishedActive = fn () => Party::query()
            ->where('status', 'published')
            ->where('is_active', true);

        $partiesUpcomingList = $partyPublishedActive()
            ->where(fn ($s) => $s->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->orderBy('starts_at')
            ->limit(4)
            ->get();

        $partyUpcomingCount = $partyPublishedActive()
            ->whereNotNull('starts_at')
            ->where('starts_at', '>', $now)
            ->count();

        // DXA: adaugat (Bar - necesare + raportari)
        $requisitionsOpenCount = StockRequisition::where('status', 'open')->count();

        // DXA: adaugat (Bar - necesare, alerta "necesar uitat")
        // Analog draft-ului "uitat" de la Raportari: cel mai vechi necesar
        // deschis, semnalat daca a trecut pragul fara nicio recepție.
        // Prag ales: 5 zile - suficient sa nu declansam alerta pt. un necesar
        // creat ieri si inca in asteptare de la furnizor, dar destul de scurt
        // cat sa prinda unul chiar uitat.
        $oldestOpenRequisition = StockRequisition::where('status', 'open')
            ->orderBy('created_at')
            ->first();
        $requisitionStale = $oldestOpenRequisition
            && $oldestOpenRequisition->created_at->lt($now->copy()->subDays(5));

        $lastReportsList = StockReport::with('party')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(2)
            ->get();
        $lastReport = $lastReportsList->first();

        // Draft "uitat" - are data raportului cu mai mult de 2 zile in urma si tot nu s-a finalizat.
        $lastReportStale = $lastReport && $lastReport->isDraft() && $lastReport->date->lt($now->copy()->subDays(2));

        $reportsLast30Days = StockReport::where('status', 'finalized')
            ->where('date', '>=', $now->copy()->subDays(30))
            ->get();
        $profitLast30Days = round($reportsLast30Days->sum(fn ($r) => $r->totalProfit()), 2);

        // DXA: adaugat (Bar - dashboard, widget comparație ultimele 2 petreceri)
        // Ultimele 2 petreceri (dupa data lor, nu dupa data raportarii) care au
        // cel putin o raportare FINALIZATA legata - vanzari/cost/profit agregate
        // pe toate raportarile finalizate ale fiecareia (de obicei una singura,
        // dar suportam si mai multe). Widget-ul apare doar cand exista cel putin
        // 2 asa petreceri - sub 2 nu are ce compara.
        $partyIdsWithFinalizedReports = StockReport::where('status', 'finalized')
            ->whereNotNull('party_id')
            ->pluck('party_id')
            ->unique();

        $lastTwoPartiesComparison = Party::whereIn('id', $partyIdsWithFinalizedReports)
            ->orderByDesc('starts_at')
            ->limit(2)
            ->get()
            ->map(function (Party $party) {
                $reports = StockReport::where('party_id', $party->id)->where('status', 'finalized')->get();

                $revenue = round($reports->sum(fn ($r) => $r->totalRevenue()), 2);
                $cost = round($reports->sum(fn ($r) => $r->totalCost()), 2);
                $profit = round($revenue - $cost, 2);

                return (object) [
                    'party' => $party,
                    'revenue' => $revenue,
                    'cost' => $cost,
                    'profit' => $profit,
                    'margin' => $revenue > 0 ? round($profit / $revenue * 100, 1) : null,
                ];
            });

        // DXA: adaugat (Bar - stocuri, alertă stoc negativ persistent)
        // Nu reactionam la orice stoc negativ (poate fi un moment tranzitoriu,
        // corectat chiar la raportarea urmatoare) - doar la unul care a ramas
        // asa neintrerupt de cel putin N zile (vezi StockItem::negativeSinceAt()),
        // semn ca lipseste o intrare/corectie si nu doar o secventa normala.
        $negativeStockDaysThreshold = 3;
        $negativeStockItems = StockItem::where('stock_qty', '<', 0)->get()
            ->map(fn ($item) => ['item' => $item, 'since' => $item->negativeSinceAt()])
            ->filter(fn ($row) => $row['since'] && $row['since']->lt($now->copy()->subDays($negativeStockDaysThreshold)))
            ->sortBy(fn ($row) => $row['since'])
            ->values();

        return view('livewire.admin.dashboard', [
            'liveCount' => $liveCount,
            'scheduledCount' => $publishedActive()->whereNotNull('starts_at')->where('starts_at', '>', $now)->count(),
            'draftCount' => Announcement::where('status', 'draft')->count(),
            'latestList' => $latestList,
            'latest' => $latestList->first(),
            'adminsActive' => Admin::where('is_active', true)->count(),
            'adminsTotal' => Admin::count(),
            'currentAdmin' => Auth::guard('admin')->user(),

            // DXA: adaugat (Petreceri)
            'partiesUpcomingList' => $partiesUpcomingList,
            'nextParty' => $partiesUpcomingList->first(),
            'partyUpcomingCount' => $partyUpcomingCount,
            'partyDraftCount' => Party::where('status', 'draft')->count(),

            // DXA: adaugat (Meniu bar)
            'menuCategoriesActiveCount' => MenuCategory::where('is_active', true)->count(),
            'menuCategoriesTotalCount' => MenuCategory::count(),

            // DXA: adaugat (Meniu bar - produse)
            'menuItemsActiveCount' => MenuItem::where('is_active', true)->count(),
            'menuItemsTotalCount' => MenuItem::count(),

            // DXA: adaugat (Bar - stocuri)
            'stockItemsLowCount' => StockItem::whereNotNull('min_stock')
                ->whereColumn('stock_qty', '<=', 'min_stock')
                ->count(),

            // DXA: adaugat (Bar - stocuri, alertă stoc negativ persistent)
            'negativeStockItems' => $negativeStockItems,
            'negativeStockDaysThreshold' => $negativeStockDaysThreshold,

            // DXA: adaugat (Bar - necesare)
            'requisitionsOpenCount' => $requisitionsOpenCount,
            'oldestOpenRequisition' => $oldestOpenRequisition,
            'requisitionStale' => $requisitionStale,

            // DXA: adaugat (Bar - raportari)
            'lastReportsList' => $lastReportsList,
            'lastReport' => $lastReport,
            'lastReportStale' => $lastReportStale,
            'profitLast30Days' => $profitLast30Days,

            // DXA: adaugat (Bar - dashboard, widget comparație ultimele 2 petreceri)
            'lastTwoPartiesComparison' => $lastTwoPartiesComparison,
        ]);
    }
}
