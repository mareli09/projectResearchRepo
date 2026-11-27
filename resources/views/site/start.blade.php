{{-- Temporary Sprint 0 start page. Replaced by the landing page in Sprint 8. --}}
<x-layouts.app title="Setup">
    <div class="eyebrow">Sprint 0 · setup check</div>
    <h1 class="mb-4">TUA Research Repository</h1>

    <div class="grid gap-4 md:grid-cols-4 mb-4">
        <x-kpi-card label="Colleges" :value="$units" />
        <x-kpi-card label="Programs" :value="$programs" />
        <x-kpi-card label="SDGs" :value="$sdgs" />
        <x-kpi-card label="Roles" :value="$roles" />
    </div>

    <x-panel title="Design system">
        <div class="flex flex-wrap gap-2 mb-3">
            <x-button>Default</x-button>
            <x-button variant="primary">Approve and publish</x-button>
            <x-button variant="ok">Endorse</x-button>
            <x-button variant="bad">Return with reason</x-button>
            <x-button size="sm">Small</x-button>
        </div>
        <div class="flex flex-wrap gap-2 mb-3">
            <x-pill variant="info">Submitted</x-pill>
            <x-pill variant="gold">Endorsed by adviser</x-pill>
            <x-pill variant="ok">Original</x-pill>
            <x-pill variant="warn">Similar, needs review</x-pill>
            <x-pill variant="bad">Probable duplicate</x-pill>
            <span class="chip">tf-idf</span>
        </div>
        <x-callout class="mb-3">No submission is rejected automatically.</x-callout>
        <x-llm-suggestion :fallback="true">
            <span class="chip">cosine similarity</span> <span class="chip">title screening</span>
        </x-llm-suggestion>
    </x-panel>
</x-layouts.app>
