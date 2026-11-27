{{-- PDF dashboard report for accreditation (CLAUDE.md §7 M7). Rendered by dompdf: plain CSS, no scripts. --}}
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $meta['title'] }}</title>
<style>
    @page { margin: 22mm 16mm 18mm; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5pt; color: #0f2724; }
    header { border-bottom: 2px solid #1b4a42; padding-bottom: 6px; margin-bottom: 12px; }
    .brand { font-size: 8pt; letter-spacing: .06em; text-transform: uppercase; color: #5c6b66; }
    h1 { font-size: 17pt; margin: 2px 0 4px; color: #1b4a42; }
    .meta { font-size: 8.5pt; color: #5c6b66; }
    .kpis { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 14px; }
    .kpis td { border: 1px solid #e2e5da; border-radius: 4px; padding: 7px 8px; vertical-align: top; width: 20%; }
    .kpis .l { font-size: 7.5pt; color: #5c6b66; text-transform: uppercase; letter-spacing: .04em; }
    .kpis .v { font-size: 14pt; font-weight: bold; color: #1b4a42; }
    .kpis .h { font-size: 7.5pt; color: #5c6b66; }
    h2 { font-size: 11pt; margin: 14px 0 3px; color: #1b4a42; page-break-after: avoid; }
    .note { font-size: 8pt; color: #5c6b66; margin: 0 0 4px; }
    table.t { width: 100%; border-collapse: collapse; page-break-inside: auto; }
    table.t th { text-align: left; background: #f1f0e6; font-size: 8pt; padding: 4px 6px; border-bottom: 1px solid #e2e5da; }
    table.t td { padding: 3px 6px; border-bottom: 1px solid #eef0ea; font-size: 8.5pt; }
    table.t td.n, table.t th.n { text-align: right; }
    tr { page-break-inside: avoid; }
    .bar { height: 7px; background: #1b4a42; border-radius: 2px; }
    footer { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 7.5pt; color: #5c6b66; }
</style>
</head>
<body>
<footer>TUA Research Repository · University Research and Development Center · generated {{ $meta['generated'] }} · trend lines are descriptive only</footer>
<header>
    <div class="brand">TUA Research Repository · University Research and Development Center</div>
    <h1>{{ $meta['title'] }}</h1>
    <div class="meta">{{ $meta['scope'] }} · {{ $meta['filters'] }} · {{ $meta['asOf'] }}</div>
</header>

<table class="kpis"><tr>
    @foreach ($kpis as [$l, $v, $h])
        <td><div class="l">{{ $l }}</div><div class="v">{{ $v }}</div><div class="h">{{ $h }}</div></td>
    @endforeach
</tr></table>

@foreach ($tables as $table)
    @continue($table['title'] === 'Summary')
    @php
        // Two-column tables (label, number) get a small bar so the PDF reads like the chart.
        $barred = count($table['headers']) === 2 && $table['rows'] !== [] && collect($table['rows'])->every(fn ($r) => is_int($r[1]) || is_float($r[1]));
        $max = $barred ? max(1, max(array_map(fn ($r) => $r[1], $table['rows']))) : 1;
    @endphp
    <h2>{{ $table['title'] }}</h2>
    @if (! empty($table['note']))<p class="note">{{ $table['note'] }}</p>@endif
    <table class="t">
        <thead><tr>@foreach ($table['headers'] as $i => $h)<th class="{{ $i > 0 ? 'n' : '' }}">{{ $h }}</th>@endforeach @if ($barred)<th style="width: 32%"></th>@endif</tr></thead>
        <tbody>
            @forelse ($table['rows'] as $row)
                <tr>
                    @foreach ($row as $i => $cell)<td class="{{ $i > 0 && is_numeric($cell) ? 'n' : '' }}">{{ is_float($cell) ? rtrim(rtrim(number_format($cell, 1), '0'), '.') : (is_int($cell) ? number_format($cell) : $cell) }}</td>@endforeach
                    @if ($barred)<td><div class="bar" style="width: {{ round($row[1] / $max * 100) }}%"></div></td>@endif
                </tr>
            @empty
                <tr><td colspan="{{ count($table['headers']) + ($barred ? 1 : 0) }}" class="note">No data for these filters.</td></tr>
            @endforelse
        </tbody>
    </table>
@endforeach
</body>
</html>
