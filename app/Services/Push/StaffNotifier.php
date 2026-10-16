<?php

namespace App\Services\Push;

use App\Models\Admin;
use App\Models\PushDelivery;
use App\Models\PushNotification;
use App\Models\PushSubscription;
use App\Support\Settings\Settings;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * DXA: adaugat (runda 77). Notificări push pentru telefoanele din aplicațiile Recepție și Bar. Folosește aceleași tabele, aceleași chei
 * VAPID și aceeași trimitere ca notificările participanților (Notifier), dar abonarea aparține contului cu care s-a făcut login (`admin_id`)
 * și aplicației în care s-a făcut (`app`: reception | bar).
 *
 * Destinatari: conturile active cu acces la aplicație (bifa „Aplicație recepție” / „Aplicație bar”), pe telefoanele abonate în ACEA aplicație.
 * Cine lucrează în aplicație nu alege tipurile: primește ce s-a bifat în admin, la Setări › Aplicație recepție / bar, de către cine are acțiunea
 * `manage_staff_notifications`. În panoul admin nu există notificări. Nu există ore liniștite (rapoartele se depun noaptea).
 */
class StaffNotifier
{
    public const APPS = ['reception', 'bar'];

    /**
     * cheie => [aplicația, etichetă, cui și când, exemplu [titlu, text]]. Un tip oprit nu se trimite și nu se amână (evenimentul se sare).
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: array{0: string, 1: string}}>
     */
    public const TYPES = [
        'r_open' => ['reception', 'Deschide casa', 'Cu cel mult 2 ore înainte de începutul petrecerii, dacă sesiunea de recepție nu e deja deschisă.',
            ['Deschide casa', 'Latino Night începe la 21:00. Pornește sesiunea de recepție.']],
        'r_not_open' => ['reception', 'Casă nedeschisă', 'Cu 30 de minute înainte de petrecere, dacă tot nu există nicio sesiune de recepție.',
            ['Casa nu e deschisă', 'Latino Night începe la 21:00 și nu e nicio sesiune de recepție.']],
        'r_party_end' => ['reception', 'Final de petrecere', 'La 30 de minute după ora de sfârșit, către cel care a deschis sesiunea, dacă raportarea nu e depusă.',
            ['Petrecerea s-a terminat', 'Latino Night: numără casa și depune raportarea.']],
        'r_report_missing' => ['reception', 'Raportare nedepusă', 'Către cel care a deschis sesiunea, dimineața (09:00–12:00), dacă sesiunea e deschisă de peste 6 ore și raportarea nu e depusă.',
            ['Raportare nedepusă', 'Latino Night: sesiunea e încă deschisă. Depune raportarea.']],
        'r_session_stale' => ['reception', 'Sesiune uitată deschisă', 'Către cel care a deschis sesiunea, când rămâne deschisă peste 12 ore fără raportare depusă (aceeași alertă ca în dashboard). Dacă a primit deja „Raportare nedepusă” pentru sesiune, nu mai primește și asta.',
            ['Sesiune uitată deschisă', 'Latino Night: deschisă de peste 12 ore. Depune raportarea.']],
        'r_sold_out' => ['reception', 'Petrecere epuizată', 'Când se vinde ultimul bilet online al unei petreceri.',
            ['Petrecere epuizată', 'Latino Night: toate cele 200 de bilete sunt vândute.']],

        'b_open' => ['bar', 'Deschide barul', 'Cu cel mult 2 ore înainte de începutul petrecerii, dacă sesiunea de bar nu e deja deschisă.',
            ['Deschide barul', 'Latino Night începe la 21:00. Pregătește barul.']],
        'b_not_open' => ['bar', 'Bar nedeschis', 'Cu 30 de minute înainte de petrecere, dacă tot nu există nicio sesiune de bar.',
            ['Barul nu e deschis', 'Latino Night începe la 21:00 și nu e nicio sesiune de bar.']],
        'b_party_end' => ['bar', 'Final de petrecere', 'La 30 de minute după ora de sfârșit, către cel care a deschis sesiunea, dacă raportarea nu e depusă.',
            ['Petrecerea s-a terminat', 'Latino Night: numără casa și depune raportarea.']],
        'b_report_missing' => ['bar', 'Raportare nedepusă', 'Către cel care a deschis sesiunea, dimineața (09:00–12:00), dacă sesiunea e deschisă de peste 6 ore și raportarea nu e depusă.',
            ['Raportare nedepusă', 'Latino Night: sesiunea de bar e încă deschisă. Depune raportarea.']],
        'b_session_stale' => ['bar', 'Sesiune uitată deschisă', 'Către cel care a deschis sesiunea, când rămâne deschisă peste 12 ore fără raportare depusă. Dacă a primit deja „Raportare nedepusă” pentru sesiune, nu mai primește și asta.',
            ['Sesiune uitată deschisă', 'Latino Night: deschisă de peste 12 ore. Depune raportarea.']],
        'b_low_stock' => ['bar', 'Stoc sub minim', 'O dată pe produs, când stocul atinge pragul minim (verificat la 15 minute). Reîncepe după ce stocul se reface.',
            ['Stoc sub minim', 'Vodcă: au rămas 800 ml (minim 1.000).']],
        'b_sold_out' => ['bar', 'Produs epuizat', 'Când un coleg marchează un produs ca epuizat (cel care l-a marcat nu primește notificarea).',
            ['Produs epuizat', 'Mojito a fost marcat epuizat la Latino Night de Andrei.']],
    ];

    public static function enabled(): bool
    {
        return Notifier::enabled();
    }

    public static function typeEnabled(string $kind): bool
    {
        if ($kind === 'test' || ! isset(self::TYPES[$kind])) {
            return true;
        }

        return Settings::get('staff_push_type_'.$kind) !== '0';
    }

    public static function setType(string $kind, bool $on): void
    {
        if (isset(self::TYPES[$kind])) {
            Settings::set('staff_push_type_'.$kind, $on ? '1' : '0');
        }
    }

    /** @return array<string, array{0: string, 1: string, 2: string, 3: array{0: string, 1: string}}> tipurile unei aplicații */
    public static function typesFor(string $app): array
    {
        return array_filter(self::TYPES, fn ($t) => $t[0] === $app);
    }

    // ---- Abonări -----------------------------------------------------------------

    public static function subscribe(Admin $a, string $app, string $endpoint, ?string $p256dh = null, ?string $auth = null, ?string $userAgent = null): PushSubscription
    {
        $hash = PushSubscription::hashOf($endpoint);
        $sub = PushSubscription::query()->where('endpoint_hash', $hash)->first() ?? new PushSubscription(['endpoint_hash' => $hash]);
        $sub->fill([
            'participant_id' => null,
            'admin_id' => $a->id,
            'app' => $app,
            'endpoint' => $endpoint,
            'p256dh' => $p256dh,
            'auth' => $auth,
            'user_agent' => $userAgent !== null ? Str::limit($userAgent, 250, '') : null,
            'last_used_at' => now(),
        ])->save();

        return $sub;
    }

    public static function unsubscribe(string $endpoint, ?Admin $a = null): void
    {
        PushSubscription::query()->where('endpoint_hash', PushSubscription::hashOf($endpoint))->whereNotNull('admin_id')
            ->when($a, fn ($q) => $q->where('admin_id', $a->id))->delete();
    }

    public static function hasSubscription(Admin $a, ?string $app = null): bool
    {
        return PushSubscription::query()->where('admin_id', $a->id)->when($app, fn ($q) => $q->where('app', $app))->exists();
    }

    /**
     * Cine e abonat în aplicație: numărul de telefoane și conturile cu acces fără niciun telefon abonat (pentru Setări).
     *
     * @return array{phones: int, accounts: int, without: list<string>}
     */
    public static function phonesSummary(string $app): array
    {
        $workers = self::workers($app);
        $subs = PushSubscription::query()->where('app', $app)->whereIn('admin_id', $workers->pluck('id'))->get();
        $with = $subs->pluck('admin_id')->unique();

        return [
            'phones' => $subs->count(),
            'accounts' => $with->count(),
            'without' => $workers->reject(fn (Admin $a) => $with->contains($a->id))->map(fn (Admin $a) => $a->name ?: $a->phone)->values()->all(),
        ];
    }

    // ---- Destinatari -------------------------------------------------------------

    /** Conturile active cu acces la aplicație (Recepție / Bar). @return Collection<int, Admin> */
    public static function workers(string $app): Collection
    {
        return Admin::query()->where('is_active', true)->get()->filter(fn (Admin $a) => $a->canAccess($app))->values();
    }

    // ---- Trimitere ---------------------------------------------------------------

    /**
     * Creează notificarea și trimite semnalul către telefoanele contului abonate în aplicația `$app`. Null dacă push nu e configurat,
     * tipul e oprit, contul n-are telefoane abonate în aplicație sau aceeași cheie a fost deja trimisă contului.
     */
    public static function notify(Admin $a, string $kind, string $key, string $title, string $body, string $url, string $app): ?PushNotification
    {
        if (! self::enabled() || ! self::typeEnabled($kind)) {
            return null;
        }

        $subs = PushSubscription::query()->where('admin_id', $a->id)->where('app', $app)->get();
        if ($subs->isEmpty()) {
            return null;
        }

        $key = Str::limit($key, 120, '');
        if (PushNotification::query()->where('admin_id', $a->id)->where('dedupe_key', $key)->exists()) {
            return null;
        }

        try {
            $n = PushNotification::create([
                'admin_id' => $a->id,
                'app' => $app,
                'kind' => $kind,
                'dedupe_key' => $key,
                'title' => Str::limit($title, 120, ''),
                'body' => Str::limit($body, 250, ''),
                'url' => Str::limit($url, 250, ''),
            ]);
        } catch (QueryException) {
            return null;   // cheie unică: deja trimisă
        }

        $gateway = app(PushGateway::class);
        foreach ($subs as $sub) {
            $delivery = PushDelivery::create(['push_notification_id' => $n->id, 'push_subscription_id' => $sub->id]);
            $status = $gateway->send($sub, 'high');
            $delivery->update(['sent_at' => now(), 'status' => $status]);
            if ($status === 404 || $status === 410) {
                $sub->delete();
            }
        }

        return $n;
    }

    /** Trimite unui grup de conturi (cheia se aplică per cont). @param  Collection<int, Admin>  $admins @return int câte notificări au fost create */
    public static function notifyMany(Collection $admins, string $kind, string $key, string $title, string $body, string $url, string $app): int
    {
        return $admins->filter(fn (Admin $a) => self::notify($a, $kind, $key, $title, $body, $url, $app) !== null)->count();
    }
}
