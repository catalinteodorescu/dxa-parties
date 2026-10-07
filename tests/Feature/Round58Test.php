<?php

use App\Livewire\Admin\Participants\Show;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Party;
use App\Models\PartyEntry;
use App\Services\EntryRecorder;
use App\Services\ParticipantRegistry;
use App\Services\PartyStats;
use App\Services\ReceptionSummary;
use App\Services\TicketOrders;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

/** DXA: teste (runda 58). Intrarea cu bilet plătit online (card / credite) nu mai e „gratuită": numără la Plătite. */
function r58Party(): Party
{
    Carbon::setTestNow(Carbon::parse('2026-10-03 21:30:00'));

    return Party::create([
        'name' => 'Petrecere R58', 'kind' => 'basic', 'start_date' => '2026-10-03', 'start_time' => '21:00', 'end_time' => '03:00',
        'is_free' => false, 'audience' => 'all', 'in_carousel' => false, 'is_active' => true, 'status' => 'published',
        'payment_methods' => ['cash'], 'online_sales' => true,
        'ticket_types' => [['name' => 'Bilet', 'price' => 50, 'discounts' => [], 'qty_tiers' => []]],
    ]);
}

it('intrarea cu bilet plătit online e „plătită"; cu bilet de plătit la intrare sau gratuit rămâne cum era', function () {
    $party = r58Party();
    $ana = ParticipantRegistry::create('Ana', '0722111222');
    $ana->forceFill(['password' => 'parola-sigura', 'phone_verified_at' => now()])->save();
    $admin = Admin::create(['name' => 'Rec', 'phone' => '0788111222', 'role' => 'admin', 'permissions' => Permissions::legacyAdmin(), 'is_active' => true, 'access_admin' => true, 'access_reception' => true, 'password' => 'secret-pass']);

    $online = TicketOrders::place($ana, $party, 'Bilet', 1);
    $online->forceFill(['payment_status' => Order::PAY_PAID])->save();
    EntryRecorder::record($party, 'Bilet', 1, [], adminId: $admin->id, ticketIds: [$online->tickets[0]->id], participants: [$ana->id]);

    // O intrare fără bilet, gratuită (suprascriere de preț cu motiv).
    EntryRecorder::record($party, 'Bilet', 1, [], 0, 'invitat', $admin->id);

    $att = PartyStats::attendance($party);
    expect($att->count)->toBe(2)->and($att->paid)->toBe(1)->and($att->free)->toBe(1);

    $session = PartyEntry::query()->first()->session;
    expect(ReceptionSummary::for($session)->entries_free)->toBe(1);

    $this->actingAs($admin, 'admin');
    Livewire::test(Show::class, ['participant' => $ana])->assertViewHas('paidEntries', 1)->assertViewHas('freeEntries', 0);
});
