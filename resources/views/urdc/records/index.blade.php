@php use App\Domain\Repository\ResearchTypes; @endphp
<x-layouts.app title="Records and metadata">
    <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
        <div>
            <div class="eyebrow">M2 · Repository</div>
            <h1>Records and metadata</h1>
        </div>
        <x-button :href="route('urdc.import')">Bulk import</x-button>
    </div>

    @if (session('import_failed'))
        <x-callout variant="bad" class="mb-4">
            <b>Some rows could not be imported:</b>
            <ul class="list-disc pl-5">@foreach (session('import_failed') as $f)<li>{{ $f }}</li>@endforeach</ul>
        </x-callout>
    @endif

    {{-- Missing-data shortcuts --}}
    <div class="flex flex-wrap gap-2 mb-3" aria-label="Missing data">
        @foreach (['' => 'All records', 'keywords' => 'No keywords', 'sdg' => 'No SDG', 'files' => 'Incomplete files'] as $key => $label)
            @php $active = ($filters['missing'] ?? '') === $key; @endphp
            <a href="{{ route('urdc.records', array_filter(array_merge($filters, ['missing' => $key ?: null]))) }}"
               @if ($active) aria-current="true" @endif
               class="btn btn-sm {{ $active ? 'btn-primary' : '' }}">{{ $label }} <span class="mono">{{ number_format($counts[$key ?: 'all']) }}</span></a>
        @endforeach
    </div>

    <form method="GET" class="panel mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 items-end">
        <x-input name="q" type="search" label="Title, author or ID" :value="$filters['q'] ?? ''" />
        <div class="flex flex-col gap-1">
            <label for="f-college" class="font-medium">College</label>
            <select id="f-college" name="college" class="min-h-11 rounded-md border border-line bg-surface px-3">
                <option value="">All colleges</option>
                @foreach ($units as $u)<option @selected(($filters['college'] ?? '') === $u->code)>{{ $u->code }}</option>@endforeach
            </select>
        </div>
        <div class="flex flex-col gap-1">
            <label for="f-status" class="font-medium">Status</label>
            <select id="f-status" name="status" class="min-h-11 rounded-md border border-line bg-surface px-3">
                <option value="">Draft and published</option>
                @foreach (ResearchTypes::STATUSES as $k => $l)<option value="{{ $k }}" @selected(($filters['status'] ?? '') === $k)>{{ $l }}</option>@endforeach
            </select>
        </div>
        @if (! empty($filters['missing']))<input type="hidden" name="missing" value="{{ $filters['missing'] }}">@endif
        <x-button type="submit" variant="primary" class="justify-center">Search</x-button>
    </form>

    <div class="panel p-0 overflow-x-auto">
        <table class="w-full text-left">
            <thead class="text-muted text-[13px] bg-surface-2">
                <tr><th class="py-2 px-3">ID</th><th class="py-2 px-3">Title</th><th class="py-2 px-3">College</th><th class="py-2 px-3">Year</th><th class="py-2 px-3">Status</th><th class="py-2 px-3">Data</th></tr>
            </thead>
            <tbody>
                @forelse ($records as $r)
                    <tr class="border-t border-line align-top">
                        <td class="py-2 px-3 mono text-[13px] whitespace-nowrap">{{ $r->public_id }}</td>
                        <td class="py-2 px-3"><a href="{{ route('urdc.records.edit', $r) }}">{{ $r->title }}</a>
                            <div class="text-[13px] text-muted">{{ ResearchTypes::TYPES[$r->research_type] }}</div></td>
                        <td class="py-2 px-3">{{ $r->academicUnit->code }}</td>
                        <td class="py-2 px-3">{{ $r->year }}</td>
                        <td class="py-2 px-3">
                            @if ($r->trashed())<x-pill variant="bad">Withdrawn</x-pill>
                            @elseif ($r->status === 'published')<x-pill variant="ok">Published</x-pill>
                            @else<x-pill>Draft</x-pill>@endif
                        </td>
                        <td class="py-2 px-3">
                            <div class="flex flex-wrap gap-1">
                                @if ($r->keywords_count < config('tua.repository.keywords_min'))<x-pill variant="warn">{{ $r->keywords_count ? 'Few keywords' : 'No keywords' }}</x-pill>@endif
                                @if ($r->sdgs_count === 0)<x-pill variant="warn">No SDG</x-pill>@endif
                                @if (! $r->is_complete)<x-pill variant="warn">Incomplete files</x-pill>@endif
                                @if ($r->keywords_count >= config('tua.repository.keywords_min') && $r->sdgs_count && $r->is_complete)<x-pill variant="ok">Complete</x-pill>@endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-muted">No records match.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $records->links() }}</div>
</x-layouts.app>
