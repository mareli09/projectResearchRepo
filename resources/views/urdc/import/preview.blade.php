<x-layouts.app title="Check import">
    <div class="eyebrow"><a href="{{ route('urdc.import') }}">Bulk import</a> · dry run</div>
    <h1 class="mb-1">Check: {{ $name }}</h1>
    <p class="text-muted mb-4">Nothing has been imported yet.{{ $hasZip ? '' : ' No ZIP was uploaded, so records will have incomplete files.' }}</p>

    <div class="grid gap-3 sm:grid-cols-3 mb-4">
        <x-kpi-card label="Ready to import" :value="$counts['ready']" />
        <x-kpi-card label="Rows with errors" :value="$counts['error']" hint="Skipped" />
        <x-kpi-card label="Duplicates" :value="$counts['duplicate']" hint="Skipped: same title or identical file" />
    </div>

    <div class="panel p-0 overflow-x-auto mb-4">
        <table class="w-full text-left">
            <thead class="text-muted text-[13px] bg-surface-2">
                <tr><th class="py-2 px-3">Row</th><th class="py-2 px-3">Title</th><th class="py-2 px-3">Result</th><th class="py-2 px-3">Details</th></tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr class="border-t border-line align-top">
                        <td class="py-2 px-3 mono">{{ $row->line }}</td>
                        <td class="py-2 px-3">{{ $row->data['title'] ?: '(no title)' }}</td>
                        <td class="py-2 px-3">
                            @switch($row->status())
                                @case('ready')<x-pill variant="ok">Ready</x-pill>@break
                                @case('duplicate')<x-pill variant="warn">Duplicate</x-pill>@break
                                @default<x-pill variant="bad">Error</x-pill>
                            @endswitch
                        </td>
                        <td class="py-2 px-3 text-[13px]">
                            @foreach ($row->errors as $m)<div class="text-bad">{{ $m }}</div>@endforeach
                            @foreach ($row->duplicates as $m)<div class="text-warn">{{ $m }}</div>@endforeach
                            @foreach ($row->warnings as $m)<div class="text-muted">{{ $m }}</div>@endforeach
                            @if ($row->isReady() && $row->files)<div class="text-muted">{{ count($row->files) }} file{{ count($row->files) === 1 ? '' : 's' }} attached</div>@endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($counts['ready'] > 0)
        <form method="POST" action="{{ route('urdc.import.store', $token) }}" class="panel flex flex-col gap-3">
            @csrf
            <label class="flex items-center gap-2 min-h-11">
                <input type="checkbox" name="publish_complete" value="1" checked class="size-5">
                Publish records that have all required files. Others are saved as drafts.
            </label>
            <div class="flex flex-wrap gap-2">
                <x-button type="submit" variant="primary">Import {{ $counts['ready'] }} {{ $counts['ready'] === 1 ? 'row' : 'rows' }}</x-button>
                <x-button :href="route('urdc.import')">Cancel</x-button>
            </div>
        </form>
    @else
        <x-callout variant="bad">No rows are ready. Fix the spreadsheet and upload it again.</x-callout>
        <x-button :href="route('urdc.import')" class="mt-3">Upload again</x-button>
    @endif
</x-layouts.app>
