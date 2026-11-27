{{-- [Not in manuscript] "Landing page content" (CLAUDE.md §8): draft → preview → publish, plain text only. --}}
@php
    use App\Domain\Site\SiteContent;
    $in = 'w-full min-h-11 rounded-md border border-line bg-surface px-3';
    $ta = 'w-full rounded-md border border-line bg-surface px-3 py-2';
    $tabs = \App\Http\Controllers\Design\SiteContentController::TABS;
    $field = fn (string $id, string $label, ?string $value, bool $area = false) => ['id' => $id, 'label' => $label, 'value' => (string) $value, 'area' => $area];
    $intro = fn (string $k) => array_filter([
        $field("$k-eyebrow", 'Small heading above the title', $c[$k]['eyebrow'] ?? null),
        $field("$k-title", 'Section title', $c[$k]['title'] ?? null),
        isset($c[$k]['intro']) ? $field("$k-intro", 'Introduction', $c[$k]['intro'], true) : null,
    ]);
@endphp
<x-layouts.app title="Landing page content">
    <x-page-head eyebrow="Website · content management" title="Landing page content"
        lead="Edit the public home page. Changes are kept as a draft until you publish them, and every publish is written to the activity log.">
        <x-slot:actions><x-button :href="route('home')">View live page</x-button></x-slot:actions>
    </x-page-head>
    <x-preview-note sprint="8" what="Editing, Preview, Discard and Publish" />

    <div class="cms-bar"><span class="st"><span class="dot-saved"></span>Everything is published.</span>
        <x-button preview="Sprint 8">Preview</x-button><x-button preview="Sprint 8">Discard changes</x-button><x-button variant="primary" preview="Sprint 8">Publish</x-button></div>

    <div class="cms">
        <aside class="panel cms-nav" aria-label="Sections">
            <div class="eyebrow px-1.5 py-1">Page settings</div>
            @foreach ($tabs as $k => $l)
                <div class="it" aria-current="{{ $tab === $k ? 'true' : 'false' }}"><a href="{{ route('site.content', ['tab' => $k]) }}">{{ $l }}</a></div>
            @endforeach
            <div class="eyebrow px-1.5 pt-2.5 pb-1">Sections, in page order</div>
            @foreach ($c['order'] as $i => $k)
                <div class="it {{ $c['show'][$k] ? '' : 'off' }}" aria-current="{{ $tab === $k ? 'true' : 'false' }}">
                    <input type="checkbox" aria-label="Show {{ SiteContent::SECTIONS[$k] }}" @checked($c['show'][$k]) data-preview="Sprint 8" data-preview-what="Show or hide a section">
                    <a href="{{ route('site.content', ['tab' => $k]) }}">{{ SiteContent::SECTIONS[$k] }}</a>
                    <x-button size="sm" preview="Sprint 8" aria-label="Move up" :disabled="$i === 0">↑</x-button>
                    <x-button size="sm" preview="Sprint 8" aria-label="Move down" :disabled="$i === count($c['order']) - 1">↓</x-button>
                </div>
            @endforeach
            <div class="pt-3 px-1.5 mt-2.5 border-t border-line"><x-button size="sm" preview="Sprint 8">Load default content</x-button></div>
        </aside>

        <div class="stack">
            <x-panel :title="$tabs[$tab] ?? SiteContent::SECTIONS[$tab]">
                @if (isset(SiteContent::SECTIONS[$tab]))
                    <x-slot:actions><label class="flex items-center gap-2 small"><input type="checkbox" @checked($c['show'][$tab])> Show on the page</label></x-slot:actions>
                @endif
                @php
                    $fields = match ($tab) {
                        'brand' => [$field('site-name', 'Site name', $c['site']['name']), $field('site-tagline', 'Tagline under the name', $c['site']['tagline']), $field('footer', 'Footer text', $c['footer']['text'])],
                        'banner' => [$field('banner-tag', 'Label', $c['banner']['tag']), $field('banner-link', 'Link text (jumps to How submission works)', $c['banner']['link']), $field('banner-text', 'Message', $c['banner']['text'], true)],
                        'hero' => [$field('hero-eyebrow', 'Small heading', $c['hero']['eyebrow']), $field('hero-title', 'Headline', $c['hero']['title']), $field('hero-lead', 'Introduction', $c['hero']['lead'], true),
                            $field('hero-cta1', 'Main button (opens the catalog)', $c['hero']['cta1']), $field('hero-cta2', 'Second button (opens sign in)', $c['hero']['cta2']), $field('hero-popular', 'Popular searches (comma separated)', $c['hero']['popular'])],
                        'about' => [...$intro('about'), $field('about-body', 'Text (leave a blank line between paragraphs)', $c['about']['body'], true)],
                        'contact' => [$field('contact-office', 'Office', $c['contact']['office']), $field('contact-address', 'Address', $c['contact']['address']), $field('contact-email', 'Email', $c['contact']['email']),
                            $field('contact-phone', 'Phone', $c['contact']['phone']), $field('contact-hours', 'Office hours', $c['contact']['hours']), $field('contact-help', 'Account help (shown under Contact)', $c['contact']['help'], true)],
                        'stats', 'news', 'faq' => [],
                        default => $intro($tab),
                    };
                    $lists = match ($tab) {
                        'stats' => ['Figure', $c['stats']['items'], ['v' => 'Number', 'l' => 'Label']],
                        'about' => ['Feature card', $c['about']['points'], ['t' => 'Title', 'd' => 'Description']],
                        'clusters' => ['Cluster', $c['clusters']['items'], ['n' => 'Cluster name', 'c' => 'Colleges', 'd' => 'Description']],
                        'how' => ['Step', $c['how']['steps'], ['t' => 'Step', 'd' => 'Description']],
                        'news' => ['Item', $c['news']['items'], ['d' => 'Date', 'tag' => 'Label', 't' => 'Headline', 'b' => 'Text']],
                        'faq' => ['Question', $c['faq']['items'], ['q' => 'Question', 'a' => 'Answer']],
                        default => null,
                    };
                @endphp

                <div class="flex flex-col gap-3">
                    @if ($tab === 'brand')
                        <div><div class="eyebrow mb-2">Default theme color</div>
                            <div class="swatches max-w-sm">@foreach ($swatches as $n => $col)<button type="button" class="sw" aria-pressed="{{ strtolower($c['theme']['color']) === strtolower($col) ? 'true' : 'false' }}" data-preview="Sprint 8" data-preview-what="Set the default theme color"><i style="background: {{ $col }}"></i>{{ $n }}</button>@endforeach</div></div>
                        <label class="flex items-center gap-2 min-h-11"><input type="checkbox" class="size-5" @checked($c['theme']['allowUser'])> Let users pick their own theme color</label>
                    @endif
                    @if ($tab === 'banner')<label class="flex items-center gap-2 min-h-11"><input type="checkbox" class="size-5" @checked($c['banner']['on'])> Show the announcement bar</label>@endif

                    @foreach ($fields as $f)
                        <div class="flex flex-col gap-1"><label for="cf-{{ $f['id'] }}" class="font-medium">{{ $f['label'] }}</label>
                            @if ($f['area'])<textarea id="cf-{{ $f['id'] }}" rows="4" class="{{ $ta }}">{{ $f['value'] }}</textarea>
                            @else<input id="cf-{{ $f['id'] }}" class="{{ $in }}" value="{{ $f['value'] }}">@endif</div>
                    @endforeach

                    @if ($tab === 'featured')
                        <div class="form-grid">
                            <div class="flex flex-col gap-1"><label for="cf-fm" class="font-medium">Which studies</label>
                                <select id="cf-fm" class="{{ $in }}">@foreach (['manual' => 'Chosen by the URDC', 'viewed' => 'Most viewed', 'latest' => 'Most recent'] as $v => $l)<option @selected($c['featured']['mode'] === $v)>{{ $l }}</option>@endforeach</select></div>
                            <div class="flex flex-col gap-1"><label for="cf-fc" class="font-medium">How many</label>
                                <select id="cf-fc" class="{{ $in }}">@foreach ([3, 6, 9] as $n)<option @selected($c['featured']['count'] === $n)>{{ $n }}</option>@endforeach</select></div>
                        </div>
                        <h3 class="font-semibold">Chosen studies</h3>
                        <div class="cms-pick">@foreach ($featured as $id => $t)<div><span><span class="mono">{{ $id }}</span> {{ $t }}</span><x-button size="sm" variant="bad" preview="Sprint 8">Remove</x-button></div>@endforeach</div>
                        <h3 class="font-semibold">Add a published study</h3>
                        <div class="cms-pick">@foreach ($candidates as $r)<div><span><span class="mono">{{ $r->public_id }}</span> {{ $r->title }} <span class="text-muted">· {{ number_format($r->views) }} views</span></span><x-button size="sm" preview="Sprint 8">Add</x-button></div>@endforeach</div>
                    @endif
                    @if ($tab === 'clusters')<x-callout class="small">Cluster names follow the manuscript (Research Locale). Which colleges belong to each cluster is an assumption until the URDC confirms it.</x-callout>@endif
                    @if ($tab === 'sdg')<p class="small text-muted">The SDG list and study counts come from the repository.</p>@endif
                    @if ($tab === 'hero')<p class="small text-muted">The search box and the "Latest approved studies" card are filled from the repository automatically.</p>@endif

                    @if ($lists)
                        @php [$itemName, $items, $cols] = $lists; @endphp
                        @foreach ($items as $i => $item)
                            <div class="cms-item">
                                <div class="ph"><b class="small">{{ $itemName }} {{ $i + 1 }}</b>
                                    <span class="flex gap-1"><x-button size="sm" preview="Sprint 8" aria-label="Move up">↑</x-button><x-button size="sm" preview="Sprint 8" aria-label="Move down">↓</x-button><x-button size="sm" variant="bad" preview="Sprint 8">Remove</x-button></span></div>
                                @foreach ($cols as $key => $label)
                                    <div class="flex flex-col gap-1"><label for="ci-{{ $i }}-{{ $key }}" class="font-medium small">{{ $label }}</label>
                                        @if (in_array($key, ['d', 'b', 'a'], true) && $tab !== 'news' || ($tab === 'news' && $key === 'b'))<textarea id="ci-{{ $i }}-{{ $key }}" rows="2" class="{{ $ta }}">{{ $item[$key] ?? '' }}</textarea>
                                        @else<input id="ci-{{ $i }}-{{ $key }}" class="{{ $in }}" value="{{ $item[$key] ?? '' }}">@endif</div>
                                @endforeach
                            </div>
                        @endforeach
                        <div><x-button size="sm" preview="Sprint 8">+ Add {{ strtolower($itemName) }}</x-button></div>
                    @endif
                    @if ($tab === 'how')
                        <div class="flex flex-col gap-1"><label for="cf-how-note" class="font-medium">Note under the steps</label><textarea id="cf-how-note" rows="2" class="{{ $ta }}">{{ $c['how']['note'] }}</textarea></div>
                    @endif
                </div>
            </x-panel>

            <x-panel title="Publishing history">
                <div class="tbl mt-2.5"><table>
                    <thead><tr><th>When</th><th>By</th><th>Changed</th></tr></thead>
                    <tbody>@foreach ($history as $h)<tr><td class="small whitespace-nowrap">{{ $h['t'] }}</td><td class="small">{{ $h['who'] }}</td><td class="small">{{ $h['what'] }}</td></tr>@endforeach</tbody>
                </table></div>
            </x-panel>
        </div>
    </div>
</x-layouts.app>
