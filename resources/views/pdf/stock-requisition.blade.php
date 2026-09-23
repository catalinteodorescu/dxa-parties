@php
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, ',', '.'), '0'), ',');
@endphp
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <title>Necesar {{ $requisition->label }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #241A16; margin: 0; }
        h1 { font-size: 17px; margin: 0 0 2px; }
        .muted { color: #6B5D57; }
        .header { margin-bottom: 16px; }
        .badge { display: inline-block; font-size: 9px; font-weight: bold; color: #1D6FB8; background: #E3EFF7; padding: 2px 7px; border-radius: 8px; }
        .badge-closed { color: #6B5D57; background: #F2EEEC; }

        table.lines { width: 100%; border-collapse: collapse; }
        table.lines th { text-align: left; font-size: 9px; text-transform: uppercase; color: #6B5D57; border-bottom: 1px solid #EAE4E1; padding: 5px 6px; }
        table.lines td { border-bottom: 1px solid #F2EEEC; padding: 7px 6px; vertical-align: top; }
        table.lines .num { text-align: right; white-space: nowrap; }
        .sub { display: block; font-size: 9px; color: #6B5D57; margin-top: 1px; }
        .check { width: 22px; }
        .checkbox { display: inline-block; width: 11px; height: 11px; border: 1px solid #6B5D57; border-radius: 2px; }
        .empty { font-style: italic; color: #6B5D57; padding: 6px; }

        .footer { margin-top: 20px; font-size: 9px; color: #6B5D57; }
    </style>
</head>
<body>

    <div class="header">
        <h1>
            Necesar — {{ $requisition->label }}
            @if ($requisition->status === 'open')
                <span class="badge">Deschis</span>
            @elseif ($requisition->status === 'fulfilled')
                <span class="badge">Rezolvat</span>
            @else
                <span class="badge badge-closed">Închis</span>
            @endif
        </h1>
        <span class="muted">
            Creat {{ $requisition->created_at->format('d.m.Y H:i') }}
            @if ($requisition->creator) de {{ $requisition->creator->name }} @endif
            @if ($requisition->party) &middot; {{ $requisition->party->name }} @endif
        </span>
    </div>

    @if ($items->isEmpty())
        <p class="empty">Necesarul nu are produse.</p>
    @else
        <table class="lines">
            <tr>
                <th class="check"></th>
                <th>Produs</th>
                <th class="num">Cantitate</th>
            </tr>
            @foreach ($items as $item)
                <tr>
                    <td class="check"><span class="checkbox"></span></td>
                    <td>{{ $item->stockItem->name }}</td>
                    <td class="num">
                        {{ $fmt($item->qty_requested) }} {{ $item->stockItem->unit }}
                        @if ($item->stockItem->hasPackage())
                            <span class="sub">{{ $item->stockItem->packageDisplayFor((float) $item->qty_requested) }}</span>
                        @endif
                        @if ((float) $item->qty_received > 0)
                            <span class="sub">deja primit: {{ $fmt($item->qty_received) }} {{ $item->stockItem->unit }}</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>
    @endif

    <div class="footer">Generat {{ now()->format('d.m.Y H:i') }} &middot; Dance Xplosion Academy — Panou admin</div>

</body>
</html>
