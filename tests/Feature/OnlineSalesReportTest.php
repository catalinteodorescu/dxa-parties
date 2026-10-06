<?php

use App\Livewire\Admin\Parties\Stats;
use App\Models\Admin;
use App\Models\Participant;
use App\Models\Party;
use App\Models\Promoter;
use App\Models\StockReport;
use App\Models\Ticket;
use App\Services\OnlineSalesReport;
use App\Services\ParticipantRegistry;
use App\Services\TicketOrders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

/** DXA: teste (runda 36). Raportul „Vânzări online” pe petrecere. */
function osParty(array $o = []): Party
{
    Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00'));

    return Party::create(array_merge([
        'name' => 'Petrecere raport', 'kind' => 'basic', 'start_date' => '2026-10-03', 'start_time' => '21:00', 'end_time' => '03:00',
        'is_free' => false, 'audience' => 'all', 'in_carousel' => false, 'is_active' => true, 'status' => 'published',
        'payment_methods' => ['cash'], 'online_sales' => true, 'tickets_for_sale' => 30,
        'ticket_types' => [
            ['name' => 'Bilet', 'price' => 50, 'limit' => 20, 'discounts' => [], 'combos' => [['buy' => 3, 'free' => 1]],
                'qty_tiers' => [['first' => 3, 'price' => 30, 'label' => 'Early bird'], ['first' => 6, 'price' => 40, 'label' => null]]],
            ['name' => 'VIP', 'price' => 80, 'discounts' => [], 'qty_tiers' => []],
        ],
    ], $o));
}

function osBuyer(): Participant
{
    $p = ParticipantRegistry::create('Ana Raport', '0722333444');
    $p->forceFill(['password' => 'parola-sigura', 'phone_verified_at' => now()])->save();

    return $p;
}

function osAdmin(): Admin
{
    return Admin::create(['name' => 'Admin', 'phone' => '+40700123456', 'role' => 'superadmin', 'is_active' => true, 'password' => 'secret-pass']);
}

/** Comenzi: 4 combo 3+1 (30,30,30,0), 2 cu cod (2×36), 2 VIP din care unul anulat. */
function osSeed(): array
{
    $party = osParty();
    $ana = osBuyer();
    $promoter = Promoter::create(['name' => 'Bob']);
    $party->discountCodes()->create(['code' => 'PROMO10', 'type' => 'percent', 'value' => 10, 'is_active' => true, 'max_uses' => 5, 'promoter_id' => $promoter->id]);
    $party->discountCodes()->create(['code' => 'NEFOLOSIT', 'type' => 'amount', 'value' => 5, 'is_active' => true]);

    TicketOrders::place($ana, $party, 'Bilet', 4, [], null, null, '3+1');
    TicketOrders::place($ana, $party, 'Bilet', 2, [], 'PROMO10');
    $vip = TicketOrders::place($ana, $party, 'VIP', 2);
    $vip->tickets->last()->update(['status' => Ticket::VOID]);

    return [$party, $ana];
}

it('raportul numără biletele pe tip: plătite, oferite, rămase, valoare și reduceri', function () {
    [$party] = osSeed();

    $r = OnlineSalesReport::forParty($party);
    $t = $r->totals;

    expect($r->has_data)->toBeTrue()
        ->and($t->tickets)->toBe(7)->and($t->paid)->toBe(6)->and($t->offered)->toBe(1)->and($t->free)->toBe(0)->and($t->voided)->toBe(1)
        ->and($t->revenue)->toBe(242.0)->and($t->discount)->toBe(8.0)
        ->and($t->orders)->toBe(3)->and($t->buyers)->toBe(1)->and($t->limit)->toBe(30)->and($t->left)->toBe(23);

    $bilet = $r->types->firstWhere('name', 'Bilet');
    expect($bilet->tickets)->toBe(6)->and($bilet->paid)->toBe(5)->and($bilet->offered)->toBe(1)
        ->and($bilet->limit)->toBe(20)->and($bilet->left)->toBe(14)->and($bilet->revenue)->toBe(162.0)->and($bilet->discount)->toBe(8.0);

    $vip = $r->types->firstWhere('name', 'VIP');
    expect($vip->tickets)->toBe(1)->and($vip->limit)->toBeNull()->and($vip->left)->toBeNull()->and($vip->revenue)->toBe(80.0);
});

it('combo-urile se numără în seturi, cu bilete plătite și oferite', function () {
    [$party] = osSeed();

    $combos = OnlineSalesReport::forParty($party)->combos;

    expect($combos)->toHaveCount(1);
    $c = $combos->first();
    expect($c->type)->toBe('Bilet')->and($c->label)->toBe('3+1')->and($c->sets)->toBe(1)->and($c->tickets)->toBe(4)
        ->and($c->paid)->toBe(3)->and($c->offered)->toBe(1)->and($c->revenue)->toBe(90.0);
});

it('treptele „primele N” arată câte locuri mai sunt la prețul special', function () {
    [$party] = osSeed();

    $tiers = OnlineSalesReport::forParty($party)->tiers;

    expect($tiers)->toHaveCount(2);
    [$a, $b] = [$tiers[0], $tiers[1]];
    expect($a->price)->toBe(30.0)->and($a->size)->toBe(3)->and($a->sold)->toBe(3)->and($a->left)->toBe(0)->and($a->state)->toBe('sold_out')
        ->and($b->price)->toBe(40.0)->and($b->size)->toBe(3)->and($b->sold)->toBe(2)->and($b->left)->toBe(1)->and($b->state)->toBe('active');
});

it('biletele gratuite (preț de listă 0) și cele folosite se numără separat de cele plătite', function () {
    $party = osParty(['ticket_types' => [['name' => 'Bilet', 'price' => 0, 'discounts' => [], 'qty_tiers' => []]]]);
    $ana = osBuyer();

    $order = TicketOrders::place($ana, $party, 'Bilet', 3);
    $order->tickets->first()->update(['status' => Ticket::USED]);

    $t = OnlineSalesReport::forParty($party)->totals;
    expect($t->tickets)->toBe(3)->and($t->free)->toBe(3)->and($t->paid)->toBe(0)->and($t->offered)->toBe(0)->and($t->used)->toBe(1)->and($t->revenue)->toBe(0.0);
});

it('petrecere fără vânzări: raport gol, fără erori', function () {
    $r = OnlineSalesReport::forParty(osParty());

    expect($r->has_data)->toBeFalse()->and($r->totals->tickets)->toBe(0)->and($r->combos)->toHaveCount(0)
        ->and($r->types->pluck('name')->all())->toBe(['Bilet', 'VIP']);
});

it('un tip de bilet șters din petrecere își păstrează vânzările în raport', function () {
    [$party] = osSeed();
    $party->update(['ticket_types' => [['name' => 'Bilet', 'price' => 50, 'discounts' => [], 'qty_tiers' => []]]]);

    $vip = OnlineSalesReport::forParty($party->fresh())->types->firstWhere('name', 'VIP');

    expect($vip)->not->toBeNull()->and($vip->defined)->toBeFalse()->and($vip->tickets)->toBe(1);
});

it('pagina de statistici afișează secțiunea Vânzări online, chiar și fără raportări finalizate', function () {
    [$party] = osSeed();
    $this->actingAs(osAdmin(), 'admin');

    $this->get(route('admin.parties.stats', $party))->assertOk();

    Livewire::test(Stats::class, ['party' => $party])
        ->assertSee('Vânzări online')->assertSee('Bilete pe tip')->assertSee('Combo-uri vândute')->assertSee('3+1')
        ->assertSee('Locuri rămase la preț special')->assertSee('epuizată')->assertSee('activă')->assertSee('1 loc rămas')
        ->assertSee('Coduri de reducere')->assertSee('PROMO10')->assertSee('242,00 lei')->assertSee('1 anulate');
});

it('statistici: cu vânzare online dar zero vânzări spune asta; fără vânzare online și fără bilete secțiunea lipsește', function () {
    $this->actingAs(osAdmin(), 'admin');

    Livewire::test(Stats::class, ['party' => osParty()])->assertSee('Nu s-a vândut încă niciun bilet online');
    Livewire::test(Stats::class, ['party' => osParty(['online_sales' => false])])->assertDontSee('Vânzări online');
});

it('vizitatorii nelogați nu văd statisticile', function () {
    $this->get(route('admin.parties.stats', osParty()))->assertRedirect();
});

it('comparația dintre petreceri include și vânzările online (valoarea celeilalte + diferența)', function () {
    [$party, $ana] = osSeed();
    $other = osParty(['name' => 'Petrecere veche', 'start_date' => '2026-10-10']);
    TicketOrders::place($ana, $other, 'VIP', 2);
    $admin = osAdmin();
    StockReport::create(['date' => '2026-09-21', 'status' => 'finalized', 'finalized_at' => now(), 'finalized_by' => $admin->id, 'party_id' => $other->id, 'created_by' => $admin->id]);
    $this->actingAs($admin, 'admin');

    Livewire::test(Stats::class, ['party' => $party])
        ->assertDontSee('vs 160,00 lei')
        ->set('compare', (string) $other->id)
        ->assertSee('vs 2')->assertSee('vs 160,00 lei')->assertSee('▲ +250,0%')->assertSee('▲ +51,2%');
});

it('boxul fără raportări are margine jos și text mic; cardurile KPI sunt în boxul Vânzări online', function () {
    [$party] = osSeed();
    $this->actingAs(osAdmin(), 'admin');

    $html = Livewire::test(Stats::class, ['party' => $party])->assertSeeHtml('px-5 py-6 mb-4 text-center text-sm text-ink-soft')->html();

    $box = strpos($html, 'Vânzări online</h3>');
    $kpi = strpos($html, 'Bilete vândute');
    $tabel = strpos($html, 'Bilete pe tip</h3>');
    expect($box)->not->toBeFalse()->and($kpi)->toBeGreaterThan($box)->and($kpi)->toBeLessThan($tabel);
    // cardul „Vânzări online” se închide DUPĂ KPI (ele sunt înăuntru): între titlu și KPI nu apare niciun </div> de nivel card urmat de alt card
    expect(substr_count(substr($html, $box, $kpi - $box), 'rounded-2xl border border-border bg-surface'))->toBe(0);
});
