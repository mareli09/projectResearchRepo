{{-- [Not in manuscript] "Landing page content" (CLAUDE.md §8): edit → draft → preview → publish. Plain text only. --}}
@php
    use App\Domain\Site\SiteContent;
    use Illuminate\Support\Arr;
    $in = 'w-full min-h-11 rounded-md border border-line bg-surface px-3';
    $ta = 'w-full rounded-md border border-line bg-surface px-3 py-2';
    $tabs = \App\Http\Controllers\Design\SiteContentController::TABS;
    $key = fn (string $path) => str_replace('.', '__', $path);
@endphp
<x-layouts.app title="Landing page content">
    <x-page-head eyebrow="Website · content management" title="Landing page content"
        lead="Edit the public home page. Saved changes stay in a draft until you publish them, and every publish is written to the activity log.">
        <x-slot:actions><x-button :href="route('home')">View live page</x-button></x-slot:actions>
    </x-page-head>

    <div class="cms-bar">
        @if ($draft)
            <span class="st"><span class="dot-saved" style="background: var(--warn)"></span>Draft saved {{ $draft->updated_at->diffForHumans() }}. Visitors still see the published page.</span>
            <x-button :href="route('home', ['preview' => 1])" target="_blank" rel="noopener">Preview</x-button>
            <form method="POST" action="{{ route('site.content.discard') }}" onsubmit="return confirm('Discard all draft changes?')">@csrf<x-button type="submit">Discard changes</x-button></form>
            <form method="POST" action="{{ route('site.content.publish') }}">@csrf<x-button type="submit" variant="primary">Publish</x-button></form>
        @else
            <span class="st"><span class="dot-saved"></span>Everything is published.</span>
        @endif
    </div>

    <div class="cms">
        <aside class="panel cms-nav" aria-label="Sections">
            <div class="eyebrow px-1.5 py-1">Page settings</div>
            @foreach ($tabs as $k => $l)
                <div class="it" aria-current="{{ $tab === $k ? 'true' : 'false' }}"><a href="{{ route('site.content', ['tab' => $k]) }}">{{ $l }}</a></div>
            @endforeach
            <div class="eyebrow px-1.5 pt-2.5 pb-1">Sections, in page order</div>
            <form method="POST" action="{{ route('site.content.layout') }}">
                @csrf <input type="hidden" name="tab" value="{{ $tab }}">
                @foreach ($c['order'] as $i => $k)
                    @php $shown = $c['show'][$k] ?? true; @endphp
                    <div class="it {{ $shown ? '' : 'off' }}" aria-current="{{ $tab === $k ? 'true' : 'false' }}">
                        <button type="submit" name="toggle" value="{{ $k }}" class="btn btn-sm" aria-pressed="{{ $shown ? 'true' : 'false' }}" aria-label="{{ $shown ? 'Hide' : 'Show' }} {{ SiteContent::SECTIONS[$k] }}" title="{{ $shown ? 'Shown. Click to hide' : 'Hidden. Click to show' }}">{{ $shown ? '●' : '○' }}</button>
                        <a href="{{ route('site.content', ['tab' => $k]) }}">{{ SiteContent::SECTIONS[$k] }}</a>
                        <button type="submit" name="move" value="{{ $k }}:up" class="btn btn-sm" aria-label="Move {{ SiteContent::SECTIONS[$k] }} up" @disabled($i === 0)>↑</button>
                        <button type="submit" name="move" value="{{ $k }}:down" class="btn btn-sm" aria-label="Move {{ SiteContent::SECTIONS[$k] }} down" @disabled($i === count($c['order']) - 1)>↓</button>
                    </div>
                @endforeach
            </form>
            <form method="POST" action="{{ route('site.content.defaults') }}" class="pt-3 px-1.5 mt-2.5 border-t border-line" onsubmit="return confirm('Put the default content in the draft? Nothing goes live until you publish.')">
                @csrf <x-button type="submit" size="sm">Load default content</x-button>
            </form>
        </aside>

        <div class="stack">
            <x-panel :title="$tabs[$tab] ?? SiteContent::SECTIONS[$tab]">
                @if (isset(SiteContent::SECTIONS[$tab]))
                    <x-slot:actions><span class="small text-muted">{{ ($c['show'][$tab] ?? true) ? 'Shown on the page' : 'Hidden from the page' }}</span></x-slot:actions>
                @endif

                <form method="POST" action="{{ route('site.content.save', $tab) }}" class="flex flex-col gap-3" novalidate>
                    @csrf
                    @if ($tab === 'brand')
                        <div x-data="{ color: @js(old('theme_color', $c['theme']['color'])) }">
                            <div class="eyebrow mb-2">Default theme color</div>
                            <div class="swatches max-w-sm">@foreach ($swatches as $n => $col)<button type="button" class="sw" x-on:click="color = '{{ $col }}'" :aria-pressed="color.toLowerCase() === '{{ strtolower($col) }}' ? 'true' : 'false'"><i style="background: {{ $col }}"></i>{{ $n }}</button>@endforeach</div>
                            <label class="flex items-center gap-2 mt-2 small">Custom color <input type="color" name="theme_color" x-model="color" style="width: 44px; height: 32px"> <span class="mono" x-text="color"></span></label>
                            <x-form-error name="theme_color" />
                        </div>
                    @endif

                    @foreach ($schema['bools'] ?? [] as $path => $label)
                        <label class="flex items-center gap-2 min-h-11"><input type="checkbox" class="size-5" name="b[{{ $key($path) }}]" value="1" @checked(Arr::get($c, $path))> {{ $label }}</label>
                    @endforeach

                    @foreach ($schema['fields'] ?? [] as $path => $def)
                        @php $id = 'cf-'.$key($path); $name = 'f['.$key($path).']'; $value = old('f.'.$key($path), Arr::get($c, $path)); @endphp
                        <div class="flex flex-col gap-1"><label for="{{ $id }}" class="font-medium">{{ $def[0] }}</label>
                            @if ($def[2] ?? false)<textarea id="{{ $id }}" name="{{ $name }}" rows="4" maxlength="{{ $def[1] }}" class="{{ $ta }}">{{ $value }}</textarea>
                            @else<input id="{{ $id }}" name="{{ $name }}" maxlength="{{ $def[1] }}" class="{{ $in }}" value="{{ $value }}">@endif
                            <x-form-error :name="'f.'.$key($path)" /></div>
                    @endforeach

                    @if ($tab === 'featured')
                        <div class="form-grid">
                            <div class="flex flex-col gap-1"><label for="cf-fm" class="font-medium">Which studies</label>
                                <select id="cf-fm" name="mode" class="{{ $in }}">@foreach (['manual' => 'Chosen by the URDC', 'viewed' => 'Most viewed', 'latest' => 'Most recent'] as $v => $l)<option value="{{ $v }}" @selected($c['featured']['mode'] === $v)>{{ $l }}</option>@endforeach</select></div>
                            <div class="flex flex-col gap-1"><label for="cf-fc" class="font-medium">How many</label>
                                <select id="cf-fc" name="count" class="{{ $in }}">@foreach ([3, 6, 9] as $n)<option value="{{ $n }}" @selected((int) $c['featured']['count'] === $n)>{{ $n }}</option>@endforeach</select></div>
                        </div>
                        <h3 class="font-semibold">Chosen studies <span class="small text-muted">(used when "Chosen by the URDC" is selected)</span></h3>
                        <div class="cms-pick">@forelse ($featured as $id => $t)<div><span><span class="mono">{{ $id }}</span> {{ $t }}</span><x-button type="submit" name="remove_id" :value="$id" size="sm" variant="bad">Remove</x-button></div>@empty<p class="small text-muted">None chosen yet.</p>@endforelse</div>
                        <h3 class="font-semibold">Add a published study</h3>
                        <div class="cms-pick">@foreach ($candidates as $r)<div><span><span class="mono">{{ $r->public_id }}</span> {{ $r->title }} <span class="text-muted">· {{ number_format($r->views) }} views</span></span><x-button type="submit" name="add_id" :value="$r->public_id" size="sm">Add</x-button></div>@endforeach</div>
                        <x-form-error name="add_id" />
                    @endif

                    @if (isset($schema['list']))
                        @php [$path, $cols, $maxItems, $itemName] = $schema['list']; $items = Arr::get($c, $path, []); @endphp
                        @foreach ($items as $i => $item)
                            <fieldset class="cms-item">
                                <legend class="sr-only">{{ $itemName }} {{ $i + 1 }}</legend>
                                <div class="ph"><b class="small">{{ $itemName }} {{ $i + 1 }}</b>
                                    <span class="flex gap-1 items-center">
                                        <button type="submit" name="move" value="{{ $i }}:up" class="btn btn-sm" aria-label="Move {{ strtolower($itemName) }} {{ $i + 1 }} up" @disabled($i === 0)>↑</button>
                                        <button type="submit" name="move" value="{{ $i }}:down" class="btn btn-sm" aria-label="Move {{ strtolower($itemName) }} {{ $i + 1 }} down" @disabled($i === count($items) - 1)>↓</button>
                                        <label class="flex items-center gap-1 small"><input type="checkbox" name="items[{{ $i }}][_remove]" value="1"> Remove</label>
                                    </span></div>
                                @if ($tab === 'news')<label class="flex items-center gap-2 small"><input type="checkbox" name="items[{{ $i }}][on]" value="1" @checked($item['on'] ?? false)> Show this item</label>@endif
                                @foreach ($cols as $k => $def)
                                    <div class="flex flex-col gap-1"><label for="ci-{{ $i }}-{{ $k }}" class="font-medium small">{{ $def[0] }}</label>
                                        @if ($def[2] ?? false)<textarea id="ci-{{ $i }}-{{ $k }}" name="items[{{ $i }}][{{ $k }}]" rows="2" maxlength="{{ $def[1] }}" class="{{ $ta }}">{{ $item[$k] ?? '' }}</textarea>
                                        @else<input id="ci-{{ $i }}-{{ $k }}" name="items[{{ $i }}][{{ $k }}]" maxlength="{{ $def[1] }}" class="{{ $in }}" value="{{ $item[$k] ?? '' }}">@endif</div>
                                @endforeach
                            </fieldset>
                        @endforeach
                        @if ($items === [])<input type="hidden" name="items" value="">@endif
                        <x-form-error name="items" />
                        <div><x-button type="submit" name="add" value="1" size="sm" :disabled="count($items) >= $maxItems">+ Add {{ strtolower($itemName) }}</x-button></div>
                    @endif

                    @if ($tab === 'clusters')<x-callout class="small">Cluster names follow the manuscript (Research Locale). Which colleges belong to each cluster is an assumption until the URDC confirms it. Study counts are live.</x-callout>@endif
                    @if ($tab === 'sdg')<p class="small text-muted">The SDG list and study counts come from the repository.</p>@endif
                    @if ($tab === 'hero')<p class="small text-muted">The search box and the "Latest approved studies" card are filled from the repository automatically.</p>@endif

                    <div class="flex gap-2 pt-1"><x-button type="submit" variant="primary">Save to draft</x-button></div>
                </form>
            </x-panel>

            <x-panel title="Publishing history">
                <div class="tbl mt-2.5"><table>
                    <thead><tr><th>When</th><th>By</th><th>Changed</th></tr></thead>
                    <tbody>@forelse ($history as $h)<tr><td class="small whitespace-nowrap">{{ $h['t'] }}</td><td class="small">{{ $h['who'] }}</td><td class="small">{{ $h['what'] }}</td></tr>@empty<tr><td colspan="3" class="text-muted">Nothing published yet.</td></tr>@endforelse</tbody>
                </table></div>
            </x-panel>
        </div>
    </div>
</x-layouts.app>
