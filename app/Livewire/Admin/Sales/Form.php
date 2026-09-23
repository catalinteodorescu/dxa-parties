<?php

namespace App\Livewire\Admin\Sales;

use App\Models\MenuItem;
use App\Models\SalePayment;
use App\Models\SalesGroup;
use App\Services\ActivityLogger;
use App\Services\SaleRecorder;
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
    public string $sales_group_id = '';

    /** [['menu_item_id' => int|null, 'qty' => string], ...] - ramane mereu un rand gol la coada. */
    public array $lines = [];

    /** [['method' => string, 'amount' => string, 'tokens' => string], ...] */
    public array $payments = [];

    public function mount(): void
    {
        abort_unless(Auth::guard('admin')->check(), 403);

        // Implicit: vanzare simpla (fara sesiune). Cand vii dintr-o sesiune (?group=), o preselectam.
        $requested = request()->integer('group');
        $group = $requested ? SalesGroup::open()->find($requested) : null;

        $this->sales_group_id = $group ? (string) $group->id : '';
        $this->lines = [['menu_item_id' => null, 'qty' => '1']];
        $this->payments = [['method' => 'cash', 'amount' => '', 'tokens' => '']];
    }

    public function usesTokens(): bool
    {
        return (bool) Settings::get('uses_tokens') && (float) Settings::get('token_rate') > 0;
    }

    public function updated($name): void
    {
        if (preg_match('/^lines\.\d+\.menu_item_id$/', $name)) {
            $this->syncLineRows();
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

        // Sesiunea de vanzari e optionala: '' = vanzare simpla (ex. apa la un curs).
        $group = null;
        if ($this->sales_group_id !== '') {
            $group = SalesGroup::open()->with('party')->find((int) $this->sales_group_id);

            if (! $group) {
                $this->addError('form', 'Sesiunea aleasă nu mai există sau e închisă.');

                return;
            }
        }

        try {
            $sale = SaleRecorder::record(
                $group,
                $this->lines,
                $this->payments,
                source: 'manual',
                adminId: Auth::guard('admin')->id(),
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

        $methods = SalePayment::METHODS;
        if (! $this->usesTokens()) {
            unset($methods['token']);
        }

        return view('livewire.admin.sales.form', [
            'groups' => SalesGroup::with('party')->open()->orderByDesc('id')->get(),
            'menuItems' => MenuItem::query()
                ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', collect($this->lines)->pluck('menu_item_id')->filter()->all()))
                ->orderBy('name')
                ->get(),
            'total' => $total,
            'paid' => $paid,
            'remaining' => round($total - $paid, 2),
            'methods' => $methods,
            'tokenRate' => (float) Settings::get('token_rate'),
        ]);
    }
}
