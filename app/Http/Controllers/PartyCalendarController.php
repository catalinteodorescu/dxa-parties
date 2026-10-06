<?php

namespace App\Http\Controllers;

use App\Models\Party;
use App\Support\PartyCalendar;
use Illuminate\Http\Response;

/**
 * DXA: adaugat (runda 41). Fișierul .ics al unei petreceri (butonul „Adaugă în calendar” din pagina petrecerii). Aceleași reguli de vizibilitate ca pagina:
 * ciornele și petrecerile dezactivate nu există (404), iar cele „doar logați” cer cont.
 */
class PartyCalendarController extends Controller
{
    public function show(Party $party): Response
    {
        abort_if($party->isDraft() || ! $party->is_active, 404);
        abort_if($party->audience === 'auth' && ! auth('participant')->check(), 404);

        return response(PartyCalendar::ics($party, route('app.party', $party)), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="'.PartyCalendar::filename($party).'"',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
