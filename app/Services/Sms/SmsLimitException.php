<?php

namespace App\Services\Sms;

use DomainException;

/** DXA: adaugat (runda 81). Limita anti-abuz a trimiterii de SMS-uri a fost atinsă; mesajul se poate arăta utilizatorului. */
class SmsLimitException extends DomainException {}
