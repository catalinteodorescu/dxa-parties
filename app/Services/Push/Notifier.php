<?php

namespace App\Services\Push;

use App\Models\Participant;
use App\Models\PushDelivery;
use App\Models\PushNotification;
use App\Models\PushSubscription;
use App\Support\Settings\Settings;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * DXA: adaugat (runda 70). Nucleul notificărilor push: abonări, creare notificare (o singură dată per participant și cheie),
 * trimiterea semnalului către fiecare telefon abonat și citirea conținutului de către service worker.
 *
 * Fără conținut în cererea de push: browserul primește doar „ai ceva nou”, iar service worker-ul cere textul de la
 * `POST /notificari/necitite` (cu endpoint-ul abonării, care e secret). Fiecare telefon primește fiecare notificare o dată.
 */
class Notifier
{
    /** Ore liniștite: tipurile fără urgență nu se trimit între 22:00 și 09:00. */
    public const QUIET_FROM = 22;

    public const QUIET_TO = 9;

    /** Notificările mai vechi de atât nu se mai livrează unui telefon care se conectează târziu. */
    public const DELIVERY_DAYS = 3;

    public static function enabled(): bool
    {
        return Vapid::configured();
    }

    /**
     * Tipurile de notificări pe care adminul le poate porni/opri (Setări › Aplicație participanți › Notificări):
     * cheie => [etichetă, cui și când, exemplu [titlu, text]]. Exemplele sunt statice (arată forma mesajului, cu date inventate).
     * Un tip oprit nu se trimite și nu se amână: evenimentul se sare (ex. o petrecere publicată cât timp „Petrecere nouă” era oprit
     * nu mai e anunțată după repornire). „test” (butonul din Cont) nu se poate opri.
     *
     * @var array<string, array{0: string, 1: string, 2: array{0: string, 1: string}}>
     */
    public const TYPES = [
        'party_new' => ['Petrecere nouă', 'Către toți abonații, o singură dată, când o petrecere e publicată (nu între 22:00 și 09:00). Cei cu intrare gratuită adunată (card de fidelitate) primesc în mesaj „Ai intrare gratuită”.',
            ['Petrecere nouă', 'Latino Night · 10.10.2026, 21:00']],
        'price' => ['Prețul crește', 'Către cei care au petrecerea la favorite și nu au bilet, când o reducere cu dată expiră în 24 de ore și prețul chiar crește. Una la fiecare creștere (nu între 22:00 și 09:00).',
            ['Prețul biletelor crește', 'Latino Night: Ultima zi la prețul actual, de mâine crește (50 lei → 80 lei).']],
        'seats' => ['Puține locuri', 'Către cei care au petrecerea la favorite și nu au bilet, când rămân cel mult 10% din biletele puse la vânzare. O dată pe petrecere (nu între 22:00 și 09:00).',
            ['Au mai rămas puține bilete', 'Latino Night: au mai rămas doar 4 bilete.']],
        'buyers' => ['Bilete luate de alții', 'Către abonații fără bilet, o singură dată pe petrecere, când numărul celor care au cumpărat bilet ajunge la pragul din câmpul de lângă comutator (nu între 22:00 și 09:00). Implicit oprit; se pornește doar după ce completezi pragul.',
            ['Se umple petrecerea', 'Latino Night: 25 de persoane și-au luat deja bilet.']],
        'tomorrow' => ['Petrecerea e mâine', 'Către cei cu bilet, de la 18:00 în ziua dinainte.',
            ['Petrecerea e mâine', 'Mâine, la 21:00, Club Sala. Latino Night.']],
        'ready' => ['Cu 2 ore înainte', 'Către cei cu bilet, „Ești gata de petrecere?”, cu 2 ore înainte de început.',
            ['Latino Night', 'Ești gata de petrecere? Dresscode: Alb.']],
        'ticket_new' => ['Bilet cumpărat sau primit', 'Către cel care a cumpărat biletele (aplicație, card sau credite) sau care a primit un bilet prin transfer, imediat.',
            ['Bilete cumpărate', 'Latino Night: 2 bilete, 100,00 lei.']],
        'ticket_cancelled' => ['Bilet anulat', 'Către deținătorul biletului, imediat după anulare, cu suma returnată în credite.',
            ['Bilet anulat', 'Biletul 3F9A12BC la Latino Night a fost anulat. 50,00 lei au fost returnați în credite.']],
        'credit_payment' => ['Plată cu credite', 'Către participant, imediat după o plată cu credite la Bar sau la Recepție, cu soldul rămas. Biletele cumpărate cu credite în aplicație primesc doar notificarea de bilet.',
            ['Plată cu credite', 'Ai plătit 35,00 lei cu credite la Bar. Sold: 120,00 lei.']],
        'credit_load' => ['Încărcare credite', 'Către participant, imediat după ce i se încarcă credite (în aplicație cu cardul, la Recepție sau de către echipă), cu bonusul și soldul rămas.',
            ['Credite încărcate', 'Ai încărcat 110,00 lei credite la Recepție (din care bonus 10,00 lei). Sold: 130,00 lei.']],
        'card_ready' => ['Card de fidelitate complet', 'Către participant, imediat ce strânge toate ștampilele (la scanarea biletului sau din cont) și are intrare gratuită.',
            ['Ai intrare gratuită', 'Ai strâns toate ștampilele. Următoarea intrare la o petrecere cu fidelitate e gratuită.']],
        'announcement' => ['Anunț nou', 'Către toți abonații, o singură dată, pentru fiecare anunț publicat și vizibil.',
            ['Concurs de dans sâmbătă', 'Înscrie-te până vineri și poți câștiga un abonament la cursurile noastre.']],
    ];

    /** Tipuri oprite implicit: se trimit doar după ce adminul le pornește (și le completează condiția). */
    public const DEFAULT_OFF = ['buyers'];

    /** @var list<string> tipuri suprimate temporar (ex. date de test care nu trebuie să trimită notificări) */
    private static array $muted = [];

    /** Tipul e pornit? Implicit da (exceptând DEFAULT_OFF); „test” e mereu pornit. */
    public static function typeEnabled(string $kind): bool
    {
        if ($kind === 'test' || ! isset(self::TYPES[$kind])) {
            return true;
        }

        return in_array($kind, self::DEFAULT_OFF, true) ? Settings::get('push_type_'.$kind) === '1' : Settings::get('push_type_'.$kind) !== '0';
    }

    /** Pragul „bilete luate de alții”: câte persoane au cumpărat bilet; null dacă nu e completat. */
    public static function buyersThreshold(): ?int
    {
        $v = Settings::get('push_buyers_threshold');

        return is_numeric($v) && (int) $v >= 1 ? (int) $v : null;
    }

    public static function setBuyersThreshold(?int $n): void
    {
        Settings::set('push_buyers_threshold', $n !== null && $n >= 1 ? (string) $n : '');
    }

    /** Rulează $fn fără a trimite notificări de tipurile date (folosit la datele de test). @template T @param  callable(): T  $fn @return T */
    public static function muting(array $kinds, callable $fn): mixed
    {
        $before = self::$muted;
        self::$muted = array_values(array_unique(array_merge($before, $kinds)));
        try {
            return $fn();
        } finally {
            self::$muted = $before;
        }
    }

    public static function setType(string $kind, bool $on): void
    {
        if (isset(self::TYPES[$kind])) {
            Settings::set('push_type_'.$kind, $on ? '1' : '0');
        }
    }

    public static function isQuietHour(?Carbon $at = null): bool
    {
        $h = ($at ?? now())->hour;

        return $h >= self::QUIET_FROM || $h < self::QUIET_TO;
    }

    // ---- Abonări -----------------------------------------------------------------

    /** Înregistrează (sau mută pe contul curent) abonarea unui browser. Același endpoint = aceeași abonare. */
    public static function subscribe(Participant $p, string $endpoint, ?string $p256dh = null, ?string $auth = null, ?string $userAgent = null): PushSubscription
    {
        $hash = PushSubscription::hashOf($endpoint);
        $sub = PushSubscription::query()->where('endpoint_hash', $hash)->first() ?? new PushSubscription(['endpoint_hash' => $hash]);
        $sub->fill([
            'participant_id' => $p->id,
            'endpoint' => $endpoint,
            'p256dh' => $p256dh,
            'auth' => $auth,
            'user_agent' => $userAgent !== null ? Str::limit($userAgent, 250, '') : null,
            'last_used_at' => now(),
        ])->save();

        return $sub;
    }

    public static function unsubscribe(string $endpoint, ?Participant $p = null): void
    {
        PushSubscription::query()->where('endpoint_hash', PushSubscription::hashOf($endpoint))
            ->when($p, fn ($q) => $q->where('participant_id', $p->id))->delete();
    }

    public static function hasSubscription(Participant $p): bool
    {
        return PushSubscription::query()->where('participant_id', $p->id)->exists();
    }

    // ---- Trimitere ---------------------------------------------------------------

    /**
     * Creează notificarea și trimite semnalul către toate telefoanele participantului. Întoarce null dacă participantul nu are
     * abonări, dacă push nu e configurat sau dacă aceeași notificare (cheia) a fost deja trimisă.
     */
    public static function notify(Participant $p, string $kind, string $key, string $title, string $body, string $url = '/', bool $urgent = false): ?PushNotification
    {
        if (! self::enabled() || ! self::typeEnabled($kind) || in_array($kind, self::$muted, true)) {
            return null;
        }
        $subs = PushSubscription::query()->where('participant_id', $p->id)->get();
        if ($subs->isEmpty()) {
            return null;
        }

        $key = Str::limit($key, 120, '');
        if (PushNotification::query()->where('participant_id', $p->id)->where('dedupe_key', $key)->exists()) {
            return null;
        }

        try {
            $n = PushNotification::create([
                'participant_id' => $p->id,
                'kind' => $kind,
                'dedupe_key' => $key,
                'title' => Str::limit($title, 120, ''),
                'body' => Str::limit($body, 250, ''),
                'url' => Str::limit($url, 250, ''),
            ]);
        } catch (QueryException $e) {
            return null;   // cheie unică: deja trimisă
        }

        $gateway = app(PushGateway::class);
        foreach ($subs as $sub) {
            $delivery = PushDelivery::create(['push_notification_id' => $n->id, 'push_subscription_id' => $sub->id]);
            $status = $gateway->send($sub, $urgent ? 'high' : 'normal');
            $delivery->update(['sent_at' => now(), 'status' => $status]);
            if ($status === 404 || $status === 410) {
                $sub->delete();   // abonare dispărută (aplicația ștearsă / permisiune retrasă)
            }
        }

        return $n;
    }

    /** Conținutul de livrat unui telefon (endpoint-ul abonării); marchează notificările ca livrate. @return list<array{id:int,title:string,body:string,url:string}> */
    public static function pendingFor(string $endpoint): array
    {
        $sub = PushSubscription::query()->where('endpoint_hash', PushSubscription::hashOf($endpoint))->first();
        if (! $sub) {
            return [];
        }
        $sub->forceFill(['last_used_at' => now()])->save();

        $deliveries = PushDelivery::query()->with('notification')
            ->where('push_subscription_id', $sub->id)->whereNull('delivered_at')
            ->whereHas('notification', fn ($q) => $q->where('created_at', '>=', now()->subDays(self::DELIVERY_DAYS)))
            ->orderBy('id')->limit(5)->get();

        $out = [];
        foreach ($deliveries as $d) {
            $d->update(['delivered_at' => now()]);
            $out[] = ['id' => $d->notification->id, 'title' => $d->notification->title, 'body' => $d->notification->body, 'url' => $d->notification->url];
        }

        return $out;
    }

    /** Participanții cu cel puțin o abonare (ținta „către toți”). */
    public static function subscribedParticipants()
    {
        return Participant::query()->whereIn('id', PushSubscription::query()->select('participant_id'));
    }

    /** Curăță notificările vechi (livrările se șterg în cascadă). */
    public static function prune(): int
    {
        return PushNotification::query()->where('created_at', '<', now()->subDays(60))->delete();
    }
}
