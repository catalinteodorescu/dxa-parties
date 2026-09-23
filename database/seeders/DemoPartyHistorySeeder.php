<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\MenuItem;
use App\Models\Party;
use App\Models\StockItem;
use App\Models\StockReport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * DXA: adaugat — date DEMO pt. testarea "Sugerează cantități" (Bar → Necesare).
 *
 * De ce seeder si nu INSERT direct in sqlite: stock_qty/avg_cost pe StockItem
 * sunt CACHE, recalculate DOAR prin StockItem::record*() (vezi comentariile din
 * model) — scrise direct in stock_movements am fi rupt invariantul. Rulam prin
 * StockReport::addEntry()/addSale()/addLoss(), exact codul folosit de
 * finalize(), ca datele sa fie 100% consistente (miscari + cache), nu doar
 * niste randuri plauzibile in stock_report_sales (asa cum sunt, din pacate,
 * cele 2 petreceri de test EXISTENTE — vezi nota de mai jos).
 *
 * NOTA importanta despre datele de test EXISTENTE (party_id 2 si 4, stock_reports
 * 12 si 13, note = 'Date test - comparatie petreceri'): au randuri in
 * stock_report_sales (bune pt. widget-ul de comparatie de pe dashboard, care
 * citeste de-acolo), dar NU au stock_movements corespunzatoare pt. vanzarile cu
 * reteta (Cuba Libre, MyFCock, Fanta 0.33, Espresso) — doar cele 2 "pierderi
 * test" au miscare reala. Inseamna ca acele 2 petreceri aproape nu contribuie
 * la media de consum pe care se bazeaza "Sugerează cantități" (nu are din ce
 * calcula, pt ca stock_movements e sursa, nu stock_report_sales). Nu le-am
 * atins — au ramas explicit cerute ca date de test — dar de-aia a fost nevoie
 * de petreceri noi, cu miscari complete, ca sa existe pe ce sa se bazeze
 * media (minim 2 petreceri de fiecare `kind`, cere task-ul).
 *
 * Adauga 5 petreceri noi TRECUTE (3 "basic" + 2 "festival"), fiecare cu cate o
 * raportare FINALIZATA (intrari + vanzari + eventual o pierdere) — istoricul
 * de consum pe care se bazeaza media.
 *
 * PLUS 2 petreceri VIITOARE (1 "basic" + 1 "festival"), FARA raportare — alea
 * sunt cele pt. care chiar ai face un Necesar nou acum. Fara ele, selectorul
 * de petreceri din Necesare/Raportari e gol: ambele formulare filtreaza
 * explicit doar petreceri cu starts_at >= azi (sau festival inca in
 * desfasurare) — vezi $upcoming in StockRequisitions/Form.php si
 * StockReports/Form.php. Cele 2 petreceri reale din proiect (13.09 si
 * 20.09.2026) sunt deja trecute fata de azi, la fel ca cele 5 istorice de mai
 * sus — de-aia dropdown-ul era gol INDIFERENT de acest seeder.
 *
 * Idempotent: daca o petrecere cu acelasi nume exista deja (party + raportare
 * legata, la cele istorice), o sare — sigur de rulat de mai multe ori.
 *
 * Rulare (din radacina proiectului, pe Herd):
 *   php artisan db:seed --class="Database\Seeders\DemoPartyHistorySeeder"
 */
class DemoPartyHistorySeeder extends Seeder
{
    public function run(): void
    {
        $adminId = Admin::query()->orderBy('id')->value('id');

        if (! $adminId) {
            $this->command?->error('Nu există niciun admin în baza de date — abandonez.');

            return;
        }

        $rom = StockItem::where('name', 'Rom')->first();
        $cola = StockItem::where('name', 'Coca-Cola')->first();
        $fanta15 = StockItem::where('name', 'Fanta 1.5L')->first();
        $tonica = StockItem::where('name', 'Apa tonica')->first();
        $fanta033Stock = StockItem::where('name', 'Fanta 0.33')->first();
        $cafea = StockItem::where('name', 'Cafea boabe')->first();

        $cubaLibre = MenuItem::where('name', 'Cuba Libre')->first();
        $myFCock = MenuItem::where('name', 'MyFCock')->first();
        $fanta033Item = MenuItem::where('name', 'Fanta 0.33')->first();
        $espresso = MenuItem::where('name', 'Espresso')->first();

        if (! $rom || ! $cola || ! $fanta15 || ! $tonica || ! $fanta033Stock || ! $cafea
            || ! $cubaLibre || ! $myFCock || ! $fanta033Item || ! $espresso) {
            $this->command?->error('Lipsesc produse/articole de meniu așteptate (Rom, Coca-Cola, Fanta 1.5L, Apa tonica, Fanta 0.33, Cafea boabe / Cuba Libre, MyFCock, Fanta 0.33, Espresso) — abandonez.');

            return;
        }

        $parties = [
            [
                'name' => 'Salsa Night — Demo istoric 1',
                'kind' => 'basic',
                'date' => '2026-03-14',
                'entries' => [
                    [$rom, 3000, 0.095], [$cola, 6000, 0.006], [$fanta15, 1500, 0.007],
                    [$tonica, 2000, 0.004], [$fanta033Stock, 30, 11.20], [$cafea, 800, 0.1433],
                ],
                'sales' => [[$cubaLibre, 30], [$myFCock, 15], [$fanta033Item, 25], [$espresso, 10]],
                'loss' => [$fanta033Stock, 2, 'Demo — sparte la transport'],
            ],
            [
                'name' => 'Bachata Sunset — Demo istoric 2',
                'kind' => 'basic',
                'date' => '2026-05-23',
                'entries' => [
                    [$rom, 2800, 0.095], [$cola, 5000, 0.006], [$fanta15, 2000, 0.007],
                    [$tonica, 2500, 0.004], [$fanta033Stock, 35, 11.20], [$cafea, 900, 0.1433],
                ],
                'sales' => [[$cubaLibre, 20], [$myFCock, 22], [$fanta033Item, 30], [$espresso, 14]],
                'loss' => [$cafea, 60, 'Demo — praf de cafea vărsat'],
            ],
            [
                'name' => 'Kizomba Vibes — Demo istoric 3',
                'kind' => 'basic',
                'date' => '2026-07-18',
                'entries' => [
                    [$rom, 3500, 0.095], [$cola, 6500, 0.006], [$fanta15, 1800, 0.007],
                    [$tonica, 2200, 0.004], [$fanta033Stock, 25, 11.20], [$cafea, 700, 0.1433],
                ],
                'sales' => [[$cubaLibre, 35], [$myFCock, 18], [$fanta033Item, 20], [$espresso, 8]],
                'loss' => null,
            ],
            [
                'name' => 'Latin Spring Festival — Demo istoric 1',
                'kind' => 'festival',
                'date' => '2026-04-10',
                'entries' => [
                    [$rom, 8000, 0.095], [$cola, 14000, 0.006], [$fanta15, 4000, 0.007],
                    [$tonica, 7000, 0.004], [$fanta033Stock, 100, 11.20], [$cafea, 2000, 0.1433],
                ],
                'sales' => [[$cubaLibre, 80], [$myFCock, 60], [$fanta033Item, 90], [$espresso, 30]],
                'loss' => [$fanta033Stock, 5, 'Demo — sparte la transport'],
            ],
            [
                'name' => 'Summer Dance Camp — Demo istoric 2',
                'kind' => 'festival',
                'date' => '2026-06-27',
                'entries' => [
                    [$rom, 9000, 0.095], [$cola, 16000, 0.006], [$fanta15, 4500, 0.007],
                    [$tonica, 8000, 0.004], [$fanta033Stock, 90, 11.20], [$cafea, 2400, 0.1433],
                ],
                'sales' => [[$cubaLibre, 95], [$myFCock, 70], [$fanta033Item, 75], [$espresso, 40]],
                'loss' => [$cafea, 100, 'Demo — praf de cafea vărsat'],
            ],
        ];

        foreach ($parties as $def) {
            $party = Party::firstOrCreate(
                ['name' => $def['name']],
                [
                    'kind' => $def['kind'],
                    'status' => 'published',
                    'start_date' => $def['date'],
                    'start_time' => '22:00',
                    'is_active' => true,
                    'audience' => 'all',
                    'created_by' => $adminId,
                ]
            );

            if (StockReport::where('party_id', $party->id)->exists()) {
                $this->command?->info('Sar peste „'.$def['name'].'" — are deja o raportare legată.');

                continue;
            }

            $report = StockReport::create([
                'date' => $def['date'],
                'status' => 'draft',
                'party_id' => $party->id,
                'created_by' => $adminId,
            ]);

            foreach ($def['entries'] as [$stockItem, $qty, $cost]) {
                $report->addEntry($stockItem, (float) $qty, (float) $cost, $adminId, 'Demo — aprovizionare');
            }

            foreach ($def['sales'] as [$menuItem, $qty]) {
                $report->addSale($menuItem, (float) $qty, $adminId, allowNegative: true);
            }

            if ($def['loss']) {
                [$stockItem, $qty, $reason] = $def['loss'];
                $report->addLoss($stockItem, (float) $qty, $adminId, $reason, allowNegative: true);
            }

            $report->update([
                'status' => 'finalized',
                'finalized_at' => Carbon::parse($def['date'].' 23:30:00'),
                'finalized_by' => $adminId,
            ]);

            $this->command?->info('Creat „'.$def['name'].'" ('.$def['kind'].', '.$def['date'].') + raportare finalizată.');
        }

        // --------------------------------------------------------------
        // Petreceri VIITOARE, fara raportare — cele pt. care faci un Necesar nou.
        // Date relative la azi (now()), nu fixe, ca sa ramana "viitoare" oricand rulezi seederul.
        // --------------------------------------------------------------
        $upcoming = [
            [
                'name' => 'Salsa & Bachata Night — Demo (viitoare)',
                'kind' => 'basic',
                'date' => now()->addDays(12)->toDateString(),
            ],
            [
                'name' => 'Autumn Dance Festival — Demo (viitoare)',
                'kind' => 'festival',
                'date' => now()->addDays(25)->toDateString(),
            ],
        ];

        foreach ($upcoming as $def) {
            $party = Party::firstOrCreate(
                ['name' => $def['name']],
                [
                    'kind' => $def['kind'],
                    'status' => 'published',
                    'start_date' => $def['date'],
                    'start_time' => '22:00',
                    'is_active' => true,
                    'audience' => 'all',
                    'created_by' => $adminId,
                ]
            );

            $this->command?->info('Creat „'.$def['name'].'" ('.$def['kind'].', '.$def['date'].', fără raportare — pentru testul de „Sugerează cantități").');
        }
    }
}
