<?php

namespace App\Livewire\Admin\Reconciliation;

use App\Models\BarReport;
use App\Models\Party;
use App\Models\ReceptionReport;
use App\Services\DiscountCodes;
use App\Support\PaymentMethods;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * DXA: adaugat (Reconciliere). Pe o petrecere, recepția și barul la un loc: cash așteptat / numărat / diferență din toate
 * raportările de casă, tokenii (vânduți la recepție, primiți la bar, numărați) și creditele (vândute la recepție,
 * cheltuite la bar). Cifrele vin din figures() ale raportărilor; cele nefinalizate/netrimise se marchează „provizoriu”.
 */
#[Layout('layouts.admin')]
class Index extends Component
{
    #[Url]
    public string $party = '';

    public function mount(): void
    {
        // Petrecerea afișată e și cea selectată în listă: implicit, cea mai recentă cu raportări.
        if ($this->party === '') {
            $ids = ReceptionReport::query()->whereNotNull('party_id')->pluck('party_id')
                ->merge(BarReport::query()->whereNotNull('party_id')->pluck('party_id'))->unique();
            $first = Party::whereIn('id', $ids)->orderByDesc('starts_at')->orderByDesc('id')->first();
            $this->party = $first ? (string) $first->id : '';
        }
    }

    public function render()
    {
        $partyIds = ReceptionReport::query()->whereNotNull('party_id')->pluck('party_id')
            ->merge(BarReport::query()->whereNotNull('party_id')->pluck('party_id'))->unique()->values();
        $parties = Party::whereIn('id', $partyIds)->orderByDesc('starts_at')->orderByDesc('id')->get();

        $selected = $parties->firstWhere('id', (int) $this->party) ?? $parties->first();

        $reception = collect();
        $bar = collect();
        $tot = null;
        $discounts = collect();

        if ($selected) {
            $discounts = DiscountCodes::grantedFor($selected); // DXA: adaugat (Coduri de reducere)
            $reception = ReceptionReport::query()->where('party_id', $selected->id)->with('session')->orderBy('id')->get()
                ->map(fn (ReceptionReport $r) => (object) ['model' => $r, 'fig' => $r->figures(), 'final' => $r->isFinalized() || $r->isSubmitted()]);
            $bar = BarReport::query()->where('party_id', $selected->id)->with('group')->orderBy('id')->get()
                ->map(fn (BarReport $r) => (object) ['model' => $r, 'fig' => $r->figures(), 'final' => $r->isSubmitted() || ! ($r->group?->isOpen() ?? false)]);

            $counted = fn ($rows) => $rows->filter(fn ($x) => $x->fig->counted_cash !== null);

            $barCredit = round($bar->sum(fn ($x) => (float) (collect($x->fig->methods)->firstWhere('key', PaymentMethods::CREDIT)['amount'] ?? 0)), 2);

            $tot = (object) [
                'expected_cash' => round($counted($reception)->sum(fn ($x) => $x->fig->expected_cash) + $counted($bar)->sum(fn ($x) => $x->fig->expected_cash), 2),
                'counted_cash' => round($counted($reception)->sum(fn ($x) => $x->fig->counted_cash) + $counted($bar)->sum(fn ($x) => $x->fig->counted_cash), 2),
                'tokens_sold' => (int) $reception->sum(fn ($x) => $x->fig->tokens_sold),
                'tokens_received' => (int) $bar->sum(fn ($x) => $x->fig->tokens_received),
                'tokens_diff' => (int) $reception->sum(fn ($x) => (int) $x->fig->tokens_diff) + (int) $bar->sum(fn ($x) => (int) $x->fig->tokens_diff),
                'credits_sold' => round($reception->sum(fn ($x) => (float) $x->fig->credits_amount), 2),
                'credits_spent' => $barCredit,
                'provisional' => $reception->contains(fn ($x) => ! $x->final) || $bar->contains(fn ($x) => ! $x->final),
            ];
            $tot->cash_diff = round($tot->counted_cash - $tot->expected_cash, 2);
        }

        return view('livewire.admin.reconciliation.index', [
            'parties' => $parties,
            'selected' => $selected,
            'reception' => $reception,
            'bar' => $bar,
            'tot' => $tot,
            'discounts' => $discounts,
        ]);
    }
}
