<?php

namespace App\Services;

use App\Models\CreditTransaction;
use App\Models\Order;
use App\Models\Participant;
use App\Models\PartyEntry;
use App\Models\PartyInterest;
use App\Models\Sale;
use App\Models\Ticket;
use App\Models\TokenTransaction;

/**
 * DXA: adaugat (runda 52). Exportul datelor unui participant (JSON), pentru „Descarcă datele mele”. Conține doar ce ține de contul lui:
 * profilul, biletele, comenzile, intrările, creditele, fidelitatea, petrecerile salvate și consumațiile la bar. Nu conține datele personale ale altor persoane
 * (un bilet trimis altcuiva apare fără numele/telefonul celuilalt).
 */
class AccountExport
{
    /** @return array<string, mixed> */
    public static function build(Participant $p): array
    {
        $date = fn ($d) => $d?->toIso8601String();

        return [
            'generated_at' => now()->toIso8601String(),
            'profile' => [
                'name' => $p->name,
                'phone' => $p->phone,
                'created_at' => $date($p->created_at),
                'phone_verified_at' => $date($p->phone_verified_at),
                'has_photo' => $p->hasAvatar(),
            ],
            'credit_balance' => round(CreditLedger::balance($p), 2),
            'credit_transactions' => CreditTransaction::query()->where('participant_id', $p->id)->orderBy('occurred_at')->orderBy('id')->get()
                ->map(fn (CreditTransaction $t) => [
                    'date' => $date($t->occurred_at),
                    'type' => $t->typeLabel(),
                    'source' => $t->sourceLabel(),
                    'amount' => (float) $t->amount,
                    'bonus' => (float) $t->bonus,
                    'note' => $t->note,
                    'cancelled' => $t->isCancelled(),
                ])->all(),
            'orders' => Order::query()->where('participant_id', $p->id)->with('party:id,name')->orderBy('id')->get()
                ->map(fn (Order $o) => [
                    'date' => $date($o->created_at),
                    'party' => $o->party?->name,
                    'tickets' => (int) $o->tickets_count,
                    'total' => (float) $o->total,
                    'payment' => $o->payment_status,
                ])->all(),
            'tickets' => Ticket::query()->where('owner_participant_id', $p->id)->with('party:id,name')->orderBy('id')->get()
                ->map(fn (Ticket $t) => [
                    'party' => $t->party?->name,
                    'type' => $t->ticket_type,
                    'price' => (float) $t->price,
                    'status' => $t->status,
                    'used_at' => $date($t->used_at),
                ])->all(),
            'entries' => PartyEntry::query()->active()->where('participant_id', $p->id)->with('party:id,name')->orderBy('entered_at')->get()
                ->map(fn (PartyEntry $e) => [
                    'date' => $date($e->entered_at),
                    'party' => $e->party?->name,
                    'ticket_type' => $e->ticket_type,
                    'price_paid' => (float) $e->price_paid,
                ])->all(),
            'token_transactions' => TokenTransaction::query()->where('participant_id', $p->id)->whereNull('cancelled_at')->orderBy('occurred_at')->get()
                ->map(fn (TokenTransaction $t) => [
                    'date' => $date($t->occurred_at),
                    'type' => $t->type,
                    'tokens' => (int) $t->tokens,
                    'amount' => (float) $t->amount,
                ])->all(),
            'bar_purchases' => Sale::query()->where('customer_id', $p->id)->where('status', 'completed')->with('lines.menuItem')->orderBy('sold_at')->get()
                ->map(fn (Sale $s) => [
                    'date' => $date($s->sold_at),
                    'total' => (float) $s->total,
                    'items' => $s->lines->map(fn ($l) => ['name' => $l->menuItem?->name, 'qty' => (float) $l->qty, 'total' => (float) $l->total_price])->all(),
                ])->all(),
            'loyalty_cards' => $p->loyaltyCards()->with(['stamps' => fn ($q) => $q->whereNull('voided_at')->orderBy('stamped_at')])->get()
                ->map(fn ($c) => [
                    'status' => $c->status,
                    'stamps_required' => (int) $c->stamps_required,
                    'stamps' => $c->stamps->map(fn ($s) => ['date' => $date($s->stamped_at), 'source' => $s->source])->all(),
                    'completed_at' => $date($c->completed_at),
                ])->all(),
            'saved_parties' => PartyInterest::query()->where('participant_id', $p->id)->with('party:id,name')->get()
                ->map(fn ($i) => $i->party?->name)->filter()->values()->all(),
        ];
    }
}
