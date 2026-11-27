<x-layouts.app title="Supervised studies">
    <x-page-head eyebrow="M7 · Supervised studies" title="Studies you supervised" :lead="'Published studies with '.$name.' as adviser.'" />
    <div class="g3 mb-4">@foreach ($kpis as [$l, $v])<x-kpi :label="$l" :value="$v" />@endforeach</div>
    <div class="g2 mb-4">
        <x-panel title="Per year"><x-chart :option="$yearChart" label="Supervised studies per year" :link="route('search', ['adviser' => $name]).'&year={name}'" /></x-panel>
        <x-panel title="By theme"><x-chart :option="$themeChart" label="Supervised studies by theme" :link="route('search', ['adviser' => $name]).'&theme={key}'" /></x-panel>
    </div>
    <x-panel title="Most recent">
        @foreach ($recent as $r)<x-record-row :r="$r" :href="route('studies.show', $r->public_id)" />@endforeach
    </x-panel>
</x-layouts.app>
