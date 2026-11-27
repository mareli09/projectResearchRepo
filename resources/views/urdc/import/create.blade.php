<x-layouts.app title="Bulk import">
    <div class="eyebrow">M2 · Repository</div>
    <h1 class="mb-4">Bulk import</h1>

    <div class="grid gap-4 lg:grid-cols-[1fr_1fr]">
        <x-panel title="1. Fill in the template">
            <p class="mb-3">For the Google Form submissions and older outputs. One row per study.</p>
            <div class="flex flex-wrap gap-2 mb-4">
                <x-button :href="route('urdc.import.template', 'xlsx')">Download XLSX template</x-button>
                <x-button :href="route('urdc.import.template', 'csv')">Download CSV template</x-button>
            </div>
            <details>
                <summary class="cursor-pointer min-h-11 flex items-center">Columns</summary>
                <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-[13px] mt-2">
                    @foreach ($columns as $col => $help)
                        <dt class="mono">{{ $col }}</dt><dd class="text-muted">{{ $help }}</dd>
                    @endforeach
                </dl>
            </details>
        </x-panel>

        <x-panel title="2. Upload and check">
            <form method="POST" action="{{ route('urdc.import.preview') }}" enctype="multipart/form-data" class="flex flex-col gap-3" novalidate>
                @csrf
                <div class="flex flex-col gap-1">
                    <label for="f-sheet" class="font-medium">Spreadsheet (.xlsx or .csv)</label>
                    <input id="f-sheet" type="file" name="sheet" accept=".xlsx,.csv" required>
                    <x-form-error name="sheet" />
                </div>
                <div class="flex flex-col gap-1">
                    <label for="f-zip" class="font-medium">Files (.zip, optional)</label>
                    <input id="f-zip" type="file" name="files_zip" accept=".zip" aria-describedby="f-zip-hint">
                    <p id="f-zip-hint" class="text-[13px] text-muted">One ZIP with the files named in the file columns. Without it, records are saved with incomplete files.</p>
                    <x-form-error name="files_zip" />
                </div>
                <x-button type="submit" variant="primary">Check file (dry run)</x-button>
                <p class="text-[13px] text-muted">The check writes nothing. You see every row's errors and duplicates before you import.</p>
            </form>
        </x-panel>
    </div>
</x-layouts.app>
