{{-- "AI performance" (CLAUDE.md §7 M5, SOP 3.1–3.4). Every figure comes from the database. --}}
@php
    $fmt = fn (?float $v, int $d = 3) => $v === null ? '—' : number_format($v, $d);
    $rate = fn (array $s) => ($s['accepted'] + $s['edited'] + $s['rejected']) > 0 ? $s['accepted'] / ($s['accepted'] + $s['edited'] + $s['rejected']) * 100 : null;
@endphp
<x-layouts.app title="AI performance">
    <x-page-head eyebrow="M5 · M6 · M7 · Effectiveness of the AI features" title="AI performance"
        lead="Measures from Chapter 2: duplicate screening against the coordinators' labeled pairs, clustering quality, and what users did with the LLM suggestions.">
        <x-slot:actions><x-button :href="route('urdc.ai.export')">Export report (CSV)</x-button></x-slot:actions>
    </x-page-head>

    <div class="g2">
        <x-panel title="Duplicate screening">
            <x-slot:actions><span class="text-muted small">{{ count($pairs) }} labeled pairs · {{ $duplicates }} duplicates</span></x-slot:actions>
            @if ($pairs === [])
                <x-empty-state title="No labeled pairs yet">Each coordinator decision records a calibration label (duplicate or distinct). The evaluation needs at least 100 pairs, about half duplicates.</x-empty-state>
            @else
                <div x-data="{
                        pairs: @js(array_map(fn ($p) => [$p['score'], $p['duplicate']], $pairs)),
                        t: {{ $threshold }},
                        get cm() { let c = { tp: 0, fp: 0, fn: 0, tn: 0 }; for (const [s, d] of this.pairs) { const f = s >= this.t - 1e-9; c[f ? (d ? 'tp' : 'fp') : (d ? 'fn' : 'tn')]++; } return c; },
                        get m() { const c = this.cm, n = c.tp + c.fp + c.fn + c.tn, p = c.tp + c.fp ? c.tp / (c.tp + c.fp) : null, r = c.tp + c.fn ? c.tp / (c.tp + c.fn) : null;
                            return { Accuracy: n ? (c.tp + c.tn) / n : null, Precision: p, Recall: r, 'F1-score': p !== null && r !== null && p + r ? 2 * p * r / (p + r) : null }; },
                        f(v) { return v === null ? '—' : v.toFixed(3); }
                    }">
                    <div class="g2 items-center">
                        <div class="cm" role="table" aria-label="Confusion matrix">
                            <div class="h"></div><div class="h">Labeled duplicate</div><div class="h">Labeled distinct</div>
                            <div class="h" style="text-align:right">Flagged</div><div class="tp">TP <span x-text="cm.tp">{{ $cm['tp'] }}</span></div><div class="er">FP <span x-text="cm.fp">{{ $cm['fp'] }}</span></div>
                            <div class="h" style="text-align:right">Not flagged</div><div class="er">FN <span x-text="cm.fn">{{ $cm['fn'] }}</span></div><div class="tp">TN <span x-text="cm.tn">{{ $cm['tn'] }}</span></div>
                        </div>
                        <div class="grid grid-cols-2 gap-2.5">
                            @foreach (['Accuracy' => 'accuracy', 'Precision' => 'precision', 'Recall' => 'recall', 'F1-score' => 'f1'] as $name => $k)
                                <div><div class="eyebrow">{{ $name }}</div><div class="mono text-xl font-medium" x-text="f(m['{{ $name }}'])">{{ $fmt($cm[$k]) }}</div></div>
                            @endforeach
                        </div>
                    </div>
                    <div class="flex flex-col gap-1 mt-3">
                        <label for="th" class="small font-medium">Try a duplicate threshold: <b class="mono" x-text="Number(t).toFixed(2)">{{ number_format($threshold, 2) }}</b> <span class="text-muted">(in use: {{ number_format($threshold, 2) }})</span></label>
                        <input id="th" type="range" min="0.3" max="0.95" step="0.01" x-model.number="t">
                    </div>
                    <p class="small text-muted mt-2.5">Moving the slider changes nothing in the system. To change the threshold in use, go to System settings.</p>
                </div>
            @endif
            <p class="small text-muted mt-2.5">Labels come from the coordinators' decisions. For the evaluation, each pair in the test set is labeled by majority of three coordinators.</p>
        </x-panel>

        <x-panel title="Thematic clustering">
            @if ($run)
                <x-slot:actions><span class="text-muted small">{{ $run->public_id }} · k = {{ $run->k }} · {{ $run->state }}</span></x-slot:actions>
                <div class="g3">
                    <div><div class="eyebrow">Silhouette</div><div class="mono text-xl font-medium">{{ $fmt($run->silhouette !== null ? (float) $run->silhouette : null) }}</div><div class="text-muted small">higher is better</div></div>
                    <div><div class="eyebrow">Davies–Bouldin</div><div class="mono text-xl font-medium">{{ $fmt($run->davies_bouldin !== null ? (float) $run->davies_bouldin : null, 2) }}</div><div class="text-muted small">lower is better</div></div>
                    <div><div class="eyebrow">Meaningful labels</div><div class="mono text-xl font-medium">{{ $meaningful }}</div><div class="text-muted small">kept by coordinators</div></div>
                </div>
                @if ($runsChart)<x-chart :option="$runsChart" size="short" label="Clustering quality per run" class="mt-2" />@endif
            @else
                <x-empty-state title="No clustering run yet">Run clustering on the Theme clustering page.</x-empty-state>
            @endif
        </x-panel>

        <x-panel title="LLM suggestions" class="span2">
            <x-slot:actions><span class="small text-muted"><span class="ai-tag">LLM</span> acceptance rate = accepted without changes ÷ suggestions acted on</span></x-slot:actions>
            <div class="g5 mb-3">
                @foreach (\App\Domain\Analytics\AiPerformance::KINDS as $k => $name)
                    @php $r = $rate($llm[$k]); @endphp
                    <x-kpi :label="$name" :value="$r === null ? '—' : number_format($r).'%'" :hint="'accepted of '.number_format($llm[$k]['accepted'] + $llm[$k]['edited'] + $llm[$k]['rejected']).($llm[$k]['fallback'] ? ' · '.$llm[$k]['fallback'].' fallback' : '')" />
                @endforeach
                <x-kpi label="Label rating" :value="$ratings['avg'] === null ? '—' : number_format($ratings['avg'], 2)" :hint="'mean of 4 · '.$ratings['count'].' rated'" />
            </div>
            <x-chart :option="$llmChart" label="LLM suggestion outcomes" />
            <p class="small text-muted mt-2">Suggestions made by the fallback (top TF-IDF terms) are counted in the totals and shown separately. The LLM takes no part in duplicate screening.</p>
        </x-panel>
    </div>
</x-layouts.app>
