<?php

namespace App\Livewire\Admin\Loyalty;

use App\Models\LoyaltyCard;
use App\Models\LoyaltyStamp;
use App\Models\Party;
use App\Models\PartyEntryPayment;
use App\Services\LoyaltyLedger;
use App\Support\PaymentMethods;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * DXA: adaugat (Card de fidelitate — pagina „Carduri", pe modelul App\Livewire\Admin\Reception\Tokens).
 * Sumar global (participanți înrolați, carduri active/complete, ștampile acordate, intrări gratuite acordate),
 * lista „aproape gata" (un cerc distanță de intrarea gratis) și jurnalul filtrabil al tuturor ștampilelor.
 * Doar-afișare — corecțiile rămân, ca și până acum, în fișa fiecărui participant (App\Services\LoyaltyLedger).
 */
#[Layout('layouts.admin')]
class Cards extends Component
{
    #[Url(as: 'petrecere')]
    public string $filterParty = '';

    #[Url(as: 'sursa')]
    public string $filterSource = '';

    public function render()
    {
        $enrolledCount = LoyaltyCard::query()->distinct('participant_id')->count('participant_id');
        $activeCount = LoyaltyCard::query()->where('status', LoyaltyCard::ACTIVE)->count();
        $completedCount = LoyaltyCard::query()->where('status', LoyaltyCard::COMPLETED)->count();

        $stampsFromEntries = LoyaltyStamp::query()->active()->where('source', LoyaltyStamp::SOURCE_ENTRY)->count();
        $stampsManual = LoyaltyStamp::query()->active()->where('source', LoyaltyStamp::SOURCE_MANUAL_ADJUST)->count();

        $freeEntriesGranted = PartyEntryPayment::query()
            ->where('method', PaymentMethods::BENEFIT)
            ->whereHas('entry', fn ($q) => $q->active())
            ->count();

        // „Aproape gata": cardul activ e complet mai puțin ultimul cerc (readyForFreeEntry). Set mic de date —
        // se filtrează în PHP, ca să refolosim exact regula din LoyaltyLedger.
        $almostReady = LoyaltyCard::query()
            ->where('status', LoyaltyCard::ACTIVE)
            ->with('participant')
            ->get()
            ->filter(fn (LoyaltyCard $c) => $c->participant && ! $c->participant->isAnonymized() && $c->activeStampsCount() === $c->stamps_required)
            ->sortBy(fn (LoyaltyCard $c) => $c->participant->name)
            ->values();

        $eligibleParties = Party::query()->where('loyalty_eligible', true)->orderByDesc('start_date')->get(['id', 'name']);

        $sourceOptions = ['' => 'Toate sursele'] + LoyaltyStamp::SOURCE_LABELS;
        $partyOptions = ['' => 'Toate petrecerile'] + $eligibleParties->pluck('name', 'id')->all();

        $stamps = LoyaltyStamp::query()
            ->with(['card.participant', 'partyEntry.party:id,name', 'createdBy:id,name'])
            ->when($this->filterSource !== '', fn ($q) => $q->where('source', $this->filterSource))
            ->when($this->filterParty !== '', fn ($q) => $q->whereHas('partyEntry', fn ($qq) => $qq->where('party_id', $this->filterParty)))
            ->orderByDesc('stamped_at')->orderByDesc('id')
            ->limit(100)
            ->get();

        return view('livewire.admin.loyalty.cards', [
            'loyaltyOn' => LoyaltyLedger::enabled(),
            'stampsRequired' => LoyaltyLedger::stampsRequired(),
            'enrolledCount' => $enrolledCount,
            'activeCount' => $activeCount,
            'completedCount' => $completedCount,
            'stampsFromEntries' => $stampsFromEntries,
            'stampsManual' => $stampsManual,
            'freeEntriesGranted' => $freeEntriesGranted,
            'almostReady' => $almostReady,
            'sourceOptions' => $sourceOptions,
            'partyOptions' => $partyOptions,
            'stamps' => $stamps,
        ]);
    }
}
