{{-- The 13 visual analytics of Table 7 (CLAUDE.md §7 M7). Click any bar, slice or point to see the records behind it. --}}
@php
    $base = route('search');
    $sel = 'min-h-11 rounded-md border border-line bg-surface px-3';
    // Drill-down links keep the current filters. __Y__/__N__ become {name} and __K__ becomes {key} for the chart script.
    $q = fn (array $extra) => str_replace(['__Y__', '__N__', '__K__'], ['{name}', '{name}', '{key}'],
        $base.'?'.http_build_query(array_filter(array_merge(array_intersect_key($filters, array_flip(['college', 'year', 'sdg'])), $extra))));
    $themes = \App\Domain\Site\Corpus::themeLabels();
@endphp
<x-layouts.app :title="$title">
    <x-page-head :eyebrow="$eyebrow" :title="$title">
        <x-slot:actions>
            <x-button preview="Sprint 7" data-preview-what="Export Excel">Export Excel</x-button>
            <x-button preview="Sprint 7" data-preview-what="Export PDF report">Export PDF report</x-button>
        </x-slot:actions>
    </x-page-head>

    <form method="GET" action="{{ route($route) }}" class="panel mb-4 flex flex-wrap items-end gap-3" style="padding: 12px 16px">
        <div class="flex flex-col gap-1"><label for="an-college" class="text-[13px] font-medium">College</label>
            <select id="an-college" name="college" class="{{ $sel }}" onchange="this.form.submit()">
                <option value="">{{ count($scope) === 6 ? 'All colleges' : 'All in cluster' }}</option>
                @foreach ($scope as $c)<option @selected(($filters['college'] ?? '') === $c)>{{ $c }}</option>@endforeach
            </select></div>
        <div class="flex flex-col gap-1"><label for="an-year" class="text-[13px] font-medium">Year</label>
            <select id="an-year" name="year" class="{{ $sel }}" onchange="this.form.submit()">
                <option value="">All years</option>@foreach (range(2025, 2015) as $y)<option @selected(($filters['year'] ?? '') == $y)>{{ $y }}</option>@endforeach
            </select></div>
        <div class="flex flex-col gap-1"><label for="an-theme" class="text-[13px] font-medium">Theme</label>
            <select id="an-theme" name="theme" class="{{ $sel }}" onchange="this.form.submit()">
                <option value="">All themes</option>@foreach ($themes as $id => $l)<option value="{{ $id }}" @selected(($filters['theme'] ?? '') == $id)>{{ $l }}</option>@endforeach
            </select></div>
        <div class="flex flex-col gap-1"><label for="an-sdg" class="text-[13px] font-medium">SDG</label>
            <select id="an-sdg" name="sdg" class="{{ $sel }}" onchange="this.form.submit()">
                <option value="">All SDGs</option>@foreach (range(1, 17) as $s)<option value="{{ $s }}" @selected(($filters['sdg'] ?? '') == $s)>SDG {{ $s }}</option>@endforeach
            </select></div>
        <noscript><x-button type="submit">Apply</x-button></noscript>
        <span class="text-muted small ml-auto">Click any bar, slice or point to see the records behind it.</span>
    </form>

    @php $kpiIcons = [['book', 'accent'], ['chart', 'ok'], ['heart', 'gold'], ['users', 'warn'], ['import', 'bad']]; @endphp
    <div class="g5 mb-4">@foreach ($d['kpis'] as $i => [$l, $v, $h, $up])<x-kpi :label="$l" :value="$v" :hint="$h" :up="$up === 'up'" :icon="$kpiIcons[$i][0] ?? null" :icon-tone="$kpiIcons[$i][1] ?? 'accent'" />@endforeach</div>

    <div class="g2">
        <x-panel title="Research output per year"><x-slot:actions><span class="text-muted small">trend line is descriptive only</span></x-slot:actions>
            <x-chart :option="$d['charts']['trend']" label="Research output per year" :link="$q(['year' => '__Y__'])" /></x-panel>
        <x-panel title="Outputs by college and type"><x-chart :option="$d['charts']['college']" label="Outputs by college and research type" :link="$base.'?college={name}&type={series}'" /></x-panel>
        <x-panel title="Research theme map"><x-slot:actions><span class="text-muted small">K-Means, 2-D projection · {{ \App\Domain\Site\Corpus::currentRun() ? 'themes of '.\App\Domain\Site\Corpus::currentRun()->public_id : 'sample themes until the URDC confirms a run' }}</span></x-slot:actions>
            <x-chart :option="$d['charts']['scatter']" size="tall" label="Research theme map" :link="url('/studies').'/{id}'" /></x-panel>
        <x-panel title="Theme distribution"><x-chart :option="$d['charts']['themes']" size="tall" label="Theme distribution" :link="$q(['theme' => '__K__'])" /></x-panel>
        @if ($full)
            <x-panel title="Themes by college" class="span2"><x-slot:actions><span class="text-muted small">Gaps show where colleges could collaborate</span></x-slot:actions>
                <x-chart :option="$d['charts']['heat']" size="tall" label="Themes by college heat map" /></x-panel>
        @endif
        <x-panel title="Distribution by research type"><x-chart :option="$d['charts']['type']" label="Distribution by research type" :link="$q(['type' => '__K__'])" /></x-panel>
        <x-panel title="SDG alignment"><x-chart :option="$d['charts']['sdg']" label="SDG alignment" :link="$q(['sdg' => '__K__'])" /></x-panel>
        <x-panel title="Top keywords"><x-slot:actions><span class="text-muted small">last 3 years</span></x-slot:actions>
            <x-chart :option="$d['charts']['keywords']" label="Top keywords" :link="$q(['q' => '__N__'])" /></x-panel>
        <x-panel title="Adviser load"><x-slot:actions><span class="text-muted small">studies supervised, last 3 years</span></x-slot:actions>
            <x-chart :option="$d['charts']['advisers']" label="Adviser load" :link="$q(['adviser' => '__N__'])" /></x-panel>
        <x-panel title="Similarity screening summary"><x-slot:actions><span class="text-muted small">AY 2026–2027 · sample</span></x-slot:actions>
            <div class="g3 mb-2.5">
                <div><div class="eyebrow">Screened</div><div class="num text-xl font-semibold">186</div></div>
                <div><div class="eyebrow">Flagged</div><div class="num text-xl font-semibold" style="color: var(--warn)">31</div></div>
                <div><div class="eyebrow">Confirmed duplicates</div><div class="num text-xl font-semibold" style="color: var(--bad)">6</div></div>
            </div>
            <x-chart :option="$d['charts']['screening']" size="short" label="Screening results per month" /></x-panel>
        <x-panel title="Submission compliance per unit"><x-slot:actions><span class="text-muted small">all required files</span></x-slot:actions>
            <div class="tbl"><table>
                <thead><tr><th>Unit</th><th class="r">Expected</th><th class="r">Complete</th><th>Rate</th></tr></thead>
                <tbody>
                    @foreach ($d['compliance'] as $c)
                        <tr><td>{{ $c['college'] }}</td><td class="r num">{{ number_format($c['expected']) }}</td><td class="r num">{{ number_format($c['complete']) }}</td>
                            <td><div class="score"><span class="num small">{{ number_format($c['rate']) }}%</span><div class="bar"><i style="width: {{ $c['rate'] }}%; background: {{ $c['rate'] >= 85 ? 'var(--ok)' : 'var(--warn)' }}"></i></div></div></td></tr>
                    @endforeach
                </tbody>
            </table></div></x-panel>
        @if ($full)
            <x-panel title="Repository usage"><x-slot:actions><span class="text-muted small">last 12 weeks · sample</span></x-slot:actions>
                <x-chart :option="$d['charts']['usage']" label="Repository usage" /></x-panel>
            <x-panel title="Most accessed studies">
                <div class="list">@foreach ($d['topAccessed'] as $r)<div class="ph"><a href="{{ route('studies.show', $r['public_id']) }}">{{ $r['title'] }}</a><span class="num small text-muted">{{ number_format($r['views']) }} views</span></div>@endforeach</div></x-panel>
            <x-panel title="Searches with no or few results" class="span2"><x-slot:actions><span class="text-muted small">possible gaps in the collection · last 90 days</span></x-slot:actions>
                <div class="tbl"><table>
                    <thead><tr><th>Search term</th><th class="r">Times searched</th><th class="r">Results</th><th>Last searched</th></tr></thead>
                    <tbody>@forelse ($d['searchGaps'] as $g)<tr><td>{{ $g[0] }}</td><td class="r num">{{ $g[1] }}</td><td class="r num">@if ($g[2] === 0)<x-pill variant="bad">0</x-pill>@else{{ $g[2] }}@endif</td><td>{{ $g[3] }}</td></tr>@empty<tr><td colspan="4" class="text-muted">No searches with few results yet.</td></tr>@endforelse</tbody>
                </table></div></x-panel>
        @endif
    </div>
</x-layouts.app>
