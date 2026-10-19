<?php

use App\Contracts\SmsSender;
use App\Livewire\Admin\ForgotPassword as AdminForgotPassword;
use App\Livewire\Admin\Users\Create as UsersCreate;
use App\Models\Admin;
use App\Services\ParticipantAccounts;
use App\Services\ParticipantRegistry;
use App\Services\Plan\SmsUsage;
use App\Services\Sms\AsciiSmsSender;
use App\Services\Sms\CountingSmsSender;
use App\Services\Sms\GuardedSmsSender;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\SmsDeliveryException;
use App\Services\Sms\SmsLimitException;
use App\Services\Sms\SmsoSmsSender;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** DXA: teste (runda 81). Driverul SMSO, alegerea driverului din config și limitele anti-abuz la trimiterea de SMS-uri. */
function r81Recorder(): SmsSender
{
    return new class implements SmsSender
    {
        /** @var list<array{0: string, 1: string}> */
        public array $sent = [];

        public function send(string $phone, string $message): void
        {
            $this->sent[] = [$phone, $message];
        }
    };
}

// ---------- driverul SMSO ----------

it('r81: SMSO primește cheia în antet și to/body/sender ca formular, cu telefonul în format +40', function () {
    Http::fake(['app.smso.ro/*' => Http::response(['responseToken' => 'abc'], 200)]);

    (new SmsoSmsSender('cheie-secreta', '4'))->send('0722 123 456', 'Cod: 123456');

    Http::assertSent(function (HttpRequest $r) {
        return $r->url() === 'https://app.smso.ro/api/v1/send'
            && $r->method() === 'POST'
            && $r->header('X-Authorization') === ['cheie-secreta']
            && $r['to'] === '+40722123456' && $r['body'] === 'Cod: 123456' && $r['sender'] === '4';
    });
});

it('r81: un răspuns de eroare sau lipsa conexiunii aruncă o excepție cu mesaj sigur, iar SMS-ul nu se numără', function () {
    Http::fake(['app.smso.ro/*' => Http::response(['error' => 'sold insuficient'], 402)]);
    $sender = new CountingSmsSender(new SmsoSmsSender('k', '4'));

    expect(fn () => $sender->send('0722123456', 'x'))->toThrow(SmsDeliveryException::class, SmsDeliveryException::MESSAGE);

    Http::swap(new Factory);
    Http::fake(['app.smso.ro/*' => fn () => throw new ConnectionException('timeout')]);
    expect(fn () => $sender->send('0722123456', 'x'))->toThrow(SmsDeliveryException::class);

    expect(SmsUsage::report()['sent'])->toBe(0)
        ->and(fn () => (new SmsoSmsSender('k', '4'))->send('abc', 'x'))->toThrow(RuntimeException::class);
});

it('r81: driverul se alege din config: log implicit, smso la cerere, mereu cu limite, numărare și fără diacritice', function () {
    config(['sms.driver' => 'log']);
    expect(app(SmsSender::class))->toBeInstanceOf(CountingSmsSender::class);

    Log::spy();
    app(SmsSender::class)->send('0722123456', 'Ți-am trimis „codul”');
    Log::shouldHaveReceived('info')->once()->withArgs(fn ($m) => str_contains($m, 'Ti-am trimis "codul"'));

    config(['sms.driver' => 'smso', 'sms.smso.key' => 'k', 'sms.smso.sender' => '7']);
    Http::fake(['app.smso.ro/*' => Http::response([], 200)]);
    app(SmsSender::class)->send('0722123456', 'Ți-am trimis codul');
    Http::assertSent(fn (HttpRequest $r) => $r['sender'] === '7' && $r['body'] === 'Ti-am trimis codul');
    expect(SmsUsage::report()['sent'])->toBe(2);
});

// ---------- limite anti-abuz ----------

it('r81: cel mult 6 SMS pe oră pe destinatar, indiferent de tipul mesajului; altul poate primi', function () {
    $inner = r81Recorder();
    $sender = new GuardedSmsSender($inner);

    foreach (range(1, 6) as $i) {
        $sender->send('0722123456', "m{$i}");
    }
    expect(fn () => $sender->send('+40722123456', 'm7'))->toThrow(SmsLimitException::class, GuardedSmsSender::MESSAGE);
    $sender->send('0733000111', 'alt număr');

    expect($inner->sent)->toHaveCount(7);
});

it('r81: limita pe IP și limita zilnică a instalării opresc trimiterea în masă, fără să atingă contorul de facturare', function () {
    config(['sms.limits.per_ip' => 3, 'sms.limits.daily' => 0]);
    $inner = r81Recorder();
    $sender = new CountingSmsSender(new GuardedSmsSender($inner));

    foreach (['0700000001', '0700000002', '0700000003'] as $p) {
        $sender->send($p, 'x');
    }
    expect(fn () => $sender->send('0700000004', 'x'))->toThrow(SmsLimitException::class)
        ->and(SmsUsage::report()['sent'])->toBe(3);

    RateLimiter::clear('sms:ip:127.0.0.1');
    config(['sms.limits.per_ip' => 0, 'sms.limits.daily' => 2]);
    $sender->send('0700000011', 'x');
    $sender->send('0700000012', 'x');
    expect(fn () => $sender->send('0700000013', 'x'))->toThrow(SmsLimitException::class);
});

it('r81: un SMS respins de furnizor nu consumă din limita destinatarului', function () {
    Http::fake(['app.smso.ro/*' => Http::response([], 500)]);
    $sender = new GuardedSmsSender(new SmsoSmsSender('k', '4'));

    foreach (range(1, 8) as $_) {
        expect(fn () => $sender->send('0722123456', 'x'))->toThrow(SmsDeliveryException::class);
    }
});

// ---------- locurile care trimit ----------

it('r81: resetarea parolei administratorului: 3 cereri pe oră pe număr, același răspuns neutru', function () {
    $admin = Admin::create([
        'name' => 'Admin Reset', 'phone' => '0722111222', 'role' => 'admin', 'is_active' => true,
        'password' => 'secret-pass-123', 'permissions' => Permissions::legacyAdmin(),
    ]);
    $inner = r81Recorder();
    app()->instance(SmsSender::class, $inner);

    foreach (range(1, 5) as $_) {
        Livewire::test(AdminForgotPassword::class)->set('phone', $admin->phone)->call('send')->assertSet('sent', true);
    }

    expect($inner->sent)->toHaveCount(3);
});

it('r81: resetarea parolei participantului rămâne neutră când furnizorul cade', function () {
    $p = ParticipantRegistry::create('Ana Reset', '0722333444');
    $p->forceFill(['password' => 'parola-sigura', 'phone_verified_at' => now()])->save();
    Http::fake(['app.smso.ro/*' => Http::response([], 500)]);

    $sms = new CountingSmsSender(new GuardedSmsSender(new AsciiSmsSender(new SmsoSmsSender('k', '4'))));
    ParticipantAccounts::sendResetLink('0722333444', $sms);   // nu aruncă

    expect(SmsUsage::report()['sent'])->toBe(0);
});

it('r81: invitația unui cont nou: dacă SMS-ul nu pleacă, contul rămâne creat și apare o eroare clară', function () {
    $super = Admin::create([
        'name' => 'Super', 'phone' => '0700999888', 'role' => 'superadmin', 'is_active' => true,
        'password' => 'secret-pass-123', 'permissions' => Permissions::legacyAdmin(),
    ]);
    Http::fake(['app.smso.ro/*' => Http::response([], 500)]);
    app()->instance(SmsSender::class, new SmsoSmsSender('k', '4'));

    Livewire::actingAs($super, 'admin')->test(UsersCreate::class)
        ->set('phone', '0711222333')->call('save');

    expect(Admin::query()->where('phone', 'like', '%711222333')->exists())->toBeTrue()
        ->and(session('error'))->toContain('invitația nu a plecat');
});

it('r81: env-ul exemplu și config-ul documentează driverul și limitele', function () {
    $env = file_get_contents(base_path('.env.example'));
    expect($env)->toContain('SMS_DRIVER=log')->toContain('SMSO_API_KEY=')->toContain('SMSO_SENDER=')->toContain('SMS_LIMIT_DAILY=')
        ->and(config('sms.limits'))->toBe(['per_phone' => 6, 'per_ip' => 60, 'daily' => 1000])
        ->and(DB::getSchemaBuilder()->hasTable('sms_usage'))->toBeTrue();
    expect(new LogSmsSender)->toBeInstanceOf(SmsSender::class);
});
