<?php

use App\Livewire\Admin\Dashboard;
use App\Models\Admin;
use App\Models\CreditTopup;
use App\Models\Order;
use App\Models\Participant;
use App\Models\Party;
use App\Models\ReceptionReport;
use App\Models\ReceptionSession;
use App\Models\StockItem;
use App\Services\AppDashboard;
use App\Services\ContentStats;
use App\Services\DashboardAlerts;
use App\Support\DashboardSections;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** DXA: teste (runda 75). Cardul „Atenție” și blocul „Aplicație” din dashboard. */
function r75Admin(string $role = 'superadmin', ?array $permissions = null): Admin
{
    return Admin::create([
        'name' => 'Cont '.random_int(1, 99999), 'phone' => '07'.random_int(10000000, 99999999), 'role' => $role,
        'permissions' => $permissions ?? Permissions::legacyAdmin(), 'is_active' => true,
        'access_admin' => true, 'access_bar' => true, 'access_reception' => true, 'password' => 'secret-pass',
    ]);
}

function r75Party(): Party
{
    return Party::create([
        'name' => 'Petrecere alerte', 'kind' => 'basic', 'start_date' => now()->addDays(3)->toDateString(), 'start_time' => '21:00', 'end_time' => '03:00',
        'is_free' => false, 'audience' => 'all', 'in_carousel' => false, 'is_active' => true, 'status' => 'published',
        'payment_methods' => ['cash'], 'online_sales' => true,
        'ticket_types' => [['name' => 'Bilet', 'price' => 50, 'discounts' => [], 'qty_tiers' => []]],
    ]);
}

function r75Participant(array $o = []): Participant
{
    return Participant::create(array_merge([
        'uuid' => (string) Str::uuid(), 'name' => 'Participant '.random_int(1, 99999), 'phone' => '07'.random_int(10000000, 99999999),
        'password' => 'parola-sigura', 'phone_verified_at' => now(),
    ], $o));
}

function r75Keys(Admin $a): array
{
    return DashboardAlerts::for($a)->pluck('key')->all();
}

it('fără nimic de rezolvat, nu există alerte și cardul nu apare', function () {
    $admin = r75Admin();

    expect(DashboardAlerts::for($admin))->toHaveCount(0);

    $this->actingAs($admin, 'admin');
    Livewire::test(Dashboard::class)->assertDontSee('data-dashboard-alerts', false);
});

it('stoc sub minim: apare doar pentru produsele cu prag atins', function () {
    $admin = r75Admin();
    StockItem::create(['name' => 'Vodcă', 'unit' => 'ml', 'stock_qty' => 100, 'min_stock' => 500, 'is_active' => true, 'created_by' => $admin->id]);
    StockItem::create(['name' => 'Bere', 'unit' => 'buc', 'stock_qty' => 100, 'min_stock' => 10, 'is_active' => true, 'created_by' => $admin->id]);
    StockItem::create(['name' => 'Fără prag', 'unit' => 'buc', 'stock_qty' => 0, 'is_active' => true, 'created_by' => $admin->id]);
    StockItem::create(['name' => 'Oprit', 'unit' => 'buc', 'stock_qty' => 0, 'min_stock' => 5, 'is_active' => false, 'created_by' => $admin->id]);

    $alert = DashboardAlerts::for($admin)->firstWhere('key', 'low_stock');

    expect($alert['count'])->toBe(1)->and($alert['detail'])->toContain('Vodcă')->and($alert['detail'])->not->toContain('Bere');
});

it('sesiune de recepție uitată deschisă, dar nu una recentă', function () {
    $admin = r75Admin();
    $party = r75Party();
    $fresh = ReceptionSession::create(['party_id' => $party->id, 'session_number' => 1, 'status' => 'open', 'opened_by' => $admin->id]);

    expect(r75Keys($admin))->not->toContain('stale_reception');

    DB::table('reception_sessions')->where('id', $fresh->id)->update(['created_at' => now()->subHours(ReceptionSession::STALE_HOURS + 1)]);

    expect(r75Keys($admin))->toContain('stale_reception');
});

it('diferență de casă la Recepție: doar în ultimele 7 zile și doar dacă e nenulă', function () {
    $admin = r75Admin();
    $party = r75Party();
    $session = ReceptionSession::create(['party_id' => $party->id, 'session_number' => 1, 'status' => 'closed', 'opened_by' => $admin->id]);
    $report = ReceptionReport::create(['reception_session_id' => $session->id, 'party_id' => $party->id, 'date' => now()->toDateString(), 'status' => 'finalized', 'created_by' => $admin->id]);

    DB::table('reception_reports')->where('id', $report->id)->update(['cash_diff' => -12.5, 'finalized_at' => now()->subDay()]);
    expect(DashboardAlerts::for($admin)->firstWhere('key', 'cash_reception')['detail'])->toContain('−12,50 lei');

    DB::table('reception_reports')->where('id', $report->id)->update(['cash_diff' => 0]);
    expect(r75Keys($admin))->not->toContain('cash_reception');

    DB::table('reception_reports')->where('id', $report->id)->update(['cash_diff' => -12.5, 'finalized_at' => now()->subDays(9)]);
    expect(r75Keys($admin))->not->toContain('cash_reception');
});

it('plăți blocate: rezervări și încărcări expirate de mai mult de 30 de minute', function () {
    $admin = r75Admin();
    $party = r75Party();
    $p = r75Participant();

    $order = Order::create(['uuid' => (string) Str::uuid(), 'participant_id' => $p->id, 'party_id' => $party->id, 'tickets_count' => 1, 'subtotal' => 50, 'total' => 50,
        'payment_status' => Order::PAY_CARD_PENDING, 'payment_expires_at' => now()->subMinutes(20)]);
    $topup = CreditTopup::create(['uuid' => (string) Str::uuid(), 'participant_id' => $p->id, 'amount' => 50, 'status' => CreditTopup::PENDING, 'expires_at' => now()->subMinutes(20)]);

    expect(r75Keys($admin))->not->toContain('stuck_orders')->and(r75Keys($admin))->not->toContain('stuck_topups');

    $order->update(['payment_expires_at' => now()->subMinutes(45)]);
    $topup->update(['expires_at' => now()->subMinutes(45)]);

    $keys = r75Keys($admin);
    expect($keys)->toContain('stuck_orders')->and($keys)->toContain('stuck_topups')
        ->and(DashboardAlerts::for($admin)->first()['level'])->toBe('danger');
});

it('fiecare alertă se vede doar cu acces la secțiunea ei', function () {
    $full = r75Admin();
    StockItem::create(['name' => 'Vodcă', 'unit' => 'ml', 'stock_qty' => 1, 'min_stock' => 5, 'is_active' => true, 'created_by' => $full->id]);
    $limited = r75Admin('admin', Permissions::none());

    expect(r75Keys($full))->toContain('low_stock')->and(r75Keys($limited))->toBe([]);
});

it('cardul se afișează în dashboard când există alerte, sus de tot', function () {
    $admin = r75Admin();
    StockItem::create(['name' => 'Vodcă', 'unit' => 'ml', 'stock_qty' => 1, 'min_stock' => 5, 'is_active' => true, 'created_by' => $admin->id]);

    $this->actingAs($admin, 'admin');
    Livewire::test(Dashboard::class)->assertSee('data-dashboard-alerts', false)->assertSee('Stoc sub minim');

    expect(DashboardSections::all()[0]['key'])->toBe('alerts');
});

it('blocul Aplicație numără conturile, activitatea și notificările', function () {
    $a = r75Participant(['phone_verified_at' => now()->subDays(60), 'last_seen_at' => now()->subDays(2)]);
    r75Participant(['last_seen_at' => now()->subDays(20)]);
    r75Participant(['password' => null]);   // fără cont

    $s = AppDashboard::stats();

    expect($s->accounts)->toBe(2)->and($s->new30)->toBe(1)->and($s->active7)->toBe(1)->and($s->active30)->toBe(2);
});

it('metricile aplicației: vizită, deschidere din aplicația instalată și instalare ajung în dashboard', function () {
    $hit = fn (array $e) => $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (Linux; Android 14) Chrome/120'])->postJson('/m', ['e' => $e])->assertNoContent();

    $hit([['x', 1, 'v'], ['x', 1, 's']]);
    $hit([['x', 1, 'n']]);

    $s = AppDashboard::stats();

    expect($s->visits7)->toBe(1)->and($s->standalone7)->toBe(1)->and($s->installs30)->toBe(1)->and($s->installs_all)->toBe(1);
    expect(ContentStats::TYPES)->toContain('app');
});

it('activitatea participantului se ține cel mult o dată pe oră', function () {
    $p = r75Participant();
    $this->actingAs($p, 'participant');

    $this->get('/cont');
    $first = $p->fresh()->last_seen_at;
    expect($first)->not->toBeNull();

    $this->travel(10)->minutes();
    $this->get('/cont');
    expect($p->fresh()->last_seen_at->equalTo($first))->toBeTrue();

    $this->travel(2)->hours();
    $this->get('/cont');
    expect($p->fresh()->last_seen_at->greaterThan($first))->toBeTrue();
});
