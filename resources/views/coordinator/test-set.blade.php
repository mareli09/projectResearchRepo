{{-- Coordinators label test pairs (CLAUDE.md §7 M5). The score is hidden so it cannot sway the label. --}}
<x-layouts.app title="Label test pairs">
    <x-page-head eyebrow="M5 · Labeled test set" title="Label test pairs"
        lead="Decide whether each study is a duplicate of the stored record. Judge the scope, data and method, not the wording alone. The similarity score is hidden on purpose." />

    <div class="g3 mb-4">
        <x-kpi label="You labeled" :value="number_format($done)" hint="pairs" />
        <x-kpi label="Waiting for you" :value="number_format($waiting)" hint="pairs" :tone="$waiting ? 'warn' : 'ok'" />
        <x-kpi label="Decided in the set" :value="number_format($stats['decided']).' of '.number_format($stats['pairs'])" hint="2 of 3 coordinators agree" />
    </div>

    @if ($pair)
        <div class="side-by-side mb-4">
            <x-panel title="Study">
                <x-slot:actions><span class="mono small text-muted">{{ $pair->public_id }}</span></x-slot:actions>
                <h3 class="font-semibold mb-1.5">{{ $pair->left_title }}</h3>
                <p class="small whitespace-pre-line">{{ $pair->left_abstract }}</p>
                @if ($pair->left_keywords)<div class="chips mt-2">@foreach ($pair->left_keywords as $k)<span class="chip">{{ $k }}</span>@endforeach</div>@endif
            </x-panel>
            <x-panel title="Stored record">
                <x-slot:actions><span class="mono small text-muted">{{ $pair->record->public_id }}</span></x-slot:actions>
                <h3 class="font-semibold mb-1.5">{{ $pair->record->title }}</h3>
                <p class="small whitespace-pre-line">{{ $pair->record->abstract }}</p>
            </x-panel>
        </div>
        <form method="POST" action="{{ route('coordinator.testset.label', $pair) }}" class="flex flex-wrap items-center gap-2">@csrf
            <x-button type="submit" name="label" value="duplicate" variant="bad">Duplicate</x-button>
            <x-button type="submit" name="label" value="distinct" variant="ok">Distinct</x-button>
            <x-button :href="route('coordinator.testset')">Skip for now</x-button>
            <span class="small text-muted">Your label is saved with your name. The other coordinators do not see it.</span>
        </form>
    @else
        <x-empty-state title="Nothing to label right now">
            {{ $stats['pairs'] ? 'You have labeled every pair that still needs a label. Thank you.' : 'The URDC has not added test pairs yet.' }}
        </x-empty-state>
    @endif
</x-layouts.app>
