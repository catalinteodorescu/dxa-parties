<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use App\Models\CreditTransaction;
use App\Models\Order;
use App\Models\Participant;
use App\Models\Party;
use App\Models\PartyInterest;
use App\Models\PushEvent;
use App\Models\PushNotification;
use App\Models\Sale;
use App\Models\Ticket;
use App\Services\AdminTickets;
use App\Services\ParticipantRegistry;
use App\Services\Push\Events;
use App\Services\Push\Notifier;
use App\Services\TicketOrders;
use App\Support\Phone;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * DXA: adaugat (runda 70b). Date de test pentru fiecare tip de notificare push, doar pentru un cont de test (telefonul dat), în mediul local.
 * Creează petreceri/anunțuri „[TEST PUSH] …”, simulează ora potrivită fiecărui tip și trimite NUMAI către contul dat. `--clean` șterge tot ce a creat.
 * Refuză să ruleze în producție.
 */
class PushDemo extends Command
{
    protected $signature = 'dxa:push-demo {phone : telefonul contului de test (cu abonare push activă)} {--clean : șterge datele de test create de această comandă} {--pause=1500 : pauza (ms) între scenarii, ca dispozitivul să-și termine cererile către server}';

    protected $description = 'Creează date de test și trimite câte o notificare de fiecare tip către un cont de test (doar local)';

    private const TAG = '[TEST PUSH]';

    private const DUMMY_PREFIX = '+40799000';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Comanda refuză să ruleze în producție.');

            return self::FAILURE;
        }

        if ($this->option('clean')) {
            return $this->clean();
        }

        if (! Notifier::enabled()) {
            $this->error('Push nu e configurat (lipsesc cheile VAPID din .env).');

            return self::FAILURE;
        }

        $phone = Phone::normalize((string) $this->argument('phone'));
        $me = $phone ? Participant::query()->where('phone', $phone)->first() : null;
        if (! $me) {
            $this->error('Nu există un participant cu acest telefon.');

            return self::FAILURE;
        }
        if (! Notifier::hasSubscription($me)) {
            $this->error('Contul nu are nicio abonare push. Intră în aplicație, la Cont, și apasă „Activează notificările”.');

            return self::FAILURE;
        }

        $today = Carbon::today();
        $sent = [];

        try {
            // 1. Petrecere nouă
            Carbon::setTestNow($today->copy()->setTime(12, 0));
            $a = $this->party('Petrecere nouă', $today->copy()->addDays(14)->setTime(21, 0));
            $sent['party_new'] = $this->retry(fn () => Events::partyNew(now(), $a, $me), fn () => PushEvent::query()->where('key', 'party_new:'.$a->id)->delete());
            $this->pause();

            // 2. Prețul crește: două creșteri (azi se termină 50 lei, mâine la 09:00 se termină 60 lei) = două notificări
            $b = $this->party('Preț crește', $today->copy()->addDays(14)->setTime(21, 0), [[
                'name' => 'Bilet', 'price' => 80, 'qty_tiers' => [],
                'discounts' => [
                    ['price' => 50, 'until' => $today->toDateString()],
                    ['price' => 60, 'until' => $today->copy()->addDay()->setTime(9, 0)->format('Y-m-d\TH:i')],
                ],
            ]]);
            $this->retry(fn () => PartyInterest::firstOrCreate(['participant_id' => $me->id, 'party_id' => $b->id]));
            $sent['price'] = $this->retry(fn () => Events::priceRises(now(), $b));
            $this->pause();

            // 3. Puține locuri: 10 la vânzare, 9 cumpărate de conturi de test -> rămâne 1
            $c = $this->party('Puține locuri', $today->copy()->addDays(14)->setTime(21, 0), null, ['tickets_for_sale' => 10]);
            $this->retry(fn () => PartyInterest::firstOrCreate(['participant_id' => $me->id, 'party_id' => $c->id]));
            foreach ([4, 4, 1] as $i => $qty) {
                $buyer = $this->retry(fn () => $this->dummy($i + 1));
                $this->retry(fn () => Notifier::muting(['ticket_new'], fn () => TicketOrders::place($buyer, $c, 'Bilet', $qty)));
            }
            $sent['seats'] = PushNotification::query()->where('participant_id', $me->id)->where('dedupe_key', 'seats:'.$c->id)->count();
            $this->pause();

            // 3b. Bilete luate de alții: cele 3 conturi de test au cumpărat; pragul de test e 3 (nu atinge setarea reală)
            $sent['buyers'] = $this->retry(fn () => Events::buyers(now(), $c, $me, 3));
            $this->pause();

            // 4. Mâine: petrecerea e mâine, tu ai bilet; ora simulată 18:05
            $d = $this->party('Mâine', $today->copy()->addDay()->setTime(21, 0), null, ['location_name' => 'Sala de test']);
            $this->retry(fn () => Notifier::muting(['ticket_new'], fn () => TicketOrders::place($me, $d, 'Bilet', 1)));
            Carbon::setTestNow($today->copy()->setTime(18, 5));
            $sent['tomorrow'] = $this->retry(fn () => Events::partyTomorrow(now(), $d));
            $this->pause();

            // 5. Cu 2 ore înainte: ora simulată = începutul - 1h45, cu dresscode
            $e = $this->party('Cu 2 ore înainte', $today->copy()->addDays(2)->setTime(21, 0), null, ['dresscode' => 'Alb']);
            $this->retry(fn () => Notifier::muting(['ticket_new'], fn () => TicketOrders::place($me, $e, 'Bilet', 1)));
            Carbon::setTestNow($e->starts_at->copy()->subMinutes(105));
            $sent['ready'] = $this->retry(fn () => Events::partyReady(now(), $e));
            $this->pause();

            // 6. Bilet anulat
            Carbon::setTestNow($today->copy()->setTime(12, 0));
            $f = $this->party('Bilet anulat', $today->copy()->addDays(14)->setTime(21, 0));
            $ticket = $this->retry(fn () => Notifier::muting(['ticket_new'], fn () => TicketOrders::place($me, $f, 'Bilet', 1)))->tickets->first();
            $this->retry(fn () => AdminTickets::cancel($ticket, 'test notificare', false));
            $sent['ticket_cancelled'] = PushNotification::query()->where('participant_id', $me->id)->where('dedupe_key', 'ticket_cancelled:'.$ticket->id)->count();
            $this->pause();

            // 6b. Bilet cumpărat: o comandă reală pe contul de test (notificarea pleacă din vânzare)
            $g = $this->party('Bilet cumpărat', $today->copy()->addDays(14)->setTime(21, 0));
            $order = $this->retry(fn () => TicketOrders::place($me, $g, 'Bilet', 2));
            $sent['ticket_new'] = PushNotification::query()->where('participant_id', $me->id)->where('dedupe_key', 'ticket_new:order:'.$order->id)->count();
            $this->pause();

            // 6c. Plată cu credite și card complet: trimise direct (nu scriu nimic în portofel sau pe card; soldul din mesaj e cel real)
            $stamp = 'demo-'.now()->timestamp;
            $this->retry(fn () => Events::creditPaid($me, 35.0, Sale::class, $stamp));
            $sent['credit_payment'] = PushNotification::query()->where('participant_id', $me->id)->where('dedupe_key', 'credit_payment:'.$stamp)->count();
            $this->pause();
            $this->retry(fn () => Events::creditLoaded($me, 110.0, 10.0, CreditTransaction::SOURCE_RECEPTION, $stamp));
            $sent['credit_load'] = PushNotification::query()->where('participant_id', $me->id)->where('dedupe_key', 'credit_load:'.$stamp)->count();
            $this->pause();
            $this->retry(fn () => Events::cardReady($me, $stamp));
            $sent['card_ready'] = PushNotification::query()->where('participant_id', $me->id)->where('dedupe_key', 'card_ready:'.$stamp)->count();
            $this->pause();

            // 7. Anunț nou
            $ann = $this->retry(fn () => Announcement::create([
                'title' => self::TAG.' Anunț nou', 'body' => 'Așa arată un anunț publicat în aplicație.', 'audience' => 'all',
                'in_carousel' => false, 'in_list' => true, 'is_active' => true, 'status' => 'published',
            ]));
            $sent['announcement'] = $this->retry(fn () => Events::announcements(now(), $ann, $me), fn () => Announcement::query()->whereKey($ann->id)->update(['push_sent_at' => null]));
        } catch (Throwable $e) {
            Carbon::setTestNow();
            $this->error('Eroare: '.$e->getMessage());
            $this->line('Dacă e „database is locked”: închide aplicațiile care țin deschis fișierul bazei (TablePlus etc.), rulează `--clean` și încearcă din nou.');

            return self::FAILURE;
        } finally {
            Carbon::setTestNow();
        }

        foreach ($sent as $kind => $n) {
            $this->line(sprintf('%-18s %s', Notifier::TYPES[$kind][0], $n > 0 ? 'trimis ('.$n.')' : 'NEtrimis (tip oprit în Setări › Aplicație participanți, sau nu s-a îndeplinit condiția)'));
        }
        $this->newLine();
        $this->line('Notificările ajung pe dispozitivele contului în câteva secunde. Ștergi datele de test cu:  php artisan dxa:push-demo '.$this->argument('phone').' --clean');

        return self::SUCCESS;
    }

    /**
     * SQLite local: telefonul răspunde la fiecare notificare cu o cerere către server (scrie în bază), iar comanda scrie în același timp;
     * o scriere poate pica cu „database is locked”. O reluăm de câteva ori (cu $reset între încercări, ca să nu rămână evenimentul „luat”).
     *
     * @template T
     *
     * @param  callable(): T  $fn
     * @return T
     */
    private function retry(callable $fn, ?callable $reset = null, int $attempts = 6): mixed
    {
        for ($i = 1; ; $i++) {
            try {
                return $fn();
            } catch (QueryException $e) {
                if ($i >= $attempts || ! str_contains($e->getMessage(), 'database is locked')) {
                    throw $e;
                }
                $reset?->__invoke();
                $this->pause($i * 1000);
            }
        }
    }

    /** Pauză între scenarii (milisecunde), ca telefonul să-și termine cererile către server înainte de următoarea scriere. */
    private function pause(?int $ms = null): void
    {
        $ms ??= max(0, (int) $this->option('pause'));
        if ($ms > 0 && (int) $this->option('pause') > 0) {
            usleep($ms * 1000);
        }
    }

    /** @param  array<int, array<string, mixed>>|null  $types */
    private function party(string $name, Carbon $starts, ?array $types = null, array $extra = []): Party
    {
        return $this->retry(fn () => Party::create($extra + [
            'name' => self::TAG.' '.$name, 'kind' => 'basic', 'start_date' => $starts->toDateString(), 'start_time' => $starts->format('H:i'), 'end_time' => '03:00',
            'is_free' => false, 'audience' => 'all', 'in_carousel' => false, 'is_active' => true, 'status' => 'published',
            'payment_methods' => ['cash'], 'online_sales' => true,
            'ticket_types' => $types ?? [['name' => 'Bilet', 'price' => 50, 'discounts' => [], 'qty_tiers' => []]],
        ]));
    }

    /** Cont de test (fără abonare push) care cumpără bilete; se refolosește între rulări. */
    private function dummy(int $n): Participant
    {
        $phone = self::DUMMY_PREFIX.sprintf('%03d', $n);
        $p = Participant::query()->where('phone', $phone)->first() ?? ParticipantRegistry::create('Test Push '.$n, $phone);
        $p->forceFill(['password' => 'parola-sigura', 'phone_verified_at' => now()])->save();

        return $p;
    }

    private function clean(): int
    {
        $parties = Party::query()->where('name', 'like', self::TAG.'%')->pluck('id');
        $orders = Order::query()->whereIn('party_id', $parties)->pluck('id');
        Ticket::query()->whereIn('party_id', $parties)->delete();
        Order::query()->whereIn('id', $orders)->delete();
        PartyInterest::query()->whereIn('party_id', $parties)->delete();
        PushEvent::query()->whereIn('key', $parties->map(fn ($id) => 'party_new:'.$id)->all())->delete();
        Party::query()->whereIn('id', $parties)->delete();
        $announcements = Announcement::query()->where('title', 'like', self::TAG.'%')->delete();
        $notifications = PushNotification::query()->where(fn ($q) => $q->where('title', 'like', '%'.self::TAG.'%')->orWhere('body', 'like', '%'.self::TAG.'%')->orWhere('dedupe_key', 'like', '%:demo-%'))->delete();
        $dummies = Participant::query()->where('phone', 'like', self::DUMMY_PREFIX.'%')->delete();

        $this->line(sprintf('Șterse: %d petreceri, %d anunțuri, %d notificări, %d conturi de test.', $parties->count(), $announcements, $notifications, $dummies));

        return self::SUCCESS;
    }
}
