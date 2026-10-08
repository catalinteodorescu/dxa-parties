<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Participant;
use App\Models\TermsVersion;
use DomainException;
use Illuminate\Support\HtmlString;

/**
 * DXA: adaugat (runda 60). Termeni și condiții (un singur text, în română).
 *
 *  - Cât timp nu e publicată nicio versiune, nu se cere și nu se arată nimic (bifa din înregistrare, banner-ul, linkurile).
 *  - Publicarea creează o versiune nouă. Prima versiune cere mereu acceptarea; la următoarele decide bifa „cere acceptare de la toți”.
 *  - Un cont trebuie să fi acceptat măcar ultima versiune care cere acceptare (`requiredId`). Banner-ul din aplicație e ne-blocant.
 */
class Terms
{
    public const MAX_LENGTH = 60000;

    public static function current(): ?TermsVersion
    {
        return TermsVersion::query()->orderByDesc('id')->first();
    }

    public static function enabled(): bool
    {
        return TermsVersion::query()->exists();
    }

    /** Ultima versiune care cere acceptare (id) sau null dacă nu există niciuna. */
    public static function requiredId(): ?int
    {
        $id = TermsVersion::query()->where('requires_reaccept', true)->max('id');

        return $id === null ? null : (int) $id;
    }

    public static function needsAcceptance(Participant $participant): bool
    {
        $required = self::requiredId();

        return $required !== null && (int) $participant->terms_version_id < $required;
    }

    public static function accept(Participant $participant): void
    {
        $current = self::current();
        if (! $current || ! self::needsAcceptance($participant)) {
            return;
        }

        $participant->forceFill(['terms_version_id' => $current->id, 'terms_accepted_at' => now()])->save();
    }

    public static function publish(string $body, bool $requireAll, ?Admin $by = null): TermsVersion
    {
        $body = trim(str_replace("\r\n", "\n", $body));
        if ($body === '') {
            throw new DomainException('Scrie textul Termenilor și condițiilor.');
        }
        if (mb_strlen($body) > self::MAX_LENGTH) {
            throw new DomainException('Textul e prea lung (maximum '.self::MAX_LENGTH.' de caractere).');
        }
        if (self::current()?->body === $body) {
            throw new DomainException('Textul nu s-a schimbat față de versiunea publicată.');
        }

        $first = ! self::enabled();
        $version = TermsVersion::create([
            'body' => $body,
            'requires_reaccept' => $first || $requireAll,
            'published_by' => $by?->id,
        ]);

        ActivityLogger::log('terms.published', 'A publicat versiunea '.$version->id.' a Termenilor și condițiilor'.($version->requires_reaccept ? ' (cere acceptarea participanților)' : ' (modificare minoră, fără re-acceptare)').'.');

        return $version;
    }

    /** Câte conturi (cu parolă) au acceptat versiunea cerută, din câte sunt în total. @return array{accepted: int, total: int} */
    public static function acceptance(): array
    {
        $accounts = Participant::query()->whereNotNull('password')->whereNull('anonymized_at');
        $total = (clone $accounts)->count();
        $required = self::requiredId();

        return [
            'accepted' => $required === null ? $total : (clone $accounts)->where('terms_version_id', '>=', $required)->count(),
            'total' => $total,
        ];
    }

    /** Textul simplu → HTML sigur: paragrafe separate prin rând gol, liniile care încep cu „# ” sunt titluri. */
    public static function render(string $body): HtmlString
    {
        $html = '';
        foreach (preg_split('/\n{2,}/', trim(str_replace("\r\n", "\n", $body))) ?: [] as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }
            $html .= str_starts_with($block, '# ')
                ? '<h2 class="pa-h2" style="margin: 1.25rem 0 .4rem">'.e(trim(substr($block, 2))).'</h2>'
                : '<p style="margin: .4rem 0; line-height: 1.55">'.nl2br(e($block)).'</p>';
        }

        return new HtmlString($html);
    }

    /** Textul de probă din câmpul gol (se înlocuiește cu cel final). */
    public static function sample(): string
    {
        return "# Termeni și condiții (text de probă)\n\n"
            ."Acesta este un text de probă. Înlocuiește-l cu varianta finală înainte de lansare.\n\n"
            ."# Bilete și credite\n\n"
            ."Biletele și creditele cumpărate în aplicație nu se rambursează.\n\n"
            ."# Acces și răspundere\n\n"
            ."Organizatorul își rezervă dreptul de a refuza accesul. Participi la eveniment pe propriul risc.\n\n"
            .'Pe parcursul evenimentelor se pot face fotografii și filmări în scop promoțional.';
    }
}
