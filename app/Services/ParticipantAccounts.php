<?php

namespace App\Services;

use App\Contracts\SmsSender;
use App\Models\Participant;
use App\Models\ParticipantVerification;
use App\Support\ParticipantAppSettings;
use App\Support\Phone;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * DXA: adaugat (Aplicația participanților - runda 1). Conturile participanților: înregistrare cu activare prin cod SMS de
 * 6 cifre, login cu telefon + parolă, resetare parolă prin link semnat trimis prin SMS (ca la admin).
 *
 *  - Înregistrarea NU creează participantul: ține o cerere în `participant_verifications` (nume + parola hash-uită + codul
 *    hash-uit). Abia după confirmarea codului se creează participantul sau se leagă de cel existent cu același telefon
 *    (creat la Recepție: își păstrează id-ul, creditele, ștampilele și istoricul; numele lui din Recepție nu se schimbă).
 *  - Codul expiră (10 min), are cel mult 5 încercări greșite, iar retrimiterea are pauză (60 s) și plafon (5 SMS-uri/oră).
 *  - Login: limitat la 5 încercări/minut per telefon + IP; același mesaj pentru orice eșec (nu dezvăluie dacă numărul există).
 *  - Resetare: linkul semnat expiră în 60 min și se stinge după prima folosire (semnătura depinde de parola curentă).
 * Eșecurile sunt DomainException cu mesaj gata de afișat.
 */
class ParticipantAccounts
{
    public const CODE_TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_PAUSE_SECONDS = 60;

    public const MAX_SENDS = 5;

    public const SEND_WINDOW_MINUTES = 60;

    public const RESET_TTL_MINUTES = 60;

    public const MIN_PASSWORD = 8;

    public const LOGIN_MAX_ATTEMPTS = 5;

    public const RESET_MAX_REQUESTS = 3;

    public const GENERIC_LOGIN_ERROR = 'Telefon sau parolă incorectă.';

    /**
     * Pasul 1 al înregistrării: validează, ține cererea și trimite codul prin SMS. Întoarce telefonul normalizat
     * (cheia cererii, pentru pasul de verificare). Reapelată pentru același telefon, înlocuiește cererea și retrimite codul
     * (cu aceleași pauze / plafon ca retrimiterea).
     */
    public static function startRegistration(string $name, string $phone, string $password, SmsSender $sms, bool $acceptTerms = false): string
    {
        if (! ParticipantAppSettings::registrationOpen()) {
            throw new DomainException('Înregistrarea conturilor noi este închisă momentan.');
        }

        // Runda 60: cât timp există Termeni și condiții publicați, contul nou cere acceptarea lor.
        if (Terms::enabled() && ! $acceptTerms) {
            throw new DomainException('Bifează acceptarea Termenilor și condițiilor ca să-ți faci cont.');
        }

        $name = trim($name);
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            throw new DomainException('Scrie numele tău (între 2 și 120 de caractere).');
        }

        $normalized = self::normalizedPhone($phone);
        self::assertPassword($password);

        $existing = Participant::query()->where('phone', $normalized)->first();
        if ($existing && $existing->hasAccount()) {
            throw new DomainException('Acest număr are deja cont. Intră în cont sau resetează parola.');
        }

        $verification = self::freshOrNew($normalized);
        self::assertCanSend($verification);

        $verification->fill([
            'phone' => $normalized,
            'name' => $name,
            'password_hash' => Hash::make($password),
            'terms_version_id' => Terms::current()?->id,
        ]);
        self::issueCode($verification, $sms);

        return $normalized;
    }

    /** Retrimite codul unei cereri în așteptare (cu pauză și plafon). */
    public static function resendCode(string $phone, SmsSender $sms): void
    {
        $verification = ParticipantVerification::query()->where('phone', self::normalizedPhone($phone))->first()
            ?? throw new DomainException('Cererea a expirat. Reia înregistrarea.');

        $verification = self::freshOrNew($verification->phone);
        self::assertCanSend($verification);
        self::issueCode($verification, $sms);
    }

    /** Secundele rămase până se poate retrimite codul (0 = acum). */
    public static function resendWaitSeconds(string $phone): int
    {
        $verification = ParticipantVerification::query()->where('phone', $phone)->first();
        if (! $verification || ! $verification->last_sent_at) {
            return 0;
        }

        return max(0, (int) ceil($verification->last_sent_at->copy()->addSeconds(self::RESEND_PAUSE_SECONDS)->diffInSeconds(now(), false) * -1));
    }

    public static function hasPending(string $phone): bool
    {
        return ParticipantVerification::query()->where('phone', $phone)->exists();
    }

    /** Pasul 2: confirmă codul; creează sau leagă participantul și întoarce contul activ. */
    public static function verifyRegistration(string $phone, string $code): Participant
    {
        $verification = ParticipantVerification::query()->where('phone', self::normalizedPhone($phone))->first()
            ?? throw new DomainException('Cererea a expirat sau nu există. Reia înregistrarea.');

        if ($verification->expires_at->isPast()) {
            throw new DomainException('Codul a expirat. Cere un cod nou.');
        }
        if ($verification->attempts >= ParticipantAppSettings::codeMaxAttempts()) {
            throw new DomainException('Prea multe încercări greșite. Cere un cod nou.');
        }

        $digits = preg_replace('/\D+/', '', $code) ?? '';
        if (! hash_equals($verification->code_hash, self::hashCode($verification->phone, $digits))) {
            $verification->increment('attempts');
            $left = ParticipantAppSettings::codeMaxAttempts() - $verification->attempts;

            throw new DomainException($left > 0
                ? 'Cod greșit. Mai ai '.$left.' '.($left === 1 ? 'încercare' : 'încercări').'.'
                : 'Prea multe încercări greșite. Cere un cod nou.');
        }

        return DB::transaction(function () use ($verification) {
            $participant = Participant::query()->where('phone', $verification->phone)->lockForUpdate()->first();

            if ($participant?->hasAccount()) {
                $verification->delete();

                throw new DomainException('Acest număr are deja cont. Intră în cont sau resetează parola.');
            }

            if ($participant) {
                // Participant creat la Recepție: contul se leagă de el (id, credite, ștampile, istoric rămân).
                // Numele scris de el în aplicație (telefonul e confirmat cu cod) înlocuiește numele pus la Recepție.
                $oldName = $participant->name;
                $participant->forceFill(['name' => $verification->name, 'password' => $verification->password_hash, 'phone_verified_at' => now()] + self::termsFields($verification))->save();
                $how = 'legat de participantul existent din Recepție'.($oldName !== $verification->name ? ', nume schimbat din „'.$oldName.'”' : '');
            } else {
                $participant = Participant::create([
                    'name' => $verification->name,
                    'phone' => $verification->phone,
                    'password' => $verification->password_hash,
                    'phone_verified_at' => now(),
                    'source' => 'app',
                ] + self::termsFields($verification));
                $how = 'nou';
            }

            $verification->delete();

            ActivityLogger::log('participants.account_created', 'Participantul „'.$participant->name.'” ('.$participant->phone.') și-a activat contul în aplicație ('.$how.').', actor: null);

            return $participant;
        });
    }

    /** Runda 60: versiunea de Termeni bifată la înregistrare (dacă a fost). @return array<string, mixed> */
    private static function termsFields(ParticipantVerification $verification): array
    {
        return $verification->terms_version_id
            ? ['terms_version_id' => $verification->terms_version_id, 'terms_accepted_at' => now()]
            : [];
    }

    /** Autentifică pe guard-ul `participant`. Aceeași eroare pentru orice eșec; limitat per telefon + IP. */
    public static function attempt(string $phone, string $password, bool $remember, ?string $ip = null): Participant
    {
        $normalized = Phone::normalize($phone) ?? throw new DomainException(self::GENERIC_LOGIN_ERROR);
        $key = 'participant-login:'.$normalized.'|'.($ip ?? '-');

        if (RateLimiter::tooManyAttempts($key, self::LOGIN_MAX_ATTEMPTS)) {
            $sec = RateLimiter::availableIn($key);

            throw new DomainException('Prea multe încercări. Încearcă din nou peste '.$sec.' '.($sec === 1 ? 'secundă' : 'secunde').'.');
        }

        $participant = Participant::query()->where('phone', $normalized)->first();
        if (! $participant || ! $participant->hasAccount() || ! Hash::check($password, (string) $participant->password)) {
            RateLimiter::hit($key, 60);
            ActivityLogger::log('participants.login_failed', 'Autentificare eșuată în aplicația participanților pentru '.$normalized.'.', actor: null);

            throw new DomainException(self::GENERIC_LOGIN_ERROR);
        }

        RateLimiter::clear($key);
        Auth::guard('participant')->login($participant, $remember);

        return $participant;
    }

    /**
     * Trimite prin SMS linkul de resetare (doar pentru conturi active). Nu întoarce nimic și nu dezvăluie dacă numărul
     * există; cel mult 3 cereri pe oră per telefon.
     */
    public static function sendResetLink(string $phone, SmsSender $sms): void
    {
        $normalized = Phone::normalize($phone);
        if ($normalized === null) {
            return;
        }

        $key = 'participant-reset:'.$normalized;
        if (RateLimiter::tooManyAttempts($key, self::RESET_MAX_REQUESTS)) {
            return;
        }
        RateLimiter::hit($key, 3600);

        $participant = Participant::query()->where('phone', $normalized)->first();
        if (! $participant || ! $participant->hasAccount()) {
            ActivityLogger::log('participants.password_reset_unknown', 'S-a cerut resetarea parolei în aplicație pentru un număr fără cont ('.$normalized.').', actor: null);

            return;
        }

        $url = URL::temporarySignedRoute('app.reset', now()->addMinutes(self::RESET_TTL_MINUTES), [
            'participant' => $participant->id,
            'v' => self::resetStamp($participant),
        ]);

        $sms->send($participant->phone, ParticipantAppSettings::smsReset($url));

        ActivityLogger::log('participants.password_reset_requested', 'Participantul „'.$participant->name.'” a cerut resetarea parolei prin SMS.', actor: null);
    }

    /** Amprenta parolei curente din link: se schimbă odată cu parola, deci linkul se stinge după folosire. */
    public static function resetStamp(Participant $participant): string
    {
        return substr(hash_hmac('sha256', (string) $participant->password, (string) config('app.key')), 0, 12);
    }

    public static function resetLinkValid(Participant $participant, string $stamp): bool
    {
        return $participant->hasAccount() && hash_equals(self::resetStamp($participant), $stamp);
    }

    public static function resetPassword(Participant $participant, string $stamp, string $password): void
    {
        if (! self::resetLinkValid($participant, $stamp)) {
            throw new DomainException('Linkul de resetare nu mai este valabil. Cere unul nou.');
        }
        self::assertPassword($password);

        $participant->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();

        ActivityLogger::log('participants.password_reset_completed', 'Participantul „'.$participant->name.'” și-a resetat parola prin link SMS.', actor: null);
    }

    /** Schimbă numele afișat (2–120 caractere). Telefonul nu se schimbă aici: ar cere o nouă confirmare prin SMS. */
    public static function updateName(Participant $participant, string $name): void
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            throw new DomainException('Scrie numele tău (între 2 și 120 de caractere).');
        }
        if ($name === $participant->name) {
            return;
        }

        $old = $participant->name;
        $participant->forceFill(['name' => $name])->save();

        ActivityLogger::log('participants.name_changed', 'Participantul „'.$old.'” și-a schimbat numele în „'.$name.'” din aplicație.', actor: null);
    }

    /** Schimbă parola din cont: cere parola curentă (limitat la 5 încercări/minut) și rotește remember_token (ieșire de pe alte dispozitive). */
    public static function changePassword(Participant $participant, string $current, string $new): void
    {
        $key = 'participant-password:'.$participant->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $sec = RateLimiter::availableIn($key);

            throw new DomainException('Prea multe încercări. Încearcă din nou peste '.$sec.' '.($sec === 1 ? 'secundă' : 'secunde').'.');
        }
        if (! Hash::check($current, (string) $participant->password)) {
            RateLimiter::hit($key, 60);

            throw new DomainException('Parola curentă nu e corectă.');
        }
        self::assertPassword($new);

        RateLimiter::clear($key);
        $participant->forceFill(['password' => $new, 'remember_token' => Str::random(60)])->save();

        ActivityLogger::log('participants.password_changed', 'Participantul „'.$participant->name.'” și-a schimbat parola din aplicație.', actor: null);
    }

    public static function assertPassword(string $password): void
    {
        if (mb_strlen($password) < self::MIN_PASSWORD) {
            throw new DomainException('Parola trebuie să aibă cel puțin '.self::MIN_PASSWORD.' caractere.');
        }
        if (mb_strlen($password) > 200) {
            throw new DomainException('Parola e prea lungă.');
        }
    }

    private static function normalizedPhone(string $phone): string
    {
        return Phone::normalize($phone) ?? throw new DomainException('Telefonul nu pare valid. Scrie-l ca 07XXXXXXXX.');
    }

    /** Cererea existentă a telefonului (cu fereastra de trimiteri reîmprospătată dacă a trecut) sau una nouă, nesalvată. */
    private static function freshOrNew(string $phone): ParticipantVerification
    {
        $verification = ParticipantVerification::query()->where('phone', $phone)->first();

        if (! $verification) {
            return new ParticipantVerification(['phone' => $phone, 'send_count' => 0]);
        }

        // Fereastra de plafonare a trimiterilor a trecut: numărătoarea o ia de la capăt.
        if ($verification->created_at->lt(now()->subMinutes(self::SEND_WINDOW_MINUTES))) {
            $verification->send_count = 0;
            $verification->created_at = now();
        }

        return $verification;
    }

    private static function assertCanSend(ParticipantVerification $verification): void
    {
        if ($verification->last_sent_at) {
            $wait = (int) ceil($verification->last_sent_at->copy()->addSeconds(self::RESEND_PAUSE_SECONDS)->diffInSeconds(now(), false) * -1);
            if ($wait > 0) {
                throw new DomainException('Așteaptă '.$wait.' '.($wait === 1 ? 'secundă' : 'secunde').' înainte să ceri alt cod.');
            }
        }

        if ($verification->send_count >= self::MAX_SENDS) {
            throw new DomainException('Ai cerut prea multe coduri. Încearcă din nou peste o oră.');
        }
    }

    private static function issueCode(ParticipantVerification $verification, SmsSender $sms): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $verification->fill([
            'code_hash' => self::hashCode($verification->phone, $code),
            'expires_at' => now()->addMinutes(ParticipantAppSettings::codeTtlMinutes()),
            'attempts' => 0,
            'last_sent_at' => now(),
            'send_count' => $verification->send_count + 1,
        ])->save();

        $sms->send($verification->phone, ParticipantAppSettings::smsActivation($code));
    }

    private static function hashCode(string $phone, string $code): string
    {
        return hash_hmac('sha256', $phone.'|'.$code, (string) config('app.key'));
    }
}
