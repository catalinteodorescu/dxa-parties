<?php

// DXA: adaugat (runda 81). Trimiterea de SMS-uri. `driver`: `log` (scrie mesajul în log, pentru dezvoltare locală) sau `smso`.
return [
    'driver' => env('SMS_DRIVER', 'log'),

    'smso' => [
        'key' => env('SMSO_API_KEY'),
        'sender' => env('SMSO_SENDER'),   // id-ul expeditorului din contul SMSO (numărul scurt comun sau eticheta proprie)
        'timeout' => (int) env('SMSO_TIMEOUT', 10),
        'type' => env('SMSO_TYPE', 'transactional'),   // transactional | otp | marketing (aplicația trimite doar mesaje despre cont și bilet)
        'low_credit' => (float) env('SMSO_LOW_CREDIT', 10),   // sub această valoare, pagina Proprietar avertizează
    ],

    // Limite anti-abuz (vezi GuardedSmsSender). 0 = fără limită pe acel nivel.
    'limits' => [
        'per_phone' => (int) env('SMS_LIMIT_PER_PHONE', 6),    // pe destinatar, pe oră
        'per_ip' => (int) env('SMS_LIMIT_PER_IP', 60),         // pe adresa IP, pe oră (wifi-ul unei petreceri)
        'daily' => (int) env('SMS_LIMIT_DAILY', 1000),         // pe instalare, pe zi
    ],
];
