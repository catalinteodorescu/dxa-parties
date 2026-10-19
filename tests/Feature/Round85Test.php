<?php

use App\Models\Admin;
use App\Models\Participant;
use App\Models\Party;
use App\Models\Sale;
use App\Services\PrivacyPolicy;
use App\Services\Terms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Output\BufferedOutput;

uses(RefreshDatabase::class);

function r85Admin(): Admin
{
    return Admin::firstOrCreate(['phone' => '0711000000'], ['name' => 'Admin', 'password' => Hash::make('x-parola-lunga-1'), 'role' => 'superadmin', 'is_active' => true]);
}

it('refuză datele demo când DEMO_MODE nu e activ', function () {
    r85Admin();
    config(['app.demo' => false]);
    $out = new BufferedOutput;
    expect(Artisan::call('app:demo-seed', [], $out))->toBe(1)
        ->and(Party::count())->toBe(0);
});

it('încarcă date demo coerente și e idempotent', function () {
    r85Admin();
    config(['app.demo' => true]);
    Artisan::call('app:demo-seed', [], new BufferedOutput);

    expect(Party::where('name', 'like', '%(demo)')->count())->toBe(7)
        ->and(Participant::count())->toBe(20)
        ->and(Sale::count())->toBeGreaterThan(20);

    expect(Terms::enabled())->toBeTrue()->and(PrivacyPolicy::enabled())->toBeTrue();

    $count = Party::count();
    Artisan::call('app:demo-seed', [], new BufferedOutput);
    expect(Party::count())->toBe($count);

    $all = json_encode(Party::pluck('name')).json_encode(Participant::pluck('name'));
    expect(stripos($all, 'dxa'))->toBeFalse();
});

it('servește fișierele încărcate prin ruta de rezervă când lipsește legătura public/storage', function () {
    Storage::fake('public');
    Storage::disk('public')->put('branding/logo.png', 'x');

    $this->get('/storage/branding/logo.png')->assertOk();
    $this->get('/storage/branding/lipsa.png')->assertNotFound();
});
