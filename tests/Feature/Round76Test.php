<?php

use App\Console\Commands\ExpirePayments;
use App\Livewire\Admin\Credits\Topups;
use App\Livewire\Admin\Dashboard;
use App\Models\Admin;
use App\Models\CreditTopup;
use App\Models\Order;
use App\Models\Participant;
use App\Models\Party;
use App\Models\StockItem;
use App\Services\DashboardAlerts;
use App\Support\Permissions;
use Illuminate\Console\OutputStyle;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

uses(RefreshDatabase::class);

/** DXA: teste (runda 76). Alerte cu explicație, „Încărcări cu cardul” și închiderea automată a plăților expirate. */
function r76Admin(string $role = 'superadmin', ?array $permissions = null): Admin
{
    return Admin::create([
        'name' => 'Cont '.random_int(1, 99999), 'phone' => '07'.random_int(10000000, 99999999), 'role' => $role,
        'permissions' => $permissions ?? Permissions::legacyAdmin(), 'is_active' => true,
        'access_admin' => true, 'access_bar' => true, 'access_reception' => true, 'password' => 'secret-pass',
    ]);
}

function r76Participant(string $name = 'Ioana Test'): Participant
{
    return Participant::create(['uuid' => (string) Str::uuid(), 'name' => $name, 'phone' => '07'.random_int(10000000, 99999999), 'password' => 'parola-sigura', 'phone_verified_at' => now()]);
}

/** Rulează comanda fără consola Symfony (în mediul de test wasm `artisan()` se oprește). */
function r76RunExpire(): int
{
    $cmd = app(ExpirePayments::class);
    $cmd->setLaravel(app());
    $cmd->setOutput(new OutputStyle(new ArrayInput([]), new NullOutput));

    return $cmd->handle();
}

function r76Topup(Participant $p, string $status, int $expiredMinutesAgo = 0, ?string $ref = null): CreditTopup
{
    return CreditTopup::create(['uuid' => (string) Str::uuid(), 'participant_id' => $p->id, 'amount' => 50, 'bonus' => 5, 'status' => $status,
        'expires_at' => now()->subMinutes($expiredMinutesAgo), 'provider_ref' => $ref]);
}

function r76Order(Participant $p): Order
{
    $party = Party::create([
        'name' => 'Petrecere plăți', 'kind' => 'basic', 'start_date' => now()->addDays(3)->toDateString(), 'start_time' => '21:00', 'end_time' => '03:00',
        'is_free' => false, 'audience' => 'all', 'in_carousel' => false, 'is_active' => true, 'status' => 'published',
        'payment_methods' => ['cash'], 'online_sales' => true,
        'ticket_types' => [['name' => 'Bilet', 'price' => 50, 'discounts' => [], 'qty_tiers' => []]],
    ]);

    return Order::create(['uuid' => (string) Str::uuid(), 'participant_id' => $p->id, 'party_id' => $party->id, 'tickets_count' => 1, 'subtotal' => 50, 'total' => 50,
        'payment_status' => Order::PAY_CARD_PENDING, 'payment_expires_at' => now()->subHour()]);
}

it('comanda app:expire-payments închide rezervările și încărcările expirate, nu pe cele încă valabile', function () {
    $p = r76Participant();
    $oldTopup = r76Topup($p, CreditTopup::PENDING, 60);
    $liveTopup = CreditTopup::create(['uuid' => (string) Str::uuid(), 'participant_id' => $p->id, 'amount' => 20, 'status' => CreditTopup::PENDING, 'expires_at' => now()->addMinutes(20)]);
    $order = r76Order($p);

    expect(r76RunExpire())->toBe(0);

    expect($oldTopup->fresh()->status)->toBe(CreditTopup::EXPIRED)
        ->and($liveTopup->fresh()->status)->toBe(CreditTopup::PENDING)
        ->and($order->fresh()->payment_status)->toBe(Order::PAY_CARD_EXPIRED);
});

it('comanda e programată la 15 minute', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'app:expire-payments'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('*/15 * * * *');
});

it('după închiderea automată, plățile expirate nu mai dau alertă', function () {
    $admin = r76Admin();
    $p = r76Participant();
    r76Topup($p, CreditTopup::PENDING, 90);
    r76Order($p);

    expect(DashboardAlerts::for($admin)->pluck('key')->all())->toContain('stuck_topups', 'stuck_orders');

    r76RunExpire();

    expect(DashboardAlerts::for($admin)->pluck('key')->all())->not->toContain('stuck_topups')->not->toContain('stuck_orders');
});

it('fiecare alertă are explicație și un link către o pagină existentă', function () {
    $admin = r76Admin();
    $p = r76Participant();
    StockItem::create(['name' => 'Vodcă', 'unit' => 'ml', 'stock_qty' => 1, 'min_stock' => 5, 'is_active' => true, 'created_by' => $admin->id]);
    r76Topup($p, CreditTopup::PENDING, 90);
    r76Order($p);

    $alerts = DashboardAlerts::for($admin);
    expect($alerts)->not->toBeEmpty();

    foreach ($alerts as $a) {
        expect(strlen($a['help']))->toBeGreaterThan(40);
        $this->actingAs($admin, 'admin')->get($a['href'])->assertOk();
    }
});

it('dashboardul arată butonul „Ce înseamnă?” cu explicația', function () {
    $admin = r76Admin();
    StockItem::create(['name' => 'Vodcă', 'unit' => 'ml', 'stock_qty' => 1, 'min_stock' => 5, 'is_active' => true, 'created_by' => $admin->id]);

    $this->actingAs($admin, 'admin');
    Livewire::test(Dashboard::class)->assertSee('data-alert-help', false)->assertSee('Ce înseamnă?')->assertSee('Fă un necesar');
});

it('pagina Încărcări cu cardul: listă, stări, referință Stripe și filtru', function () {
    $this->actingAs(r76Admin(), 'admin');
    $p = r76Participant('Maria Plată');
    r76Topup($p, CreditTopup::PAID, 0, 'cs_test_abc123');
    r76Topup($p, CreditTopup::EXPIRED, 5);
    r76Topup($p, CreditTopup::PENDING, 0);

    Livewire::test(Topups::class)
        ->assertSee('Maria Plată')->assertSee('cs_test_abc123')->assertSee('+ bonus')
        ->assertSeeHtml('data-topup="paid"')->assertSeeHtml('data-topup="expired"')
        ->set('filterState', 'paid')->assertSeeHtml('data-topup="paid"')->assertDontSeeHtml('data-topup="expired"');
});

it('pagina cere permisiunea Credite și e legată din pagina Credite', function () {
    $this->actingAs(r76Admin('admin', Permissions::none()), 'admin')->get(route('admin.credits.topups'))->assertForbidden();

    $this->actingAs(r76Admin(), 'admin')->get(route('admin.credits.index'))->assertSee(route('admin.credits.topups'), false);
});
