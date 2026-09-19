<?php

namespace App\Livewire\Admin;

use App\Models\Admin;
use App\Models\Announcement;
use App\Models\MenuCategory;                // DXA: adaugat (Meniu bar)
use App\Models\MenuItem;                    // DXA: adaugat (Meniu bar - produse)
use App\Models\Party;                       // DXA: adaugat (Petreceri)
use App\Models\StockItem;                   // DXA: adaugat (Bar - stocuri)
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
        ]);
    }
}
