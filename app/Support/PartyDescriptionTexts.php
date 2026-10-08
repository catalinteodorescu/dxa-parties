<?php

namespace App\Support;

/**
 * DXA: adaugat (runda 29). Textele fixe ale descrierii generate a unei petreceri, în română și engleză:
 * etichetele rândurilor, zilele săptămânii, metodele de plată predefinite și disclaimerul „INFORMAȚII IMPORTANTE”.
 */
class PartyDescriptionTexts
{
    public const LANGS = ['ro', 'en'];

    public const FLAGS = ['ro' => '🇷🇴', 'en' => '🇬🇧'];

    /** @return array<string, string|array<int|string, string>> */
    public static function labels(string $lang): array
    {
        return [
            'ro' => [
                'when' => 'Când', 'where' => 'Locație', 'dress' => 'Dresscode', 'guests' => 'Invitați', 'price' => 'Prețuri', 'entry' => 'Intrare',
                'free' => 'gratuită', 'pay' => 'Plată', 'contact' => 'Contact', 'default_party' => 'Petrecere', 'default_type' => 'Intrare', 'offer' => 'ofertă',
                'buy_until' => 'cumperi până la', 'enter_until' => 'intri până la', 'currency' => 'lei',
                'days' => ['duminică', 'luni', 'marți', 'miercuri', 'joi', 'vineri', 'sâmbătă'],
                'pay_names' => [],
            ],
            'en' => [
                'when' => 'When', 'where' => 'Location', 'dress' => 'Dress code', 'guests' => 'Guests', 'price' => 'Prices', 'entry' => 'Entry',
                'free' => 'free', 'pay' => 'Payment', 'contact' => 'Contact', 'default_party' => 'Party', 'default_type' => 'Entry', 'offer' => 'offer',
                'buy_until' => 'buy until', 'enter_until' => 'enter until', 'currency' => 'RON',
                'days' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
                'pay_names' => ['cash' => 'Cash', 'card' => 'Card', 'transfer' => 'Bank transfer', 'revolut' => 'Revolut', 'token' => 'Tokens', 'credit' => 'Credits'],
            ],
        ][$lang];
    }

    public static function disclaimer(string $lang): string
    {
        return [
            'ro' => "INFORMAȚII IMPORTANTE\n\n"
                ."* Organizatorul și locația își rezervă dreptul de a refuza accesul sau de a solicita părăsirea locației.\n"
                ."* Prin participarea la eveniment, îți dai acordul ca în timpul evenimentului să poți fi fotografiat(ă) și/sau filmat(ă), iar fotografiile și filmările să fie utilizate de către organizator și locație în scopuri promoționale.\n"
                .'* Participarea la eveniment se face pe propria răspundere. Organizatorul și locația nu își asumă răspunderea pentru pierderea, dispariția sau deteriorarea bunurilor personale.',
            'en' => "IMPORTANT INFORMATION\n\n"
                ."* The organizer and the venue reserve the right to refuse entry or ask guests to leave the venue.\n"
                ."* By attending the event, you agree that you may be photographed and/or filmed during the event, and that such photos and footage may be used by the organizer and the venue for promotional purposes.\n"
                .'* Attendance is at your own risk. The organizer and the venue are not responsible for any loss, disappearance, or damage to personal belongings.',
        ][$lang];
    }
}
