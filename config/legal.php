<?php

// DXA: adaugat (runda 83). Datele furnizorului tehnic care apar în textele legale (Termeni și Politica de confidențialitate).
// Datele organizatorului (clientul) se completează în aplicație, la Legal › Date organizator, sau cu `php artisan app:install-legal`.
return [
    'provider' => [
        'name' => 'SUPPINNO SRL',
        'address' => 'Str. Ana Aslan nr. 4, et. 2, ap. 12, sat Selimbar, cod poștal 557260, județul Sibiu',
        'phone' => env('LEGAL_PROVIDER_PHONE', '0748960817'),
        'email' => env('LEGAL_PROVIDER_EMAIL'),   // opțional: dacă lipsește, în text apare doar telefonul
    ],

    // Furnizorul de SMS-uri, așa cum apare în Politica de confidențialitate.
    'sms_provider' => env('LEGAL_SMS_PROVIDER', 'SMSO'),
];
