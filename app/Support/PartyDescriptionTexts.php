<?php

namespace App\Support;

/**
 * DXA: adaugat (runda 29). Textele fixe ale descrierii generate a unei petreceri, în română, engleză și spaniolă:
 * etichetele rândurilor, zilele săptămânii, metodele de plată predefinite și disclaimerul „INFORMAȚII IMPORTANTE”.
 */
class PartyDescriptionTexts
{
    public const LANGS = ['ro', 'en', 'es'];

    public const FLAGS = ['ro' => '🇷🇴', 'en' => '🇬🇧', 'es' => '🇪🇸'];

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
            'es' => [
                'when' => 'Cuándo', 'where' => 'Lugar', 'dress' => 'Código de vestimenta', 'guests' => 'Invitados', 'price' => 'Precios', 'entry' => 'Entrada',
                'free' => 'gratuita', 'pay' => 'Pago', 'contact' => 'Contacto', 'default_party' => 'Fiesta', 'default_type' => 'Entrada', 'offer' => 'oferta',
                'buy_until' => 'compra hasta', 'enter_until' => 'entra hasta', 'currency' => 'RON',
                'days' => ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'],
                'pay_names' => ['cash' => 'Efectivo', 'card' => 'Tarjeta', 'transfer' => 'Transferencia bancaria', 'revolut' => 'Revolut', 'token' => 'Fichas', 'credit' => 'Créditos'],
            ],
        ][$lang];
    }

    public static function disclaimer(string $lang): string
    {
        return [
            'ro' => "INFORMAȚII IMPORTANTE\n"
                ."Organizatorul și locația își rezervă dreptul de a refuza accesul.\n"
                ."Prin achiziționarea unui bilet și participarea la eveniment, îți dai automat acordul de a fi fotografiat și filmat în locație. Organizatorul și locația pot publica aceste fotografii și filmări în scopuri promoționale.\n"
                .'Participi la eveniment pe propriul risc. Organizatorul și locația nu răspund pentru bunurile personale ale participanților.',
            'en' => "IMPORTANT INFORMATION\n"
                ."The organizer and the venue reserve the right to refuse entry.\n"
                ."By purchasing a ticket and attending the event, you automatically consent to being photographed and filmed at the venue. The organizer and the venue may publish these photos and videos for promotional purposes.\n"
                .'You attend the event at your own risk. The organizer and the venue are not responsible for the personal belongings of participants.',
            'es' => "INFORMACIÓN IMPORTANTE\n"
                ."El organizador y el local se reservan el derecho de admisión.\n"
                ."Al comprar una entrada y asistir al evento, consientes automáticamente que te fotografíen y filmen en el local. El organizador y el local podrán publicar estas fotos y vídeos con fines promocionales.\n"
                .'Asistes al evento bajo tu propia responsabilidad. El organizador y el local no se hacen responsables de las pertenencias personales de los participantes.',
        ][$lang];
    }
}
