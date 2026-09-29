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
        table.lines td { border-bottom: 1px solid #F2EEEC; padding: 5px 6px; vertical-align: top; }
        table.lines .num { text-align: right; white-space: nowrap; }
        table.lines tr.strong td { font-weight: bold; border-top: 1px solid #EAE4E1; }
        .sub { display: block; font-size: 9px; color: {{ \App\Support\Theme::primary() }}; }
        .ok { color: #15803D; }
        .neg { color: #CA8A04; }
    </style>
</head>
<body>
    @include('pdf._brand')

    <div class="header">
        <h1>{{ $report->title() }} <span class="badge">{{ $report->isSubmitted() ? 'Trimisă' : 'Sesiune închisă' }}</span></h1>
        <span class="muted">
            @if ($report->submitted_at) Trimisă {{ $report->submitted_at->format('d.m.Y H:i') }} @if ($report->submitter) de {{ $report->submitter->name }} @endif @endif
            @if ($report->party) &middot; {{ $report->party->name }} @endif
            @if ($report->group) &middot; {{ $report->group->title() }} @endif
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
        <table class="note"><tr><td><span class="label">Observații</span>{{ $report->note }}</td></tr></table>
    @endif

    <h2>Cash</h2>
    <table class="lines">
        <tr><td>Fond de casă</td><td class="num">{{ $money($fig->opening_float) }} lei</td></tr>
        <tr><td>+ Încasat din vânzări (cash)</td><td class="num">{{ $money($fig->cash_sales) }} lei</td></tr>
        <tr>
            <td>− Bani scoși din casă @if ($report->handed_note)<span class="sub">{{ $report->handed_note }}</span>@endif</td>
            <td class="num">{{ $money($fig->handed_over) }} lei</td>
        </tr>
        <tr class="strong"><td>= Cash așteptat</td><td class="num">{{ $money($fig->expected_cash) }} lei</td></tr>
        <tr class="strong"><td>Cash numărat</td><td class="num">{{ $fig->counted_cash === null ? '—' : $money($fig->counted_cash).' lei' }}</td></tr>
    </table>

    @if ($fig->tokens_received > 0 || $fig->counted_tokens !== null)
        <h2>Tokeni</h2>
        <table class="lines">
            <tr><td>Tokeni primiți ca plată</td><td class="num">{{ number_format($fig->tokens_received, 0, ',', '.') }}</td></tr>
            <tr class="strong"><td>Tokeni numărați</td><td class="num">{{ $fig->counted_tokens === null ? '—' : number_format($fig->counted_tokens, 0, ',', '.') }}</td></tr>
            @if ($fig->tokens_diff !== null)
                <tr><td>Diferență tokeni</td><td class="num">{{ $fig->tokens_diff === 0 ? 'în regulă' : ($fig->tokens_diff > 0 ? '+' : '−').number_format(abs($fig->tokens_diff), 0, ',', '.') }}</td></tr>
            @endif
        </table>
    @endif

    <h2>Încasări pe metodă (sesiunea de vânzări)</h2>
    <table class="lines">
        @forelse ($fig->methods as $m)
            <tr>
                <td>{{ $m['label'] }} @if ($m['tokens'] !== null) · {{ $m['tokens'] }} tokeni @endif @if ($m['note'])<span class="sub">{{ $m['note'] }}</span>@endif</td>
                <td class="num">{{ $money($m['amount']) }} lei</td>
            </tr>
        @empty
            <tr><td colspan="2" class="muted">Nicio încasare.</td></tr>
        @endforelse
        <tr class="strong"><td>Total vândut ({{ $fig->sales_count }} bonuri @if ($fig->cancelled_count > 0), {{ $fig->cancelled_count }} anulate @endif)</td><td class="num">{{ $money($fig->revenue) }} lei</td></tr>
    </table>
</body>
</html>
