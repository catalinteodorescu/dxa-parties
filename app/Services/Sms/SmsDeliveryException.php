<?php

namespace App\Services\Sms;

use DomainException;

/** DXA: adaugat (runda 81). Furnizorul SMS a refuzat sau nu a răspuns. Mesajul e sigur de arătat utilizatorului; detaliile merg în log (report). */
class SmsDeliveryException extends DomainException
{
    public const MESSAGE = 'Nu am putut trimite SMS-ul acum. Încearcă din nou în câteva minute.';
}
