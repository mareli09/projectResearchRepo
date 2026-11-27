{{-- Labeled screening test set (CLAUDE.md §7 M5, §17): build the pairs; coordinators label them. --}}
<x-layouts.app title="Labeled test set">
    <x-page-head eyebrow="M5 · SOP 3.1 · Evaluation" title="Labeled test set"
        lead="Pairs of a study and a stored record. Three coordinators label each pair duplicate or distinct, without seeing the score. The label at least two of them give is the reference. AI performance measures screening against it.">
        <x-slot:actions>
            <x-button :href="route('urdc.testset.export')">Export CSV</x-button>
            <x-button :href="route('urdc.ai')" variant="primary">AI performance</x-button>
        </x-slot:actions>
    </x-page-head>

    @if ($errs = session('importErrors'))
        <x-callout variant="bad" class="mb-4"><b>Some rows were not imported:</b><ul class="list-disc pl-5 mt-1 small">@foreach ($errs as $e)<li>{{ $e }}</li>@endforeach</ul></x-callout>
    @endif

    <div class="g5 mb-4">
        <x-kpi label="Pairs" :value="number_format($stats['pairs'])" :hint="'target at least '.$target" :tone="$stats['pairs'] >= $target ? 'ok' : 'warn'" />
        <x-kpi label="Decided" :value="number_format($stats['decided'])" hint="2 of 3 coordinators agree" :tone="$stats['decided'] >= $target ? 'ok' : null" />
        <x-kpi label="Duplicates" :value="number_format($stats['duplicates'])" hint="about half is the aim" />
        <x-kpi label="Distinct" :value="number_format($stats['distinct'])" hint="reference label" />
        <x-kpi label="Labels given" :value="number_format($stats['labels'])" :hint="$stats['unscored'] ? $stats['unscored'].' pairs not scored yet' : 'all pairs scored'" :tone="$stats['unscored'] ? 'warn' : null" />
    </div>

    <div class="g2 mb-4">
        <x-panel title="Add pairs from the repository">
            <p class="small text-muted mb-2.5">Half are each study’s most similar record (the hard cases), half are random pairs. The repository rarely holds true duplicates, so import prepared near-duplicates too, to reach about half duplicates.</p>
            <form method="POST" action="{{ route('urdc.testset.sample') }}" class="flex flex-wrap items-end gap-2">@csrf
                <x-input name="n" type="number" label="How many pairs" value="50" min="2" max="200" class="w-28" />
                <x-button type="submit">Add pairs</x-button>
            </form>
        </x-panel>
        <x-panel title="Import prepared pairs (CSV)">
            <p class="small text-muted mb-2.5">Columns: <span class="mono">title, abstract, keywords, record</span>. Keywords are separated by <span class="mono">;</span> and the record is its ID, for example <span class="mono">TUA-2023-0142</span>. Use rewritten or partly copied studies to make the duplicate cases.</p>
            <form method="POST" action="{{ route('urdc.testset.import') }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-2">@csrf
                <div class="flex flex-col gap-1"><label for="ts-file" class="font-medium">CSV file</label>
                    <input id="ts-file" type="file" name="file" accept=".csv,text/csv" class="text-[13px]"><x-form-error name="file" /></div>
                <x-button type="submit">Import</x-button>
            </form>
        </x-panel>
    </div>

    @if ($stats['unscored'])
        <form method="POST" action="{{ route('urdc.testset.score') }}" class="mb-3">@csrf
            <x-callout variant="gold"><div class="flex flex-wrap items-center justify-between gap-2"><span>{{ $stats['unscored'] }} pairs have no similarity score yet (the analytics service was not reachable).</span><x-button type="submit" size="sm">Score pairs</x-button></div></x-callout>
        </form>
    @endif

    <div class="tbl">
        <table>
            <thead><tr><th>Pair</th><th>Study</th><th>Compared with</th><th class="r">Score</th><th>Labels</th><th>Reference</th><th></th></tr></thead>
            <tbody>
                @forelse ($pairs as $p)
                    @php $ref = \App\Domain\Screening\TestSet::majority($p->labels); @endphp
                    <tr>
                        <td class="mono small">{{ $p->public_id }}<div class="text-muted">{{ ['sample_neighbour' => 'similar', 'sample_random' => 'random', 'import' => 'imported'][$p->source] }}</div></td>
                        <td class="small">{{ \Illuminate\Support\Str::limit($p->left_title, 70) }}</td>
                        <td class="small"><span class="mono">{{ $p->record->public_id ?? '—' }}</span> {{ \Illuminate\Support\Str::limit((string) ($p->record->title ?? ''), 50) }}</td>
                        <td class="r num">{{ $p->score === null ? '—' : number_format((float) $p->score, 3) }}</td>
                        <td class="small whitespace-nowrap">{{ $p->labels->where('label', 'duplicate')->count() }} dup · {{ $p->labels->where('label', 'distinct')->count() }} dist</td>
                        <td>@if ($ref === 'duplicate')<x-pill variant="bad">Duplicate</x-pill>@elseif ($ref === 'distinct')<x-pill variant="ok">Distinct</x-pill>@else<x-pill>Waiting</x-pill>@endif</td>
                        <td class="r"><form method="POST" action="{{ route('urdc.testset.remove', $p) }}" data-confirm="Remove {{ $p->public_id }} and its labels?">@csrf<x-button type="submit" size="sm">Remove</x-button></form></td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty-state title="No pairs yet">Add pairs from the repository or import prepared pairs. Coordinators then label them under “Label test pairs”.</x-empty-state></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $pairs->links() }}</div>
</x-layouts.app>
