<?php

use App\Models\Party;

/** Bilet de 30 lei, cu intrare gratuita pana la 22:30 (24.09.2026). */
function freeUntilTicket(?string $until): array
{
    return [
        'name' => 'Intrare',
        'price' => 30,
        'discounts' => [['label' => 'Gratuit până la 22:30', 'price' => 0, 'until' => $until]],
    ];
}

it('treats "until 22:30" as exclusive: free through 22:29:59, base price from 22:30:00', function () {
    $party = new Party;
    $ticket = freeUntilTicket('2026-09-24T22:30');

    $this->travelTo(now()->setDateTime(2026, 9, 24, 22, 10, 0));
    expect($party->currentPriceForType($ticket))->toBe(0.0);

    $this->travelTo(now()->setDateTime(2026, 9, 24, 22, 29, 59));
    expect($party->currentPriceForType($ticket))->toBe(0.0);

    $this->travelTo(now()->setDateTime(2026, 9, 24, 22, 30, 0));
    expect($party->currentPriceForType($ticket))->toBe(30.0);

    $this->travelTo(now()->setDateTime(2026, 9, 24, 22, 45, 0));
    expect($party->currentPriceForType($ticket))->toBe(30.0);
});

it('keeps date-only discounts valid through the end of that day', function () {
    $party = new Party;
    $ticket = freeUntilTicket('2026-09-24');

    $this->travelTo(now()->setDateTime(2026, 9, 24, 23, 50, 0));
    expect($party->currentPriceForType($ticket))->toBe(0.0);

    $this->travelTo(now()->setDateTime(2026, 9, 25, 0, 5, 0));
    expect($party->currentPriceForType($ticket))->toBe(30.0);
});

it('formats the limit with or without the time', function () {
    expect(Party::formatUntil('2026-09-24'))->toBe('24.09.2026')
        ->and(Party::formatUntil('2026-09-24T22:30'))->toBe('24.09.2026 22:30')
        ->and(Party::formatUntil(null))->toBe('');
});
