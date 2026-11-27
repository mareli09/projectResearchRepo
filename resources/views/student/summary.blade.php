<x-layouts.app title="Research summary">
    <x-page-head eyebrow="M7 · Summary for students" :title="$college.' research at a glance'" />
    <div class="g3 mb-4">@foreach ($kpis as [$l, $v])<x-kpi :label="$l" :value="$v" />@endforeach</div>
    <div class="g2">
        <x-panel :title="'Themes in '.$college"><x-chart :option="$themeChart" label="Themes in your college" :link="route('search', ['college' => $college]).'&theme={key}'" /></x-panel>
        <x-panel title="Emerging keywords"><x-chart :option="$kwChart" label="Emerging keywords" :link="route('search', ['college' => $college]).'&q={name}'" /></x-panel>
    </div>
</x-layouts.app>
