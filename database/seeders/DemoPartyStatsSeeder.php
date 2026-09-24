<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\MenuItem;
use App\Models\Party;
use App\Models\Sale;
use App\Models\SalesGroup;
use App\Models\StockItem;
use App\Models\StockReport;
use App\Services\SaleRecorder;
use App\Support\Settings\Settings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * DXA: adaugat — date DEMO pentru testarea paginii „Statistici” a petrecerii (comparatie, curba pe ore,
 * plati, produse, „inregistrat de”, inventar, vanzari neraportate, stare goala).
 *
 * Datele se creeaza PRIN CODUL REAL, nu prin INSERT-uri: vanzarile prin SaleRecorder (bonuri cu ora,
 * plati cash/token/credit/beneficiu, mai multi „barmani”), iar raportarile prin StockReport::addEntry /
 * addSale / addLoss / finalize() — deci stocul (stock_qty / avg_cost), miscarile, numaratoarea de final
 * (cash, tokeni, inventar, aliniere) si numerotarea raportarilor sunt consistente ca la datele reale.
 * ATENTIE: seederul modifica stocul curent (adauga intrari si consuma retete pe produsele din meniu) —
 * fa o copie a bazei inainte:  cp database/database.sqlite database/database.sqlite.bak
 *
 * Ce creeaza (toate TRECUTE, nume terminate in „— Demo statistici N”):
 *  1. Salsa Night (Simpla, 08.08)      — 1 raportare, numaratoare cu diferente (cash, tokeni, produse), o vanzare anulata,
 *                                        o linie manuala (fara bon/ora) si o pierdere manuala
 *  2. Bachata Night (Simpla, 22.08)    — 1 raportare, numaratoare FARA diferente
 *  3. Kizomba Night (Simpla, 05.09)    — 1 raportare, lipsa la cash, + 4 vanzari NEraportate intr-o sesiune deschisa (banner)
 *  4. Latin Festival (Festival, 25–26.07) — 2 raportari finalizate (cifrele se aduna)
 *  5. Summer Camp (Festival, 12.09)    — 1 raportare mare, surplus la cash
 *  6. Doar draft (Simpla, 10.09)       — doar o raportare DRAFT => stare goala pe pagina de statistici
 * Comparatia implicita (precedenta de acelasi tip cu raportari): 2 -> 1, 3 -> 2, 6 -> 3; petrecerile 1 si 4 se compara cu ultimele
 * petreceri din DemoPartyHistorySeeder (daca l-ai rulat), iar 5 -> 4; petrecerea 6 nu are date (stare goala).
 * Produsele fara reteta (ex. Ursus) au cost necunoscut => apare nota „cost necunoscut” si profit „—” la produs.
 *
 * Idempotent: o petrecere cu acelasi nume si deja legata de o raportare se sare — sigur de rulat de mai multe ori.
 * Foloseste conturile de admin ACTIVE existente ca „barmani” (daca sunt mai putin de 2, creeaza conturi demo INACTIVE).
 * Daca cursul token → lei nu e setat, il seteaza la 5 (si activeaza tokenii), ca sa existe plati in tokeni.
 * Rand aleatoriu cu seed fix => aceleasi date la fiecare rulare pe o baza curata.
 *
 * Rulare (din radacina proiectului, pe Herd):
 *   php artisan db:seed --class="Database\Seeders\DemoPartyStatsSeeder"
 */
class DemoPartyStatsSeeder extends Seeder
{
    /** @var \Illuminate\Support\Collection<int, Admin> */
    private $staff;

    private $menu;

    private float $rate = 5.0;

    public function run(): void
    {
        mt_srand(20260924);

        $this->menu = MenuItem::query()->with('recipeLines.stockItem')->where('is_active', true)->where('price', '>', 0)->orderBy('id')->get();

        if ($this->menu->count() < 2) {
            $this->command?->error('Sunt necesare cel puțin 2 produse active în meniu — abandonez.');

            return;
        }

        $this->staff = $this->staff();
        $this->ensureTokens();

        foreach ($this->definitions() as $def) {
            $party = Party::firstOrCreate(
                ['name' => $def['name']],
                [
                    'kind' => $def['kind'],
                    'status' => 'published',
                    'start_date' => $def['date'],
                    'end_date' => $def['end_date'] ?? null,
                    'start_time' => '21:00',
                    'end_time' => '03:00',
                    'is_active' => true,
                    'audience' => 'all',
                    'created_by' => $this->staff->first()->id,
                ]
            );

            if (StockReport::where('party_id', $party->id)->exists()) {
                $this->command?->info('Sar peste „'.$def['name'].'” — are deja raportări legate.');

                continue;
            }

            DB::transaction(function () use ($party, $def) {
                foreach ($def['reports'] as $rep) {
                    $this->seedReport($party, $rep);
                }

                if (! empty($def['unreported'])) {
                    $group = SalesGroup::create(['party_id' => $party->id, 'status' => 'open', 'created_by' => $this->staff->first()->id]);
                    $this->makeBons($group, Carbon::parse($def['date']), [23 => $def['unreported']], 1);
                }
            });

            $this->command?->info('Creat „'.$def['name'].'” ('.$def['kind'].', '.$def['date'].').');
        }
    }

    /** Petrecerile de creat. 'profile' = [ora => greutate] (orele 0–11 sunt dupa miezul noptii); 'scale' inmulteste numarul de bonuri. */
    private function definitions(): array
    {
        return [
            [
                'name' => 'Salsa Night — Demo statistici 1', 'kind' => 'basic', 'date' => '2026-08-08',
                'reports' => [[
                    'date' => '2026-08-08', 'scale' => 1.0, 'profile' => [21 => 3, 22 => 6, 23 => 9, 0 => 8, 1 => 5, 2 => 2],
                    'loss' => ['Rom', 150, 'Demo — sticlă spartă'], 'manual' => [['Espresso', 6]], 'cancel' => 1,
                    'closing' => ['float' => 200, 'cash' => -15, 'tokens' => -2, 'items' => ['Rom' => -300, 'Fanta 0.33' => -1, 'Coca-Cola' => 250]],
                ]],
            ],
            [
                'name' => 'Bachata Night — Demo statistici 2', 'kind' => 'basic', 'date' => '2026-08-22',
                'reports' => [[
                    'date' => '2026-08-22', 'scale' => 1.4, 'profile' => [21 => 5, 22 => 9, 23 => 8, 0 => 6, 1 => 3],
                    'closing' => ['float' => 200, 'cash' => 0, 'tokens' => 0, 'items' => []],
                ]],
            ],
            [
                'name' => 'Kizomba Night — Demo statistici 3', 'kind' => 'basic', 'date' => '2026-09-05', 'unreported' => 4,
                'reports' => [[
                    'date' => '2026-09-05', 'scale' => 1.8, 'profile' => [22 => 5, 23 => 9, 0 => 10, 1 => 8, 2 => 6, 3 => 2],
                    'loss' => ['Fanta 0.33', 2, 'Demo — sparte la transport'],
                    'closing' => ['float' => 250, 'cash' => -40, 'tokens' => 0, 'items' => ['Rom' => -450]],
                ]],
            ],
            [
                'name' => 'Latin Festival — Demo statistici 4', 'kind' => 'festival', 'date' => '2026-07-25', 'end_date' => '2026-07-26',
                'reports' => [
                    [
                        'date' => '2026-07-25', 'scale' => 2.2, 'profile' => [20 => 4, 21 => 7, 22 => 9, 23 => 9, 0 => 7, 1 => 4],
                        'closing' => ['float' => 300, 'cash' => -10, 'tokens' => 0, 'items' => ['Coca-Cola' => -400]],
                    ],
                    [
                        'date' => '2026-07-26', 'scale' => 2.6, 'profile' => [20 => 5, 21 => 8, 22 => 10, 23 => 10, 0 => 8, 1 => 5, 2 => 2],
                        'loss' => ['Rom', 300, 'Demo — vărsat la bar'],
                        'closing' => ['float' => 300, 'cash' => 0, 'tokens' => -1, 'items' => []],
                    ],
                ],
            ],
            [
                'name' => 'Summer Camp — Demo statistici 5', 'kind' => 'festival', 'date' => '2026-09-12', 'end_date' => '2026-09-13',
                'reports' => [[
                    'date' => '2026-09-12', 'scale' => 3.0, 'profile' => [19 => 3, 20 => 6, 21 => 9, 22 => 12, 23 => 12, 0 => 10, 1 => 6, 2 => 3],
                    'manual' => [['Espresso', 12]], 'loss' => ['Cafea boabe', 200, 'Demo — praf de cafea vărsat'],
                    'closing' => ['float' => 400, 'cash' => 30, 'tokens' => 3, 'items' => ['Rom' => 200]],
                ]],
            ],
            [
                'name' => 'Doar draft — Demo statistici 6', 'kind' => 'basic', 'date' => '2026-09-10',
                'reports' => [['draft' => true, 'date' => '2026-09-10']],
            ],
        ];
    }

    private function seedReport(Party $party, array $rep): void
    {
        $adminId = $this->staff->first()->id;
        $date = Carbon::parse($rep['date']);

        if (! empty($rep['draft'])) {
            StockReport::create(['date' => $date, 'status' => 'draft', 'party_id' => $party->id, 'created_by' => $adminId, 'note' => 'Date demo - statistici']);

            return;
        }

        $group = SalesGroup::create(['party_id' => $party->id, 'status' => 'open', 'created_by' => $adminId]);
        $report = StockReport::create([
            'date' => $date,
            'status' => 'draft',
            'party_id' => $party->id,
            'sales_group_id' => $group->id,
            'created_by' => $adminId,
            'note' => 'Date demo - statistici',
        ]);

        // 1. Bonurile din aplicatie (cu ora, plati, barmani); urmarim consumul de stoc si incasarile.
        $tally = $this->makeBons($group, $date, $rep['profile'], $rep['scale'], (int) ($rep['cancel'] ?? 0));

        // 2. Necesarul de stoc = consum din bonuri + linii manuale + pierdere; aprovizionam cu ~30% peste (si acoperim eventualul sold negativ).
        $need = $tally['consumption'];
        $manualLines = [];
        foreach ($rep['manual'] ?? [] as [$name, $qty]) {
            if ($mi = $this->menu->firstWhere('name', $name)) {
                $manualLines[] = [$mi, (float) $qty];
                foreach ($mi->recipeLines as $l) {
                    $need[$l->stock_item_id] = ($need[$l->stock_item_id] ?? 0) + $qty * (float) $l->qty;
                }
            }
        }
        $loss = null;
        if (! empty($rep['loss']) && ($item = StockItem::where('name', $rep['loss'][0])->first())) {
            $loss = [$item, (float) $rep['loss'][1], $rep['loss'][2]];
            $need[$item->id] = ($need[$item->id] ?? 0) + $loss[1];
        }

        foreach ($need as $stockItemId => $qty) {
            $item = StockItem::find($stockItemId);
            $bring = max(0.0, -(float) $item->stock_qty);
            $report->addEntry($item, (float) ceil($qty * 1.3 + $bring), $item->avg_cost !== null ? (float) $item->avg_cost : 0.05, $adminId, 'Demo — aprovizionare');
        }

        foreach ($manualLines as [$mi, $qty]) {
            $report->addSale($mi, $qty, $adminId, allowNegative: true);
        }
        if ($loss) {
            $report->addLoss($loss[0], $loss[1], $adminId, $loss[2], allowNegative: true);
        }

        // 3. Numaratoarea de final: cash/tokeni + inventar (asteptat = stocul dupa postarea bonurilor).
        $closing = $rep['closing'] ?? null;
        if ($closing) {
            $report->update([
                'opening_float' => $closing['float'],
                'counted_cash' => round($closing['float'] + $tally['cash'] + $closing['cash'], 2),
                'counted_tokens' => max(0, $tally['tokens'] + $closing['tokens']),
            ]);

            $involved = array_unique(array_merge(array_keys($need), array_keys($tally['consumption'])));
            foreach ($involved as $stockItemId) {
                $item = StockItem::find($stockItemId);
                $expected = (float) $item->stock_qty - ($tally['consumption'][$stockItemId] ?? 0);
                $delta = (float) ($closing['items'][$item->name] ?? 0);
                $report->counts()->create(['stock_item_id' => $item->id, 'counted_qty' => round($expected + $delta, 3)]);
            }
        }

        // 4. Finalizare prin codul real: posteaza bonurile, inchide sesiunea, calculeaza numaratoarea si aliniaza stocul.
        $report->finalize($adminId, alignStock: (bool) $closing);
        $report->update(['finalized_at' => $date->copy()->addDay()->setTime(10, 0)]);
    }

    /**
     * Genereaza bonurile unei sesiuni. $profile = [ora => greutate]; numarul de bonuri pe ora = greutate x $scale.
     *
     * @return array{consumption: array<int, float>, cash: float, tokens: int}
     */
    private function makeBons(SalesGroup $group, Carbon $date, array $profile, float $scale, int $cancel = 0): array
    {
        $consumption = [];
        $cash = 0.0;
        $tokens = 0;
        $cancelled = 0;

        foreach ($profile as $hour => $weight) {
            $day = $date->copy()->startOfDay();
            if ($hour < 12) {
                $day->addDay();
            }

            for ($i = 0, $n = max(1, (int) round($weight * $scale)); $i < $n; $i++) {
                $soldAt = $day->copy()->setTime((int) $hour, mt_rand(0, 59), mt_rand(0, 59));

                // 1-3 produse distincte, cate 1-2 bucati
                $lines = [];
                $picked = [];
                for ($k = mt_rand(1, 3); $k > 0; $k--) {
                    $mi = $this->menu[mt_rand(0, $this->menu->count() - 1)];
                    if (isset($picked[$mi->id])) {
                        continue;
                    }
                    $picked[$mi->id] = true;
                    $lines[] = ['menu_item_id' => $mi->id, 'qty' => mt_rand(1, 2), 'mi' => $mi];
                }

                $total = round(array_sum(array_map(fn ($l) => $l['qty'] * (float) $l['mi']->price, $lines)), 2);
                $payments = $this->payments($total);

                $sale = SaleRecorder::record(
                    $group,
                    array_map(fn ($l) => ['menu_item_id' => $l['menu_item_id'], 'qty' => $l['qty']], $lines),
                    $payments,
                    'app',
                    $this->pickStaff()->id,
                    ['sold_at' => $soldAt],
                );

                if ($cancelled < $cancel && $i === 0) {
                    $sale->cancel($this->staff->first()->id, 'Demo — greșeală de încasare');
                    $cancelled++;

                    continue; // anulata: nu consuma stoc si nu intra in incasari
                }

                foreach ($lines as $l) {
                    foreach ($l['mi']->recipeLines as $r) {
                        $consumption[$r->stock_item_id] = ($consumption[$r->stock_item_id] ?? 0) + $l['qty'] * (float) $r->qty;
                    }
                }
                foreach ($payments as $p) {
                    if ($p['method'] === 'cash') {
                        $cash += $p['amount'];
                    } elseif ($p['method'] === 'token') {
                        $tokens += (int) $p['tokens'];
                    }
                }
            }
        }

        return ['consumption' => $consumption, 'cash' => round($cash, 2), 'tokens' => $tokens];
    }

    /** Plati care acopera exact totalul: cash / tokeni (+ rest cash) / credit / cash+credit / beneficiu. */
    private function payments(float $total): array
    {
        $r = mt_rand(1, 100);

        if ($r <= 38) {
            return [['method' => 'cash', 'amount' => $total]];
        }

        if ($r <= 62) {
            $max = (int) floor($total / $this->rate);
            if ($max >= 1) {
                $tk = $max > 1 ? mt_rand(1, $max) : 1;
                $rest = round($total - $tk * $this->rate, 2);
                $rows = [['method' => 'token', 'tokens' => $tk]];
                if ($rest > 0.004) {
                    $rows[] = ['method' => 'cash', 'amount' => $rest];
                }

                return $rows;
            }

            return [['method' => 'cash', 'amount' => $total]];
        }

        if ($r <= 84) {
            return [['method' => 'credit', 'amount' => $total]];
        }

        if ($r <= 94) {
            $half = round($total / 2, 2);

            return [['method' => 'cash', 'amount' => $half], ['method' => 'credit', 'amount' => round($total - $half, 2)]];
        }

        return [['method' => 'benefit', 'amount' => $total]];
    }

    /** Barmanul care inregistreaza bonul: ~55% primul cont, ~35% al doilea, restul al treilea. */
    private function pickStaff(): Admin
    {
        $r = mt_rand(1, 100);

        return $this->staff[$r <= 55 || $this->staff->count() < 2 ? 0 : ($r <= 90 || $this->staff->count() < 3 ? 1 : 2)];
    }

    private function staff()
    {
        $staff = Admin::query()->where('is_active', true)->orderBy('id')->limit(3)->get();

        for ($i = $staff->count(); $i < 2; $i++) {
            $staff->push(Admin::firstOrCreate(
                ['phone' => '+4070000010'.($i + 1)],
                ['name' => 'Demo Barman '.($i + 1), 'password' => bin2hex(random_bytes(8)), 'role' => 'admin', 'is_active' => false]
            ));
        }

        return $staff->values();
    }

    private function ensureTokens(): void
    {
        $rate = (float) Settings::get('token_rate');

        if ($rate <= 0) {
            Settings::set('token_rate', 5);
            Settings::set('uses_tokens', true);
            $rate = 5.0;
            $this->command?->warn('Cursul token → lei nu era setat: l-am setat la 5 și am activat tokenii.');
        }

        $this->rate = $rate;
    }
}
