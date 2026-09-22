@php
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, ',', '.'), '0'), ',');
    $money = fn ($n) => number_format((float) $n, 2, ',', '.');
@endphp
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <title>Raportare {{ $report->date->format('d.m.Y') }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #241A16; margin: 0; }
        h1 { font-size: 17px; margin: 0 0 2px; }
        h2 { font-size: 12px; margin: 18px 0 6px; padding-bottom: 4px; border-bottom: 1px solid #EAE4E1; }
        .muted { color: #6B5D57; }
        .header { margin-bottom: 14px; }
        .badge { display: inline-block; font-size: 9px; font-weight: bold; color: #1D6FB8; background: #E3EFF7; padding: 2px 7px; border-radius: 8px; }

        table.totals { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        table.totals td { width: 33.33%; border: 1px solid #EAE4E1; padding: 8px 10px; }
        table.totals .label { display: block; font-size: 9px; text-transform: uppercase; color: #6B5D57; margin-bottom: 2px; }
        table.totals .value { font-size: 14px; font-weight: bold; }
        .profit-neg { color: #DC2626; }

        table.note { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        table.note td { border: 1px solid #EAE4E1; padding: 8px 10px; }

        table.lines { width: 100%; border-collapse: collapse; }
        table.lines th { text-align: left; font-size: 9px; text-transform: uppercase; color: #6B5D57; border-bottom: 1px solid #EAE4E1; padding: 4px 6px; }
        table.lines td { border-bottom: 1px solid #F2EEEC; padding: 5px 6px; vertical-align: top; }
        table.lines .num { text-align: right; white-space: nowrap; }
        .sub { display: block; font-size: 9px; color: #DD6441; }
        .empty { font-style: italic; color: #6B5D57; padding: 6px; }

        .footer { margin-top: 20px; font-size: 9px; color: #6B5D57; }
    </style>
</head>
<body>

    <div class="header">
        <h1>Raportare {{ $report->date->format('d.m.Y') }} <span class="badge">Finalizat</span></h1>
        <span class="muted">
            Finalizată {{ $report->finalized_at?->format('d.m.Y H:i') }}
            @if ($report->finalizer) de {{ $report->finalizer->name }} @endif
            @if ($report->party) &middot; {{ $report->party->name }} @endif
        </span>
    </div>

    <table class="totals">
        <tr>
            <td><span class="label">Venit</span><span class="value">{{ $money($revenue) }} lei</span></td>
            <td><span class="label">Cost</span><span class="value">{{ $money($cost) }} lei</span></td>
            <td><span class="label">Profit</span><span class="value {{ $profit < 0 ? 'profit-neg' : '' }}">{{ $money($profit) }} lei</span></td>
        </tr>
    </table>

    @if ($report->note)
        <table class="note">
            <tr><td><span class="label">Notă</span>{{ $report->note }}</td></tr>
        </table>
    @endif

    <h2>Intrări</h2>
    @if ($entries->isEmpty())
        <p class="empty">Nicio intrare.</p>
    @else
        <table class="lines">
            <tr><th>Produs</th><th class="num">Cantitate</th><th class="num">Cost/unit.</th></tr>
            @foreach ($entries as $m)
                <tr>
                    <td>
                        {{ $m->stockItem->name }}
                        @if ($m->requisitionItem)
                            <span class="sub">din necesar „{{ $m->requisitionItem->requisition->label }}"</span>
                        @endif
                    </td>
                    <td class="num">{{ $fmt($m->qty) }} {{ $m->stockItem->unit }}</td>
                    <td class="num">{{ $m->unit_cost !== null ? number_format((float) $m->unit_cost, 4, ',', '.').' lei/'.$m->stockItem->unit : 'necunoscut' }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>Vânzări</h2>
    @if ($sales->isEmpty())
        <p class="empty">Nicio vânzare.</p>
    @else
        <table class="lines">
            <tr><th>Produs</th><th class="num">Cantitate</th><th class="num">Preț</th><th class="num">Total</th></tr>
            @foreach ($sales as $s)
                <tr>
                    <td>{{ $s->menuItem?->name ?? '—' }}</td>
                    <td class="num">{{ $fmt($s->qty) }}</td>
                    <td class="num">{{ $money($s->unit_price) }} lei</td>
                    <td class="num">{{ $money($s->total_price) }} lei</td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>Pierderi</h2>
    @if ($losses->isEmpty())
        <p class="empty">Nicio pierdere.</p>
    @else
        <table class="lines">
            <tr><th>Produs</th><th class="num">Cantitate</th><th>Motiv</th></tr>
            @foreach ($losses as $l)
                <tr>
                    <td>{{ $l->stockItem->name }}</td>
                    <td class="num">{{ $fmt($l->qty) }} {{ $l->stockItem->unit }}</td>
                    <td>{{ $l->note ?: '—' }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <div class="footer">Generat {{ now()->format('d.m.Y H:i') }} &middot; Dance Xplosion Academy — Panou admin</div>

</body>
</html>
