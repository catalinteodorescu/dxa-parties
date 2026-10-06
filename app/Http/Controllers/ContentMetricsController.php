<?php

namespace App\Http\Controllers;

use App\Services\ContentStats;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * DXA: adaugat (runda 40). Primește în grup evenimentele din aplicație (afișări, click-uri): {"e": [["p", 12, "i"], ["a", 5, "a"], ...]}
 * (p = petrecere, a = anunț; i = afișare, o = deschidere, a = click pe acțiune). Răspunde mereu 204, fără detalii.
 */
class ContentMetricsController extends Controller
{
    private const TYPES = ['p' => 'party', 'a' => 'announcement'];

    private const EVENTS = ['i' => 'impression', 'o' => 'open', 'a' => 'action'];

    public function store(Request $request): Response
    {
        $items = [];
        foreach ((array) $request->json('e', []) as $row) {
            if (is_array($row) && isset($row[0], $row[1], $row[2], self::TYPES[$row[0]], self::EVENTS[$row[2]])) {
                $items[] = [self::TYPES[$row[0]], (int) $row[1], self::EVENTS[$row[2]]];
            }
        }

        ContentStats::recordMany($items, $request);

        return response()->noContent();
    }
}
