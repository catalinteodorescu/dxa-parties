<?php

namespace App\Livewire\Bar;

use App\Livewire\Admin\Concerns\PicksParticipants;
use App\Livewire\Bar\Concerns\UsesBarParty;
use App\Livewire\Concerns\GuardsResubmit;
use App\Models\MenuItem;
use App\Models\Party;
use App\Models\SalePayment;
use App\Models\SalesGroup;
use App\Services\ActivityLogger;
use App\Services\SaleRecorder;
use App\Support\PaymentMethods;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (PWA Bar - Vânzare). Ecranul de vânzare al barmanului: participant (opțional, cu scanare QR), produse alese
 * dintr-un select sau adăugate rapid din cele mai vândute, plata pe total (poate fi mixtă). Aceleași reguli ca în formularul
 * din admin: scrie prin App\Services\SaleRecorder (metodele acceptate la petrecere, credite doar cu participant, blocat cât
 * raportarea sesiunii e trimisă). Sesiunea de vânzări a petrecerii se deschide singură la prima vânzare.
 */
#[Layout('layouts.bar')]
class Sale extends Component
{
    use GuardsResubmit;
    use PicksParticipants {
        scanParticipant as private scanPicked;
    }
    use UsesBarParty;

    /** Numărul maxim de bucăți dintr-un produs pe un bon (ca să nu se apese din greșeală prea mult). */
    private const MAX_QTY = 99;

    /** Produsele bonului: menu_item_id => cantitate. */
    public array $cart = [];

    /** Valoarea select-ului de produse (se golește imediat după ce produsul e adăugat). */
    public string $pick = '';

    /** @var array<int, array{method: string, amount: string, tokens: string}> plata pe TOTALUL bonului */
    public array $payments = [['method' => 'cash', 'amount' => '', 'tokens' => '']];

    /** Cum a fost identificat participantul: qr (scanat) | phone (căutat). */
    public string $identifiedBy = 'phone';

    public ?string $error = null;

    /** Confirmarea de după vânzare (ecran plin): title, line, total, note. */
    public ?array $done = null;

    // ---- Participant -------------------------------------------------------

    protected function participantsChanged(): void
    {
        $this->identifiedBy = 'phone';
    }

    /** Scanarea alege participantul (ca selecția manuală) și îl marchează „identificat prin QR”. */
    public function scanParticipant(string $code): array
    {
        $result = $this->scanPicked($code);

        if ($result['ok']) {
            $this->identifiedBy = 'qr';
        }

        return $result;
    }

    // ---- Bon (produse) -----------------------------------------------------

    public function updatedPick(string $value): void
    {
        if ($value !== '') {
            $this->addItem((int) $value);
        }

        $this->pick = '';
    }

    public function addItem(int $id): void
    {
        $this->error = null;

        if (! MenuItem::query()->active()->whereKey($id)->exists()) {
            $this->error = 'Produsul nu mai e disponibil.';

            return;
        }

        $this->cart[$id] = min(self::MAX_QTY, (int) ($this->cart[$id] ?? 0) + 1);
    }

    public function stepQty(int $id, int $delta): void
    {
        if (! isset($this->cart[$id])) {
            return;
        }

        $qty = (int) $this->cart[$id] + $delta;

        if ($qty <= 0) {
            unset($this->cart[$id]);
        } else {
            $this->cart[$id] = min(self::MAX_QTY, $qty);
        }
    }

    public function removeItem(int $id): void
    {
        unset($this->cart[$id]);
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->error = null;
    }

    /** @return Collection<int, MenuItem> produsele din bon, cheie = id */
    private function cartItems()
    {
        return $this->cart
            ? MenuItem::query()->whereIn('id', array_keys($this->cart))->get()->keyBy('id')
            : collect();
    }

    private function totalCents(): int
    {
        $items = $this->cartItems();
        $cents = 0;

        foreach ($this->cart as $id => $qty) {
            if ($item = $items->get((int) $id)) {
                $cents += (int) round((int) $qty * (float) $item->price * 100);
            }
        }

        return $cents;
    }

    // ---- Plată -------------------------------------------------------------

    /** Metodele acceptate la bar la petrecerea aleasă (cele active în Setări și bifate pe petrecere). */
    private function saleMethods(?Party $party): array
    {
        return PaymentMethods::forBar($party);
    }

    private function tokensRow(array $p): bool
    {
        return ($p['method'] ?? '') === PaymentMethods::TOKEN;
    }

    private function rowCents(array $p): int
    {
        if ($this->tokensRow($p)) {
            $tokens = (int) ($p['tokens'] ?? 0);

            return $tokens > 0 ? (int) round($tokens * PaymentMethods::tokenRate() * 100) : 0;
        }

        $raw = str_replace(',', '.', trim((string) ($p['amount'] ?? '')));

        return is_numeric($raw) && (float) $raw > 0 ? (int) round((float) $raw * 100) : 0;
    }

    private function paidCents(?int $except = null): int
    {
        $paid = 0;
        foreach ($this->payments as $i => $p) {
            if ($i !== $except) {
                $paid += $this->rowCents($p);
            }
        }

        return $paid;
    }

    private function fmtAmount(int $cents): string
    {
        return $cents > 0 ? ($cents % 100 === 0 ? (string) intdiv($cents, 100) : number_format($cents / 100, 2, '.', '')) : '';
    }

    /** Pune pe rând restul de plată (lei) — sau tokenii întregi care acoperă cel mult restul. */
    public function fillRemaining(int $i): void
    {
        $remaining = max(0, $this->totalCents() - $this->paidCents($i));

        if ($this->tokensRow($this->payments[$i] ?? [])) {
            $rate = PaymentMethods::tokenRate();
            $this->payments[$i]['tokens'] = $rate > 0 ? (string) (int) floor($remaining / 100 / $rate + 1e-9) : '';

            return;
        }

        $this->payments[$i]['amount'] = $this->fmtAmount($remaining);
    }

    public function addPayment(): void
    {
        $party = $this->currentParty();
        $this->payments[] = ['method' => array_key_first($this->saleMethods($party)) ?? 'cash', 'amount' => '', 'tokens' => ''];
    }

    public function removePayment(int $i): void
    {
        unset($this->payments[$i]);
        $this->payments = array_values($this->payments) ?: [['method' => 'cash', 'amount' => '', 'tokens' => '']];
    }

    /** „Tot cu …”: o singură plată pe tot totalul (la tokeni, tokenii întregi care acoperă cel mult totalul). */
    public function payAll(string $method): void
    {
        $row = ['method' => $method, 'amount' => '', 'tokens' => ''];

        if ($method === PaymentMethods::TOKEN) {
            $rate = PaymentMethods::tokenRate();
            $row['tokens'] = $rate > 0 ? (string) (int) floor($this->totalCents() / 100 / $rate + 1e-9) : '';
        } else {
            $row['amount'] = $this->fmtAmount($this->totalCents());
        }

        $this->payments = [$row];
    }

    /**
     * Cele mai folosite 2 metode de plată la bar (din vânzările finalizate) pentru butoanele „Tot cu…”; metodele încă
     * nefolosite completează după ordinea din Setări. „Beneficiu” nu intră (nu e o plată reală).
     *
     * @param  array<string, string>  $methods  cheie => etichetă
     * @return array<string, string>
     */
    private function topMethods(array $methods, int $limit = 2): array
    {
        $ranked = SalePayment::query()
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->where('sales.status', 'completed')
            ->groupBy('sale_payments.method')
            ->orderByRaw('COUNT(*) DESC')
            ->orderByRaw('SUM(sale_payments.amount) DESC')
            ->pluck('sale_payments.method')
            ->all();

        $candidates = array_diff_key($methods, [PaymentMethods::BENEFIT => true]);
        $order = array_merge(
            array_values(array_intersect($ranked, array_keys($candidates))),
            array_values(array_diff(array_keys($candidates), $ranked)),
        );

        $out = [];
        foreach (array_slice($order, 0, $limit) as $key) {
            $out[$key] = $candidates[$key];
        }

        return $out;
    }

    // ---- Vânzarea ------------------------------------------------------------

    public function dismissDone(): void
    {
        $this->done = null;
    }

    public function sell(): void
    {
        $this->error = null;
        $this->participantError = null;
        $party = $this->currentParty();

        try {
            if (! $party) {
                throw new DomainException('Alege o petrecere.');
            }
            if ($this->cart === []) {
                throw new DomainException('Adaugă cel puțin un produs.');
            }

            $adminId = Auth::guard('admin')->id();
            $lines = [];
            foreach ($this->cart as $id => $qty) {
                $lines[] = ['menu_item_id' => (int) $id, 'qty' => (int) $qty];
            }

            $hadSession = SalesGroup::open()->where('party_id', $party->id)->exists();

            // Sesiunea se deschide și vânzarea se înregistrează în aceeași tranzacție: dacă vânzarea e respinsă
            // (plată greșită, raportare trimisă), nu rămâne o sesiune goală deschisă.
            $sale = $this->once(fn () => DB::transaction(function () use ($party, $lines, $adminId) {
                $group = SalesGroup::openFor($party->id, $adminId);

                return SaleRecorder::record(
                    $group,
                    $lines,
                    $this->payments,
                    source: 'app',
                    adminId: $adminId,
                    extra: ['bartender_id' => $adminId],
                    participantId: $this->participantIds[0] ?? null,
                    identifiedBy: $this->identifiedBy,
                );
            }));

            if (! $hadSession) {
                ActivityLogger::log('sales.group_created', 'A deschis sesiunea de vânzări „'.$sale->group->label().'" din aplicația de bar.');
            }
            ActivityLogger::log('sales.sale_created', 'A înregistrat vânzarea #'.$sale->id.' ('.number_format((float) $sale->total, 2, ',', '.').' lei) în sesiunea „'.$sale->group->label().'" din aplicația de bar.');

            $count = (int) array_sum($this->cart);
            $this->done = [
                'title' => 'Vânzare înregistrată',
                'line' => $count.' '.($count === 1 ? 'produs' : 'produse'),
                'total' => number_format((float) $sale->total, 2, ',', '.'),
                'note' => $hadSession ? null : 'A început o sesiune de vânzări.',
            ];

            $this->cart = [];
            $this->pick = '';
            $this->payments = [['method' => 'cash', 'amount' => '', 'tokens' => '']];
            $this->identifiedBy = 'phone';
            $this->resetParticipants();
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render()
    {
        $party = $this->currentParty();
        $methods = $this->saleMethods($party);
        // Runda 57: cash nu mai e forțat la bar; un rând cu o metodă neacceptată trece pe prima metodă acceptată.
        foreach ($this->payments as $i => $p) {
            if ($methods && ! isset($methods[$p['method'] ?? ''])) {
                $this->payments[$i]['method'] = array_key_first($methods);
            }
        }
        $items = $this->cartItems();
        $total = $this->totalCents();
        $paid = $this->paidCents();

        $active = MenuItem::query()->active()->orderBy('name')->get(['id', 'name', 'price']);
        $money = fn ($n) => number_format((float) $n, 2, ',', '.');

        $topIds = MenuItem::topSellingIds(8);
        $byId = $active->keyBy('id');
        $quickItems = collect($topIds)->map(fn ($id) => $byId->get($id))->filter()->values();

        return view('livewire.bar.sale', [
            ...$this->participantPickerData($party),
            'party' => $party,
            'productOptions' => $active->mapWithKeys(fn ($m) => [$m->id => $m->name.' · '.$money($m->price).' lei'])->all(),
            'quickItems' => $quickItems,
            'cartItems' => $items,
            'methods' => $methods,
            'topMethods' => $this->topMethods($methods),
            'totalTokens' => PaymentMethods::isEnabled(PaymentMethods::TOKEN) && PaymentMethods::tokenRate() > 0 ? round($total / 100 / PaymentMethods::tokenRate(), 1) : null,
            'tokenRate' => PaymentMethods::tokenRate(),
            'total' => $total / 100,
            'paid' => $paid / 100,
            'rest' => ($total - $paid) / 100,
        ]);
    }
}
