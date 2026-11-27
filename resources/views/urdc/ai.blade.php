<x-layouts.app title="AI performance">
    <x-page-head eyebrow="M5 · M6 · M7 · Effectiveness of the AI features" title="AI performance"
        lead="Measures from Chapter 2: duplicate screening against a labeled test set, clustering quality, and acceptance of the LLM suggestions taken from the activity log.">
        <x-slot:actions><x-button preview="Sprint 10">Export report</x-button></x-slot:actions>
    </x-page-head>
    <x-preview-note sprint="10" what="All figures on this page are sample, and the threshold slider and export" />

    <div class="g2">
        <x-panel title="Duplicate screening">
            <x-slot:actions><span class="text-muted small">{{ array_sum($cm) }} labeled pairs · thresholds {{ number_format(config('tua.screening.duplicate_threshold'), 2) }} / {{ number_format(config('tua.screening.review_threshold'), 2) }}</span></x-slot:actions>
            <div class="g2 items-center">
                <div class="cm" role="table" aria-label="Confusion matrix">
                    <div class="h"></div><div class="h">Labeled duplicate</div><div class="h">Labeled distinct</div>
                    <div class="h" style="text-align:right">Flagged</div><div class="tp">TP {{ $cm['tp'] }}</div><div class="er">FP {{ $cm['fp'] }}</div>
                    <div class="h" style="text-align:right">Not flagged</div><div class="er">FN {{ $cm['fn'] }}</div><div class="tp">TN {{ $cm['tn'] }}</div>
                </div>
                <div class="grid grid-cols-2 gap-2.5">
                    @foreach ($metrics as $name => $v)<div><div class="eyebrow">{{ $name }}</div><div class="mono text-xl font-medium">{{ number_format($v, 3) }}</div></div>@endforeach
                </div>
            </div>
            <div class="flex flex-col gap-1 mt-3"><label for="th" class="small font-medium">Try a duplicate threshold</label>
                <input id="th" type="range" min="0.5" max="0.95" step="0.01" value="{{ config('tua.screening.duplicate_threshold') }}" data-preview="Sprint 10" data-preview-what="Threshold slider"></div>
            <p class="small text-muted mt-2.5">Pairs labeled by majority of three research coordinators. Confirmed coordinator decisions are added to this set to recalibrate the thresholds.</p>
        </x-panel>
        <x-panel title="Thematic clustering">
            <x-slot:actions><span class="text-muted small">{{ $run['id'] }} · k = {{ $run['k'] }}</span></x-slot:actions>
            <div class="g3">
                <div><div class="eyebrow">Silhouette</div><div class="mono text-xl font-medium">{{ number_format($run['sil'], 3) }}</div><div class="text-muted small">higher is better</div></div>
                <div><div class="eyebrow">Davies–Bouldin</div><div class="mono text-xl font-medium">{{ number_format($run['db'], 2) }}</div><div class="text-muted small">lower is better</div></div>
                <div><div class="eyebrow">Meaningful labels</div><div class="mono text-xl font-medium">{{ $meaningful }}</div><div class="text-muted small">judged by coordinators</div></div>
            </div>
            <x-chart :option="$runsChart" size="short" label="Clustering quality per run" class="mt-2" />
        </x-panel>
        <x-panel title="LLM suggestions" class="span2">
            <x-slot:actions><span class="small text-muted"><span class="ai-tag">LLM</span> acceptance rate = accepted without changes ÷ all suggestions</span></x-slot:actions>
            <div class="g5 mb-3">
                @foreach ($rates as $r)<x-kpi :label="$r['name']" :value="number_format($r['rate']).'%'" :hint="'accepted of '.number_format($r['total'])" />@endforeach
                <x-kpi label="Label rating" :value="number_format($avgRating, 2)" :hint="'mean of 4 · '.$ratedCount.' rated'" />
            </div>
            <x-chart :option="$llmChart" label="LLM suggestion outcomes" />
            <p class="small text-muted mt-2">The fallback to top TF-IDF terms is counted separately. The LLM takes no part in duplicate screening.</p>
        </x-panel>
    </div>
</x-layouts.app>
