<?php

namespace App\Livewire\Admin\Sales;

use App\Livewire\Admin\Concerns\PicksParticipants;
use App\Models\MenuItem;
use App\Models\Party;
use App\Models\SalesGroup;
use App\Services\ActivityLogger;
use App\Services\SaleRecorder;
use App\Support\PaymentMethods;
use App\Support\Settings\Settings;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (Bar - vanzari)
 *
 * Formular de vanzare din admin: solutie de rezerva (barmanul nu are aplicatia/internet)
 * si mod de testare a fluxului pana exista aplicatia DXA - Bar. Scrie prin SaleRecorder,
 * exact ca API-ul viitor. Nu blocheaza vanzarea la stoc negativ (stocul se posteaza la
 * finalizarea Raportarii, iar acolo negativul e permis si semnalat).
 */
#[Layout('layouts.admin')]
class Form extends Component
{
    use PicksParticipants;

    public string $party_id = '';

    /** [['menu_item_id' => int|null, 'qty' => string], ...] - ramane mereu un rand gol la coada. */
    public array $lines = [];

    /** [['method' => string, 'amount' => string, 'tokens' => string], ...] */
    public array $payments = [];

    public function mount(): void
    {
        abort_unless(Auth::guard('admin')->check(), 403);

        // Cand vii dintr-o sesiune anume (?group=, din filtrul listei), preselectam petrecerea EI —
        // chiar daca nu mai e "curenta" (ex. o sesiune ramasa deschisa de aseara). Altfel, alegem
        // automat: o sesiune deja deschisa (a oricarei petreceri) > petrecerea curenta/urmatoare > nimic.
        $requestedGroup = ($id = request()->integer('group')) ? SalesGroup::open()->find($id) : null;

        $this->party_id = $requestedGroup
            ? (string) ($requestedGroup->party_id ?? '')
            : $this->defaultPartyId();

        $this->lines = [['menu_item_id' => null, 'qty' => '1']];
        $this->payments = [['method' => 'cash', 'amount' => '', 'tokens' => '']];
    }

    /** O sesiune deja deschisa (a oricarei petreceri, chiar incheiata) > petrecerea curenta/urmatoare > "". */
    private function defaultPartyId(): string
    {
        $openWithParty = SalesGroup::open()->whereNotNull('party_id')->orderByDesc('id')->first();
        if ($openWithParty) {
            return (string) $openWithParty->party_id;
        }

        $current = Party::query()
            ->where('status', '!=', 'draft')
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderBy('starts_at')
            ->orderBy('id')
            ->first();

        return $current ? (string) $current->id : '';
    }

    /** Petrecerile de arătat în selector: cele cu o sesiune deschisă (chiar încheiate) + cele neîncheiate încă. */
    private function selectableParties()
    {
        $openPartyIds = SalesGroup::open()->whereNotNull('party_id')->pluck('party_id');

        return Party::query()
            ->where(fn ($q) => $q->whereIn('id', $openPartyIds)
                ->orWhere(fn ($q2) => $q2->where('status', '!=', 'draft')->where('is_active', true)
                    ->where(fn ($q3) => $q3->whereNull('ends_at')->orWhere('ends_at', '>=', now()))))
            ->orderBy('starts_at')
            ->orderBy('id')
            ->limit(30)
            ->get();
    }

    public function usesTokens(): bool
    {
        return isset($this->methods()[PaymentMethods::TOKEN]) && (float) Settings::get('token_rate') > 0;
    }

    /**
     * Metodele de plata alese in formular: cele active in Setari si acceptate de petrecerea sesiunii
     * alese (fara sesiune = toate cele active), plus Beneficiu (voucher, mereu permis).
     *
     * @return array<string, string>
     */
    public function methods(): array
    {
        $party = $this->party_id !== '' ? Party::find((int) $this->party_id) : null;

        return PaymentMethods::forBar($party) + [PaymentMethods::BENEFIT => PaymentMethods::label(PaymentMethods::BENEFIT)];
    }

    public function updated($name): void
    {
        if ($name === 'party_id') {
            $this->resetDisallowedPayments();
        }

        if (preg_match('/^lines\.\d+\.menu_item_id$/', $name)) {
            $this->syncLineRows();
        }
    }

    /** La schimbarea sesiunii, randurile cu o metoda neacceptata la acea petrecere revin pe cash. */
    private function resetDisallowedPayments(): void
    {
        $allowed = $this->methods();

        foreach ($this->payments as $i => $p) {
            if (! isset($allowed[$p['method'] ?? ''])) {
                $this->payments[$i] = ['method' => PaymentMethods::CASH, 'amount' => '', 'tokens' => ''];
            }
        }
    }

    private function syncLineRows(): void
    {
        $last = end($this->lines);

        if ($last === false || ! empty($last['menu_item_id'])) {
            $this->lines[] = ['menu_item_id' => null, 'qty' => '1'];
        }
    }

    public function removeLine(int $i): void
    {
        unset($this->lines[$i]);
        $this->lines = array_values($this->lines);
        $this->syncLineRows();
    }

    public function addPayment(): void
    {
        $this->payments[] = ['method' => 'cash', 'amount' => '', 'tokens' => ''];
    }

    public function removePayment(int $i): void
    {
        unset($this->payments[$i]);
        $this->payments = array_values($this->payments);

        if ($this->payments === []) {
            $this->addPayment();
        }
    }

    /** Pune pe rand restul de plata (lei) - sau tokenii intregi care acopera cel mult restul. */
    public function fillRemaining(int $i): void
    {
        [$total, $paidOthers] = $this->totals($i);
        $remaining = max(0.0, round($total - $paidOthers, 2));

        if (($this->payments[$i]['method'] ?? '') === 'token') {
            $rate = (float) Settings::get('token_rate');
            $this->payments[$i]['tokens'] = $rate > 0 ? (string) (int) floor($remaining / $rate + 1e-9) : '';

            return;
        }

        $this->payments[$i]['amount'] = $remaining > 0 ? number_format($remaining, 2, '.', '') : '';
    }

    /** @return array{0: float, 1: float} [total vanzare, suma platilor (fara randul $except)] */
    private function totals(?int $except = null): array
    {
        $prices = MenuItem::whereIn('id', collect($this->lines)->pluck('menu_item_id')->filter()->map(fn ($v) => (int) $v)->all())
            ->pluck('price', 'id');

        $total = 0.0;
        foreach ($this->lines as $l) {
            if (! empty($l['menu_item_id']) && is_numeric($l['qty'] ?? null) && (float) $l['qty'] > 0) {
                $total += round((float) $l['qty'] * (float) ($prices[(int) $l['menu_item_id']] ?? 0), 2);
            }
        }

        $rate = (float) Settings::get('token_rate');
        $paid = 0.0;
        foreach ($this->payments as $i => $p) {
            if ($i === $except) {
                continue;
            }

            if (($p['method'] ?? '') === 'token') {
                $paid += is_numeric($p['tokens'] ?? null) ? round((int) $p['tokens'] * $rate, 2) : 0.0;
            } else {
                $paid += is_numeric($p['amount'] ?? null) ? round((float) $p['amount'], 2) : 0.0;
            }
        }

        return [round($total, 2), round($paid, 2)];
    }

    public function save(): void
    {
        abort_unless(Auth::guard('admin')->check(), 403);

        $this->resetErrorBag();

        // Petrecerea aleasa: "" = vanzare simpla (ex. apa la un curs). Deschide (sau reia) automat
        // sesiunea ei — nu mai exista un pas separat de "deschide sesiunea".
        $group = null;
        if ($this->party_id !== '') {
            if (! Party::whereKey((int) $this->party_id)->exists()) {
                $this->addError('form', 'Petrecerea aleasă nu mai există.');

                return;
            }

            $hadOpenSession = SalesGroup::open()->where('party_id', (int) $this->party_id)->exists();
            $group = SalesGroup::openFor((int) $this->party_id, Auth::guard('admin')->id());

            if (! $hadOpenSession) {
                ActivityLogger::log('sales.group_created', 'A deschis sesiunea de vânzări „'.$group->label().'".');
            }
        }

        try {
            $sale = SaleRecorder::record(
                $group,
                $this->lines,
                $this->payments,
                source: 'manual',
                adminId: Auth::guard('admin')->id(),
                participantId: $this->participantIds[0] ?? null,   // client identificat (opțional)
            );
        } catch (\DomainException $e) {
            $this->addError('form', $e->getMessage());

            return;
        }

        ActivityLogger::log('sales.sale_created', 'A înregistrat vânzarea #'.$sale->id.' ('.number_format((float) $sale->total, 2, ',', '.').' lei) '.($group ? 'în sesiunea „'.$group->label().'"' : 'fără sesiune').'.');
        session()->flash('status', 'Vânzarea a fost înregistrată.');

        $this->redirectRoute('admin.sales.index', ['group' => $group?->id ?? 'none'], navigate: true);
    }

    public function render()
    {
        [$total, $paid] = $this->totals();

        return view('livewire.admin.sales.form', [
            ...$this->participantPickerData(),
            'parties' => $this->selectableParties(),
            'menuItems' => MenuItem::query()
                ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', collect($this->lines)->pluck('menu_item_id')->filter()->all()))
                ->orderBy('name')
                ->get(),
            'total' => $total,
            'paid' => $paid,
            'remaining' => round($total - $paid, 2),
            'methods' => $this->methods(),
            'tokenRate' => (float) Settings::get('token_rate'),
        ]);
    }
}
