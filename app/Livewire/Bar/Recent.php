<?php

namespace App\Livewire\Bar;

use App\Livewire\Bar\Concerns\UsesBarParty;
use App\Models\Sale;
use App\Models\SalesGroup;
use App\Services\ActivityLogger;
use App\Support\PaymentMethods;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (PWA Bar - Vânzări recente). Bonurile sesiunii de vânzări DESCHISE a petrecerii alese, cele mai noi primele,
 * cu anulare (motiv obligatoriu). Regulile de anulare sunt ale modelului Sale (nu merge după raportarea trimisă sau
 * după raportarea de stoc); creditele plătite se returnează în portofel. Doar bonurile sesiunii curente se pot anula de aici.
 */
#[Layout('layouts.bar')]
class Recent extends Component
{
    use UsesBarParty;

    public ?string $message = null;

    public ?string $error = null;

    public function cancelSale(int $id, string $reason): void
    {
        $this->message = $this->error = null;

        try {
            $reason = trim($reason);
            if (mb_strlen($reason) < 3) {
                throw new DomainException('Scrie motivul anulării.');
            }

            $session = $this->session();
            $sale = $session?->sales()->with('group.barReport')->whereKey($id)->first();
            if (! $sale) {
                throw new DomainException('Bonul nu aparține sesiunii deschise.');
            }

            $sale->cancel(Auth::guard('admin')->id(), mb_substr($reason, 0, 255));

            ActivityLogger::log('sales.sale_cancelled', 'A anulat vânzarea #'.$sale->id.' ('.number_format((float) $sale->total, 2, ',', '.').' lei) din aplicația de bar: '.mb_substr($reason, 0, 255));
            $this->message = 'Bonul a fost anulat.';
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    private function session(): ?SalesGroup
    {
        return $this->partyId
            ? SalesGroup::open()->where('party_id', $this->partyId)->orderBy('id')->with('barReport')->first()
            : null;
    }

    public function render()
    {
        $party = $this->currentParty();
        $session = $this->session();
        $labels = PaymentMethods::labels();

        $sales = $session
            ? $session->sales()->with(['lines.menuItem', 'payments', 'customer'])->orderByDesc('sold_at')->orderByDesc('id')->limit(100)->get()
            : collect();

        $rows = $sales->map(function (Sale $s) use ($labels, $session) {
            $s->setRelation('group', $session);
            $by = [];
            foreach ($s->payments as $p) {
                $by[$p->method] = round(($by[$p->method] ?? 0) + (float) $p->amount, 2);
            }

            return (object) [
                'id' => $s->id,
                'at' => $s->sold_at,
                'total' => (float) $s->total,
                'items' => $s->lines->map(fn ($l) => ((float) $l->qty == (int) $l->qty ? (int) $l->qty : (float) $l->qty).' × '.($l->menuItem?->name ?? '—'))->implode(', '),
                'methods' => collect($by)->map(fn ($a, $m) => ($labels[$m] ?? $m).' '.number_format($a, 2, ',', '.'))->implode(' · '),
                'customer' => $s->customer?->label(),
                'cancelled' => $s->isCancelled(),
                'cancel_reason' => $s->cancel_reason,
                'can_cancel' => $s->canBeCancelled(),
            ];
        });

        return view('livewire.bar.recent', [
            'party' => $party,
            'session' => $session,
            'rows' => $rows,
            'reportSubmitted' => $session?->reportSubmitted() ?? false,
        ]);
    }
}
