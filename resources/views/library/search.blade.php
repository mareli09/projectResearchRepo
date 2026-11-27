{{-- Public catalog (guests) and Search (signed in) share this screen (CLAUDE.md §7 M4). --}}
@php
    $labels = ['college' => 'College', 'year' => 'Year', 'type' => 'Research type', 'theme' => 'Theme', 'sdg' => 'SDG', 'adviser' => 'Adviser'];
    $action = $public ? route('catalog') : route('search');
    $show = fn ($r) => $public ? route('catalog.show', $r->public_id) : route('studies.show', $r->public_id);
    $sel = 'min-h-11 rounded-md border border-line bg-surface px-3 w-full text-[14px]';
@endphp
<x-dynamic-component :component="$public ? 'layouts.site' : 'layouts.app'" :title="$public ? 'Public catalog' : 'Search'">
    <div @class(['ls-in' => $public]) @if ($public) style="padding-block:32px" @endif>
        <x-page-head :eyebrow="$public ? 'Public catalog · approved titles and abstracts' : 'Search · M4'" :title="$public ? 'Browse TUA research' : 'Search the repository'">
            @if ($public)
                <x-slot:actions>
                    <x-button :href="route('login')">Sign in</x-button>
                    <x-button :href="route('register')" variant="primary">Create account</x-button>
                </x-slot:actions>
            @endif
        </x-page-head>

        @if ($public)
            <x-callout class="mb-4">Guests can read titles and abstracts of approved studies. Full papers need a TUA account and are released according to URDC policy.</x-callout>
        @endif

        <form method="GET" action="{{ $action }}" class="flex flex-col gap-4" role="search">
            <div class="searchbar">
                <label for="q" class="sr-only">Search</label>
                <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search titles, abstracts and keywords"
                       class="flex-1 min-h-11 rounded-md border border-line bg-surface px-4">
                <x-button type="submit" variant="primary">Search</x-button>
            </div>

            <div class="layout-search">
                <aside class="panel" style="padding: 8px 14px" aria-label="Filters">
                    @foreach ($labels as $key => $label)
                        <div class="facet">
                            <label for="f-{{ $key }}" class="text-[13px] font-medium">{{ $label }}</label>
                            <select id="f-{{ $key }}" name="{{ $key }}" class="{{ $sel }}" onchange="this.form.submit()">
                                <option value="">All</option>
                                @foreach ($facets[$key] as $v => $l)
                                    <option value="{{ $v }}" @selected((string) ($filters[$key] ?? '') === (string) $v)>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                    <div class="facet">
                        <label for="f-sort" class="text-[13px] font-medium">Sort by</label>
                        <select id="f-sort" name="sort" class="{{ $sel }}" onchange="this.form.submit()">
                            @foreach (['relevance' => 'Relevance', 'newest' => 'Newest', 'views' => 'Most viewed'] as $v => $l)
                                <option value="{{ $v }}" @selected(($filters['sort'] ?? (! empty($filters['q']) ? 'relevance' : 'newest')) === $v)>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="facet"><x-button :href="$action" size="sm">Clear filters</x-button></div>
                </aside>

                <section class="panel" style="padding-block: 6px" aria-live="polite">
                    <div class="ph pt-2.5">
                        <span class="text-muted small">{{ number_format($results->total()) }} {{ \Illuminate\Support\Str::plural('study', $results->total()) }}@if (! empty($filters['q'])) for “{{ $filters['q'] }}”@endif</span>
                        <span class="text-muted small">{{ \App\Domain\Site\Corpus::currentRun() ? 'Themes from clustering '.\App\Domain\Site\Corpus::currentRun()->public_id : 'Sample themes until the URDC confirms a clustering run' }}</span>
                    </div>
                    @forelse ($results as $r)
                        <x-record-row :r="$r" :href="$show($r)" />
                    @empty
                        <p class="text-muted py-5">No studies match. Searches with no results are logged so the URDC can see gaps in the collection (Sprint 5).</p>
                    @endforelse
                    <div class="py-3">{{ $results->links() }}</div>
                </section>
            </div>
        </form>
    </div>
</x-dynamic-component>
