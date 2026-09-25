@php
    $money = fn ($n) => number_format((float) $n, 2, ',', '.');
    $signed = fn ($n) => ($n > 0 ? '+' : ($n < 0 ? '−' : '')).number_format(abs((float) $n), 2, ',', '.');
    $diff = $fig->cash_diff;
    $diffOk = $diff !== null && abs($diff) < 0.005;
@endphp
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <title>{{ $report->title() }}</title>
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

        table.note { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        table.note td { border: 1px solid #EAE4E1; padding: 8px 10px; }
        table.note .label { display: block; font-size: 9px; text-transform: uppercase; color: #6B5D57; margin-bottom: 2px; }

        table.lines { width: 100%; border-collapse: collapse; }
        table.lines th { text-align: left; font-size: 9px; text-transform: uppercase; color: #6B5D57; border-bottom: 1px solid #EAE4E1; padding: 4px 6px; }
        table.lines td { border-bottom: 1px solid #F2EEEC; padding: 5px 6px; vertical-align: top; }
        table.lines .num { text-align: right; white-space: nowrap; }
        table.lines tr.strong td { font-weight: bold; border-top: 1px solid #EAE4E1; }
        .sub { display: block; font-size: 9px; color: {{ \App\Support\Theme::primary() }}; }
        .empty { font-style: italic; color: #6B5D57; padding: 6px; }
        .ok { color: #15803D; }
        .neg { color: #CA8A04; }

        .footer { margin-top: 20px; font-size: 9px; color: #6B5D57; }
    </style>
</head>
<body>

    @include('pdf._brand')

    <div class="header">
        <h1>{{ $report->title() }} <span class="badge">Finalizat</span></h1>
        <span class="muted">
            Finalizată {{ $report->finalized_at?->format('d.m.Y H:i') }}
            @if ($report->finalizer) de {{ $report->finalizer->name }} @endif
            @if ($report->party) &middot; {{ $report->party->name }} @endif
            @if ($report->session) &middot; {{ $report->session->title() }} @endif
        </span>
    </div>

    <table class="totals">
        <tr>
            <td><span class="label">Cash așteptat</span><span class="value">{{ $money($fig->expected_cash) }} lei</span></td>
            <td><span class="label">Cash numărat</span><span class="value">{{ $fig->counted_cash === null ? '—' : $money($fig->counted_cash).' lei' }}</span></td>
            <td><span class="label">Diferență</span><span class="value {{ $diff === null ? '' : ($diffOk ? 'ok' : 'neg') }}">{{ $diff === null ? '—' : $signed($diff).' lei' }}</span></td>
        </tr>
    </table>

    @if ($report->note)
        <table class="note">
            <tr><td><span class="label">Notă</span>{{ $report->note }}</td></tr>
        </table>
    @endif

    <h2>Cash</h2>
    <table class="lines">
        <tr><td>Fond de casă</td><td class="num">{{ $money($fig->opening_float) }} lei</td></tr>
        <tr><td>+ Încasat din intrări (cash)</td><td class="num">{{ $money($fig->cash_entries) }} lei</td></tr>
        <tr><td>+ Încasat din tokeni (cash)</td><td class="num">{{ $money($fig->cash_tokens) }} lei</td></tr>
        <tr>
            <td>
                − Predat / scos din casă
                @if ($report->handed_note)<span class="sub">{{ $report->handed_note }}</span>@endif
            </td>
            <td class="num">{{ $money($fig->handed_over) }} lei</td>
        </tr>
        <tr class="strong"><td>= Cash așteptat</td><td class="num">{{ $money($fig->expected_cash) }} lei</td></tr>
        <tr class="strong"><td>Cash numărat</td><td class="num">{{ $fig->counted_cash === null ? '—' : $money($fig->counted_cash).' lei' }}</td></tr>
    </table>

    <h2>Alte metode de plată (totaluri așteptate)</h2>
    @if (empty($fig->other_methods))
        <p class="empty">Nicio încasare cu altă metodă decât cash.</p>
    @else
        <table class="lines">
            <tr><th>Metodă</th><th class="num">Total</th></tr>
            @foreach ($fig->other_methods as $m)
                <tr>
                    <td>
                        {{ $m['label'] }}
                        @if (! empty($m['note']))<span class="sub">{{ $m['note'] }}</span>@endif
                    </td>
                    <td class="num">{{ $money($m['amount']) }} lei</td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>Activitate în sesiune</h2>
    <table class="lines">
        <tr><td>Intrări (neanulate)</td><td class="num">{{ $fig->entries_count }} (din care {{ $fig->entries_free }} gratuite) · {{ $money($fig->entries_revenue) }} lei</td></tr>
        @foreach ($fig->tickets as $t)
            <tr><td><span class="muted">&nbsp;&nbsp;{{ $t['name'] }}</span></td><td class="num">{{ $t['count'] }} × · {{ $money($t['revenue']) }} lei</td></tr>
        @endforeach
        <tr><td>Tokeni vânduți</td><td class="num">{{ number_format($fig->tokens_sold, 0, ',', '.') }} în {{ $fig->token_sales }} {{ $fig->token_sales === 1 ? 'vânzare' : 'vânzări' }} · {{ $money($fig->tokens_amount) }} lei</td></tr>
        @if ($fig->entries_cancelled > 0 || $fig->token_sales_cancelled > 0)
            <tr><td class="muted">Anulate (nu intră în totaluri)</td><td class="num muted">{{ $fig->entries_cancelled }} intrări · {{ $fig->token_sales_cancelled }} vânzări de tokeni</td></tr>
        @endif
    </table>

    <div class="footer">
        Valorile sunt înghețate la finalizare. Diferența de casă nu generează mișcări automate.
    </div>
</body>
</html>
