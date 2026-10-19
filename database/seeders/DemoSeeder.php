<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Announcement;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemRecipe;
use App\Models\Participant;
use App\Models\Party;
use App\Models\SalesGroup;
use App\Models\StockItem;
use App\Models\StockReport;
use App\Services\CreditLedger;
use App\Services\EntryRecorder;
use App\Services\LegalTemplates;
use App\Services\ParticipantRegistry;
use App\Services\PrivacyPolicy;
use App\Services\SaleRecorder;
use App\Services\Terms;
use App\Services\TicketOrders;
use DomainException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Date de DEMONSTRAȚIE, neutre (fără nume de client): meniu de bar cu stoc și rețete, petreceri trecute și viitoare (inclusiv un festival),
 * participanți cu cont, intrări, vânzări la bar, credite, bilete online și anunțuri. Totul se creează prin serviciile aplicației
 * (EntryRecorder, SaleRecorder, TicketOrders, CreditLedger, StockReport), deci cifrele sunt coerente. Idempotent: dacă există deja
 * petrecerile demo, nu face nimic. Rulează doar când DEMO_MODE=true (config('app.demo')).
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'demo1234';

    public function run(): void
    {
        if (! config('app.demo')) {
            throw new DomainException('Datele demo se pot încărca doar pe o instanță de demonstrație (DEMO_MODE=true în .env).');
        }
        if (Party::query()->where('name', 'like', '%(demo)')->exists()) {
            $this->command?->info('Datele demo există deja — nu adaug nimic.');

            return;
        }

        mt_srand(2026);
        $adminId = Admin::query()->orderBy('id')->value('id');
        if (! $adminId) {
            throw new DomainException('Nu există niciun administrator. Rulează întâi instalarea.');
        }

        [$menu, $stock] = $this->menu($adminId);
        $people = $this->people();
        $past = $this->pastParties($adminId, $menu, $stock, $people);
        $this->upcoming($people);
        $this->announcements();
        $this->legal();

        $this->command?->info('Date demo create: '.count($people).' participanți, '.count($past).' petreceri trecute, 3 viitoare. Parola conturilor: '.self::PASSWORD);
    }

    /** @return array{0: array<string, MenuItem>, 1: array<string, StockItem>} */
    private function menu(int $adminId): array
    {
        $stockDefs = [
            'Rom' => ['ml', 0.095], 'Cola' => ['ml', 0.006], 'Apă tonică' => ['ml', 0.004], 'Lime' => ['buc', 0.8],
            'Bere 0.33' => ['buc', 4.5], 'Apă plată 0.5' => ['buc', 1.8], 'Cafea boabe' => ['g', 0.14],
        ];
        $stock = [];
        foreach ($stockDefs as $name => [$unit, $cost]) {
            $stock[$name] = StockItem::firstOrCreate(['name' => $name], ['unit' => $unit, 'avg_cost' => $cost, 'min_stock' => 0, 'is_active' => true, 'created_by' => $adminId]);
        }

        $cats = [];
        foreach (['Cocktailuri' => 1, 'Băuturi' => 2, 'Cafea' => 3] as $name => $order) {
            $cats[$name] = MenuCategory::firstOrCreate(['name' => $name], ['sort_order' => $order, 'is_active' => true]);
        }

        $itemDefs = [
            'Mojito' => ['Cocktailuri', 28, [['Rom', 50], ['Lime', 0.5]]],
            'Cuba Libre' => ['Cocktailuri', 25, [['Rom', 50], ['Cola', 150]]],
            'Rom cu tonic' => ['Cocktailuri', 25, [['Rom', 50], ['Apă tonică', 150]]],
            'Bere' => ['Băuturi', 12, [['Bere 0.33', 1]]],
            'Apă plată' => ['Băuturi', 7, [['Apă plată 0.5', 1]]],
            'Cola' => ['Băuturi', 10, [['Cola', 250]]],
            'Espresso' => ['Cafea', 9, [['Cafea boabe', 8]]],
        ];
        $menu = [];
        foreach ($itemDefs as $name => [$cat, $price, $recipe]) {
            $item = MenuItem::firstOrCreate(['name' => $name], ['menu_category_id' => $cats[$cat]->id, 'price' => $price, 'is_active' => true, 'created_by' => $adminId]);
            foreach ($recipe as [$stockName, $qty]) {
                MenuItemRecipe::firstOrCreate(['menu_item_id' => $item->id, 'stock_item_id' => $stock[$stockName]->id], ['qty' => $qty]);
            }
            $menu[$name] = $item;
        }

        return [$menu, $stock];
    }

    /** @return array<int, Participant> */
    private function people(): array
    {
        $names = [
            'Andreea Popescu', 'Mihai Ionescu', 'Elena Dumitrescu', 'Radu Stan', 'Ioana Georgescu', 'Alexandru Marin', 'Cristina Rusu', 'Bogdan Munteanu',
            'Diana Constantin', 'Vlad Tudor', 'Maria Neagu', 'Andrei Dobre', 'Laura Stoica', 'Cosmin Barbu', 'Raluca Matei', 'Sergiu Lungu',
            'Oana Preda', 'Tudor Voicu', 'Alina Florea', 'Ștefan Cojocaru',
        ];
        $people = [];
        foreach ($names as $i => $name) {
            $phone = '07000000'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
            $p = Participant::query()->where('phone', $phone)->first() ?? ParticipantRegistry::create($name, $phone);
            $p->forceFill(['password' => self::PASSWORD, 'phone_verified_at' => now()])->save();
            $people[] = $p;
        }

        return $people;
    }

    /** @return array<int, Party> */
    private function pastParties(int $adminId, array $menu, array $stock, array $people): array
    {
        $defs = [
            ['Salsa Night (demo)', 'basic', 70, 45],
            ['Bachata Sunset (demo)', 'basic', 49, 40],
            ['Kizomba Vibes (demo)', 'basic', 28, 35],
            ['Latin Spring Festival (demo)', 'festival', 14, 60],
        ];
        $parties = [];
        foreach ($defs as [$name, $kind, $daysAgo, $price]) {
            $date = now()->subDays($daysAgo)->toDateString();
            $festival = $kind === 'festival';
            $party = Party::create([
                'name' => $name, 'kind' => $kind, 'start_date' => $date, 'end_date' => $festival ? now()->subDays($daysAgo - 2)->toDateString() : null,
                'start_time' => '21:00', 'end_time' => '03:00', 'location_name' => 'Club Demo', 'location_address' => 'Str. Exemplu 1, București',
                'is_free' => false, 'audience' => 'all', 'in_carousel' => false, 'is_active' => true, 'status' => 'published',
                'payment_methods' => ['cash', 'card'], 'online_sales' => false, 'loyalty_eligible' => true, 'created_by' => $adminId,
                'ticket_types' => [['name' => 'Bilet', 'price' => $price, 'discounts' => [], 'qty_tiers' => []]],
            ]);
            $at = Carbon::parse($date.' 22:00:00');

            // Intrări: câteva persoane identificate, restul anonime.
            $crowd = $festival ? 55 : 32;
            $ids = array_slice(array_map(fn ($p) => $p->id, $people), 0, 8);
            foreach ($ids as $k => $id) {
                EntryRecorder::record($party, 'Bilet', 1, [['method' => $k % 2 ? 'card' : 'cash', 'amount' => $price]], adminId: $adminId, at: $at->copy()->addMinutes($k * 3), enforceState: false, participants: [$id]);
            }
            $left = $crowd - count($ids);
            while ($left > 0) {
                $n = min($left, mt_rand(1, 3));
                EntryRecorder::record($party, 'Bilet', $n, [['method' => mt_rand(0, 2) ? 'cash' : 'card', 'amount' => $price * $n]], adminId: $adminId, at: $at->copy()->addMinutes(mt_rand(10, 180)), enforceState: false);
                $left -= $n;
            }

            // Stoc și vânzări la bar prin raportarea petrecerii.
            $report = StockReport::create(['date' => $date, 'status' => 'draft', 'party_id' => $party->id, 'created_by' => $adminId]);
            foreach ($stock as $s) {
                $qty = match ($s->unit) {
                    'ml' => $festival ? 9000 : 4500, 'g' => 800, default => $festival ? 120 : 60
                };
                $report->addEntry($s, (float) $qty, (float) $s->avg_cost, $adminId, 'Aprovizionare');
            }
            $group = SalesGroup::openFor($party->id, $adminId);
            $mult = $festival ? 2 : 1;
            foreach ([['Mojito', 14], ['Cuba Libre', 12], ['Rom cu tonic', 8], ['Bere', 10], ['Apă plată', 9], ['Cola', 7], ['Espresso', 4]] as [$item, $base]) {
                $qty = $base * $mult + mt_rand(0, 4);
                $report->addSale($menu[$item], (float) $qty, $adminId, allowNegative: true);
            }
            for ($i = 0; $i < 12 * $mult; $i++) {
                $item = $menu[array_rand($menu)];
                $qty = mt_rand(1, 3);
                $total = (float) $item->price * $qty;
                $sale = SaleRecorder::record($group, [['menu_item_id' => $item->id, 'qty' => $qty]], [['method' => mt_rand(0, 1) ? 'cash' : 'card', 'amount' => $total]], adminId: $adminId);
                $sale->forceFill(['created_at' => $at->copy()->addMinutes(mt_rand(15, 300))])->save();
            }
            $group->close();
            $report->update(['status' => 'finalized', 'finalized_at' => $at->copy()->addHours(6), 'finalized_by' => $adminId]);
            $parties[] = $party;
        }

        // Credite pentru câțiva participanți.
        foreach (array_slice($people, 0, 6) as $k => $p) {
            CreditLedger::load($p, 50.0 + $k * 10, 'manual', $adminId, 'Credite demo');
        }

        return $parties;
    }

    /** @param array<int, Participant> $people */
    private function upcoming(array $people): void
    {
        $defs = [
            ['Salsa & Bachata Night (demo)', 'basic', 6, 50],
            ['Latin Friday (demo)', 'basic', 13, 40],
            ['Autumn Dance Festival (demo)', 'festival', 30, 120],
        ];
        $parties = [];
        foreach ($defs as [$name, $kind, $in, $price]) {
            $festival = $kind === 'festival';
            $parties[] = Party::create([
                'name' => $name, 'kind' => $kind, 'start_date' => now()->addDays($in)->toDateString(), 'end_date' => $festival ? now()->addDays($in + 2)->toDateString() : null,
                'start_time' => '21:00', 'end_time' => '03:00', 'location_name' => 'Club Demo', 'location_address' => 'Str. Exemplu 1, București',
                'is_free' => false, 'audience' => 'all', 'in_carousel' => true, 'is_active' => true, 'status' => 'published',
                'payment_methods' => ['cash', 'card'], 'online_sales' => true, 'loyalty_eligible' => true, 'tickets_for_sale' => $festival ? 300 : 120,
                'ticket_types' => [['name' => 'Bilet', 'price' => $price, 'discounts' => [], 'qty_tiers' => []]],
            ]);
        }

        // Bilete cumpărate online (comenzi cu plata la intrare) pentru câțiva participanți.
        foreach (array_slice($people, 8, 8) as $k => $buyer) {
            try {
                TicketOrders::place($buyer, $parties[$k % 3], 'Bilet', mt_rand(1, 2));
            } catch (DomainException) {
                // dacă o regulă blochează comanda (de ex. plata online dezactivată), demo-ul continuă fără ea
            }
        }
    }

    /** Date FICTIVE ale organizatorului + publicarea textelor legale, ca ecranele și fluxul de acceptare să se vadă în demo. */
    private function legal(): void
    {
        LegalTemplates::saveOrganizer([
            'organizer_name' => 'Demo Events SRL', 'organizer_cui' => 'RO 00000000', 'organizer_regcom' => 'J00/0000/2026',
            'organizer_address' => 'Str. Exemplu nr. 1, București', 'organizer_email' => 'contact@example.com', 'organizer_phone' => '0700000000',
        ]);
        foreach ([[Terms::enabled(), fn () => Terms::publish(LegalTemplates::rendered('terms'), false)], [PrivacyPolicy::enabled(), fn () => PrivacyPolicy::publish(LegalTemplates::rendered('privacy'))]] as [$published, $publish]) {
            if (! $published) {
                try {
                    $publish();
                } catch (DomainException) {
                    // textul nu s-a schimbat: nimic de făcut
                }
            }
        }
    }

    private function announcements(): void
    {
        Announcement::firstOrCreate(['title' => 'Bine ați venit la demo'], [
            'body' => 'Aceasta este o instanță de demonstrație cu date fictive. Explorează petrecerile, biletele și bara.',
            'audience' => 'all', 'in_carousel' => true, 'in_list' => true, 'is_active' => true, 'status' => 'published',
        ]);
    }
}
