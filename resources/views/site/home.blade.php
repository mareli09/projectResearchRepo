{{--
  Public landing page (CLAUDE.md §8). Layout and palette follow the developer's reference design;
  every text comes from the landing page content (site_contents), and studies, counts and SDGs are live.
--}}
@php
    use App\Domain\Repository\ResearchTypes;
    $site = $c['site'];
    $navItems = ['about' => 'About', 'featured' => 'Studies', 'clusters' => 'Clusters', 'how' => 'Submit', 'news' => 'News', 'contact' => 'Contact'];
    $links = collect($c['order'])->filter(fn ($k) => $c['show'][$k] && isset($navItems[$k]))->mapWithKeys(fn ($k) => [$k => $navItems[$k]]);
    $paras = fn (string $t) => array_values(array_filter(preg_split('/\n\s*\n/', $t) ?: []));
    $clusterColors = [1 => '#0f3d1d', 2 => '#3f8f86', 3 => '#b5862a', 4 => '#6a5fb0'];
    $donut = collect([1, 2, 3, 4])->map(fn ($n) => ['n' => $n, 'v' => (int) ($clusterCounts[$n] ?? 0)]);
    $total = max(1, $donut->sum('v'));
    $acc = 0;
    $stops = $donut->map(function ($d) use (&$acc, $total, $clusterColors) {
        $from = $acc / $total * 360;
        $acc += $d['v'];

        return $clusterColors[$d['n']].' '.round($from, 1).'deg '.round($acc / $total * 360, 1).'deg';
    })->implode(', ');
    $points = $c['about']['points'];
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="{{ $c['hero']['lead'] }}">
    <meta name="theme-color" content="#0d302b">
    <title>{{ $site['name'] }} · {{ $site['tagline'] }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="lp">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:m-2 lp-btn lp-btn-lime">Skip to content</a>
    @if ($preview ?? false)
        <div role="status" style="position: sticky; top: 0; z-index: 60; background: #b5862a; color: #fff; padding: 10px 16px; text-align: center; font-weight: 600">
            Preview of the draft. Visitors still see the published page. <a href="{{ route('site.content') }}" style="color: #fff; text-decoration: underline">Back to the editor</a>
        </div>
    @endif

    {{-- Top utility bar --}}
    <div class="lp-top">
        <div class="lp-wrap">
            <div class="group">
                <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 4h16v16H4z"/><path d="m4 6 8 7 8-7"/></svg>{{ $c['contact']['email'] }}</span>
                <span class="hide-sm"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s-7-6-7-11a7 7 0 0 1 14 0c0 5-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>{{ $c['contact']['address'] }}</span>
            </div>
            <span class="hide-sm"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>{{ $c['contact']['hours'] }}</span>
        </div>
    </div>

    {{-- Header --}}
    <header class="lp-head">
        <div class="lp-wrap">
            <a class="lp-logo" href="{{ route('home') }}"><span class="mark">TUA</span><span>{{ $site['name'] }}<small>{{ $site['tagline'] }}</small></span></a>
            <nav class="lp-nav" aria-label="Site">
                @foreach ($links as $k => $label)<a href="#lp-{{ $k }}">{{ $label }}</a>@endforeach
            </nav>
            <div class="lp-actions" @if ($links->isEmpty()) style="margin-left:auto" @endif>
                @auth
                    <a class="lp-signin" href="{{ route('dashboard') }}">My dashboard</a>
                @else
                    <a class="lp-signin" href="{{ route('login') }}">Sign in</a>
                @endauth
                <a class="lp-btn lp-btn-dark lp-btn-sm" href="{{ route('catalog') }}">{{ $c['hero']['cta1'] }}</a>
                <details class="lp-menu">
                    <summary aria-label="Menu"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg></summary>
                    <div class="panel-m">
                        @foreach ($links as $k => $label)<a href="#lp-{{ $k }}">{{ $label }}</a>@endforeach
                        @guest<a href="{{ route('login') }}">Sign in</a><a href="{{ route('register') }}">Create account</a>@endguest
                    </div>
                </details>
            </div>
        </div>
    </header>

    @if ($c['banner']['on'])
        <div class="lp-banner" role="note"><div class="lp-wrap">
            <span class="tag">{{ $c['banner']['tag'] }}</span><span>{{ $c['banner']['text'] }}</span>
            @if ($c['banner']['link'])<a href="#lp-how">{{ $c['banner']['link'] }} →</a>@endif
        </div></div>
    @endif

    <main id="main">
        {{-- Hero --}}
        <section class="lp-hero">
            <div class="lp-wrap">
                <div>
                    <span class="lp-eyebrow">{{ $c['hero']['eyebrow'] }}</span>
                    <h1 class="lp-h1">{{ $c['hero']['title'] }}</h1>
                    <p class="lp-lead">{{ $c['hero']['lead'] }}</p>
                    <form class="lp-search" method="GET" action="{{ route('catalog') }}" role="search">
                        <label for="lp-q" class="sr-only">Search the repository</label>
                        <input type="search" id="lp-q" name="q" placeholder="Search titles, abstracts and keywords">
                        <button class="lp-btn lp-btn-dark" type="submit">Search</button>
                    </form>
                    @if ($c['hero']['popular'])
                        <div class="lp-pop"><span>Popular:</span>
                            @foreach (array_filter(array_map('trim', explode(',', $c['hero']['popular']))) as $p)<a href="{{ route('catalog', ['q' => $p]) }}">{{ $p }}</a>@endforeach
                        </div>
                    @endif
                    <div class="cta">
                        <a class="lp-btn lp-btn-lime" href="{{ route('catalog') }}">{{ $c['hero']['cta1'] }} →</a>
                        <a class="lp-btn lp-btn-line" href="{{ route('login') }}">{{ $c['hero']['cta2'] }}</a>
                    </div>
                </div>

                <div class="lp-visual">
                    <div class="stage">@include('site.partials.art-hero')</div>
                    {{-- "Latest approved studies" card: the 4 newest published records (§8) --}}
                    <div class="lp-float lp-f3">
                        <div class="k">Latest approved studies</div>
                        @foreach ($latest as $r)
                            <div class="row"><span class="lp-yr">{{ $r->year }}</span>
                                <div><a href="{{ route('catalog.show', $r->public_id) }}">{{ \Illuminate\Support\Str::limit($r->title, 70) }}</a>
                                    <div class="lp-meta"><span>{{ $r->academicUnit->code }}</span><span>{{ ResearchTypes::TYPES[$r->research_type] }}</span></div></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="lp-float lp-f1">
                        <span class="ic" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M4 19V5a2 2 0 0 1 2-2h12v16H6a2 2 0 0 0-2 2z"/><path d="M8 7h6"/></svg></span>
                        <div><div class="k">Published studies</div><div class="v">{{ number_format($published) }}</div></div>
                    </div>
                    <div class="lp-float lp-f2" aria-label="Studies by research cluster">
                        <div class="k mb-2">Studies by cluster</div>
                        <div class="flex gap-3 items-center">
                            <div class="lp-donut" style="background: conic-gradient({{ $stops }})"><i>{{ count($c['clusters']['items']) }}</i></div>
                            <div class="lp-legend">
                                @foreach ($donut as $d)<span><span class="flex gap-1.5 items-center"><b style="background: {{ $clusterColors[$d['n']] }}"></b>Cluster {{ $d['n'] }}</span><strong>{{ number_format($d['v']) }}</strong></span>@endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        @foreach ($c['order'] as $k)
            @continue(! $c['show'][$k])
            @switch($k)
                @case('stats')
                    <section id="lp-stats" aria-label="Key figures"><div class="lp-wrap"><div class="lp-stats">
                        @foreach ($c['stats']['items'] as $s)<div class="lp-stat"><b>{{ $s['v'] }}</b><span>{{ $s['l'] }}</span></div>@endforeach
                    </div></div></section>
                    @break

                @case('about')
                    <section class="lp-sec" id="lp-about"><div class="lp-wrap">
                        <div class="lp-split-head">
                            <div><span class="lp-eyebrow">{{ $c['about']['eyebrow'] }}</span><h2 class="lp-h2">{{ $c['about']['title'] }}</h2></div>
                            <div>@foreach ($paras($c['about']['body']) as $p)<p class="mb-3">{{ $p }}</p>@endforeach</div>
                        </div>
                        <div class="lp-bento">
                            <div class="lp-tile art">
                                <div class="cap"><span class="lp-tag lime">Six colleges · one repository</span></div>
                                @include('site.partials.art-team')
                            </div>
                            @if (isset($points[0]))
                                <div class="lp-tile lime"><span class="ic">01</span><h3>{{ $points[0]['t'] }}</h3><p>{{ $points[0]['d'] }}</p>
                                    <a class="lp-more" href="{{ route('catalog') }}">Browse the catalog →</a></div>
                            @endif
                            <div class="lp-tile">
                                <div><div class="lp-num">{{ number_format($published) }}</div><p>published studies to search</p></div>
                                <div><div class="lp-num">{{ $colleges }}</div><p>colleges contributing research</p></div>
                            </div>
                            @if (isset($points[1]))
                                <div class="lp-tile dark span2"><span class="ic">02</span><h3>{{ $points[1]['t'] }}</h3><p>{{ $points[1]['d'] }}</p>
                                    <div class="foot"><a class="lp-more" href="#lp-how" style="color: var(--lp-lime)">How submission works →</a>
                                        <span class="flex gap-2"><a class="lp-round" href="#lp-how" aria-label="Go to How submission works">↘</a><a class="lp-round lime" href="{{ route('catalog') }}" aria-label="Open the catalog">↗</a></span></div></div>
                            @endif
                            @foreach (array_slice($points, 2) as $i => $p)
                                <div class="lp-tile"><span class="ic">0{{ $i + 3 }}</span><h3>{{ $p['t'] }}</h3><p>{{ $p['d'] }}</p></div>
                            @endforeach
                        </div>
                    </div></section>
                    @break

                @case('featured')
                    <section class="lp-sec lp-dark-sec lp-featured" id="lp-featured"><div class="lp-wrap">
                        <div class="lp-row-head">
                            <div><span class="lp-eyebrow">{{ $c['featured']['eyebrow'] }}</span><h2 class="lp-h2">{{ $c['featured']['title'] }}</h2><p class="lp-lead">{{ $c['featured']['intro'] }}</p></div>
                            <a class="lp-btn lp-btn-lime" href="{{ route('catalog') }}">View all studies →</a>
                        </div>
                        @if ($featured->isNotEmpty())
                            <div class="lp-cards">
                                @foreach ($featured as $r)
                                    <a class="lp-card" href="{{ route('catalog.show', $r->public_id) }}">
                                        <span class="ic" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M4 19V5a2 2 0 0 1 2-2h12v16H6a2 2 0 0 0-2 2z"/><path d="M8 7h6M8 11h6"/></svg></span>
                                        <div class="tags"><span class="lp-tag lime">{{ $r->academicUnit->code }}</span><span class="lp-tag">{{ $r->year }}</span><span class="lp-tag">{{ ResearchTypes::TYPES[$r->research_type] }}</span></div>
                                        <h3>{{ $r->title }}</h3>
                                        <p>{{ $r->abstract }}</p>
                                        <div class="lp-meta"><span>{{ $r->authors->take(2)->pluck('name')->implode(', ') }}{{ $r->authors->count() > 2 ? ' et al.' : '' }}</span><span>Adviser: {{ $r->adviser_name }}</span></div>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <div class="lp-empty">No studies are featured yet.</div>
                        @endif
                    </div></section>
                    @break

                @case('clusters')
                    @php
                        $pins = ['CAHS' => [24, 28], 'CASE' => [74, 22], 'IBAM' => [20, 70], 'CEIS' => [50, 48], 'CMT' => [80, 66], 'SLCN' => [44, 82]];
                    @endphp
                    <section class="lp-sec" id="lp-clusters"><div class="lp-wrap">
                        <div class="lp-row-head">
                            <div><span class="lp-eyebrow">{{ $c['clusters']['eyebrow'] }}</span><h2 class="lp-h2">{{ $c['clusters']['title'] }}</h2><p class="lp-lead">{{ $c['clusters']['intro'] }}</p></div>
                            <a class="lp-btn lp-btn-lime lp-btn-sm" href="{{ route('catalog') }}">View all studies</a>
                        </div>
                        <div class="lp-map" aria-label="Colleges and their research clusters">
                            @foreach ($units as $u)
                                @php [$x, $y] = $pins[$u->code] ?? [50, 50]; $n = $u->researchCluster?->number ?? 0; @endphp
                                <a class="lp-pin {{ $u->code === 'CEIS' ? 'hot' : '' }}" style="left: {{ $x }}%; top: {{ $y }}%" href="{{ route('catalog', ['college' => $u->code]) }}">
                                    <span class="av" style="background: {{ $clusterColors[$n] ?? '#0f3d1d' }}">{{ $u->code }}</span>
                                    <span><b>{{ $u->name }}</b><small>Cluster {{ $n }} · {{ number_format($collegeCounts[$u->code] ?? 0) }} studies{{ $u->code === 'CEIS' ? ' · pilot college' : '' }}</small></span>
                                </a>
                            @endforeach
                        </div>
                        <div class="lp-clusters">
                            @foreach ($c['clusters']['items'] as $i => $cl)
                                <div class="lp-clu" style="border-top: 4px solid {{ $clusterColors[$i + 1] ?? '#0f3d1d' }}">
                                    <span class="no">Cluster {{ $i + 1 }} · {{ $cl['c'] }}</span><h3>{{ $cl['n'] }}</h3><p>{{ $cl['d'] }}</p>
                                    @if ($clusterCounts[$i + 1] ?? 0)<span class="cnt">{{ number_format($clusterCounts[$i + 1]) }} <small class="text-[.8rem] font-medium" style="color: var(--lp-muted)">studies on record</small></span>@endif
                                </div>
                            @endforeach
                        </div>
                    </div></section>
                    @break

                @case('how')
                    @php $steps = $c['how']['steps']; @endphp
                    <section class="lp-sec lp-dark-sec" id="lp-how"><div class="lp-wrap">
                        <div class="lp-center mb-10"><span class="lp-eyebrow">{{ $c['how']['eyebrow'] }}</span><h2 class="lp-h2">{{ $c['how']['title'] }}</h2><p class="lp-lead mx-auto">{{ $c['how']['intro'] }}</p></div>
                        <div class="lp-how" data-lp-steps>
                            <div>
                                <div class="lp-stepcard">
                                    <div>
                                        @foreach ($steps as $i => $s)
                                            <div data-step-panel="{{ $i }}" @if ($i > 0) hidden @endif role="tabpanel" id="lp-step-{{ $i }}">
                                                <div class="n">{{ sprintf('%02d', $i + 1) }}.</div><h3>{{ $s['t'] }}</h3><p>{{ $s['d'] }}</p>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="lp-vtabs" role="tablist" aria-label="Submission steps">
                                        @foreach ($steps as $i => $s)
                                            <button type="button" role="tab" data-step="{{ $i }}" aria-controls="lp-step-{{ $i }}" aria-selected="{{ $i === 0 ? 'true' : 'false' }}">{{ sprintf('%02d', $i + 1) }} {{ $s['t'] }}</button>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="lp-process">
                                    <span class="font-semibold">Our process</span>
                                    <span class="ctl">
                                        <button type="button" data-step-prev aria-label="Previous step">←</button>
                                        <span class="font-semibold"><span data-step-now>01</span>/{{ sprintf('%02d', count($steps)) }}</span>
                                        <button type="button" data-step-next aria-label="Next step">→</button>
                                    </span>
                                </div>
                            </div>
                            <div class="lp-files">
                                <span class="lp-tag lime" style="width: fit-content">Required files</span>
                                <h3 class="text-xl font-semibold">Five files, checked on every submission</h3>
                                <ul>
                                    @foreach (config('tua.repository.file_slots') as $slot)
                                        <li><span class="chk" aria-hidden="true">✓</span><span>{{ $slot['label'] }} <span style="color: var(--lp-on-dark-muted)">· {{ strtoupper(implode(' / ', $slot['mimes'])) }}</span></span></li>
                                    @endforeach
                                </ul>
                                @if ($c['how']['note'])<p style="color: var(--lp-on-dark-muted); font-size: .9rem">{{ $c['how']['note'] }}</p>@endif
                                <a class="lp-btn lp-btn-lime" style="width: fit-content" href="{{ route('login') }}">{{ $c['hero']['cta2'] }} →</a>
                            </div>
                        </div>
                    </div></section>
                    @break

                @case('sdg')
                    <section class="lp-sec" id="lp-sdg"><div class="lp-wrap">
                        <div class="lp-center mb-8"><span class="lp-eyebrow">{{ $c['sdg']['eyebrow'] }}</span><h2 class="lp-h2">{{ $c['sdg']['title'] }}</h2><p class="lp-lead mx-auto">{{ $c['sdg']['intro'] }}</p></div>
                        <div class="lp-sdg justify-center">
                            @foreach ($sdgs as $s)
                                @if ($sdgCounts[$s->id] ?? 0)
                                    <a href="{{ route('catalog', ['sdg' => $s->id]) }}"><b>{{ $s->id }}</b><span>{{ $s->name }}</span><em>{{ number_format($sdgCounts[$s->id]) }}</em></a>
                                @endif
                            @endforeach
                        </div>
                    </div></section>
                    @break

                @case('news')
                    @php $items = array_values(array_filter($c['news']['items'], fn ($n) => $n['on'])); @endphp
                    <section class="lp-sec" id="lp-news" style="background: #fff"><div class="lp-wrap">
                        <div class="lp-center mb-10"><span class="lp-eyebrow">{{ $c['news']['eyebrow'] }}</span><h2 class="lp-h2">{{ $c['news']['title'] }}</h2></div>
                        @if ($items)
                            <div class="lp-news">
                                @foreach ($items as $n)
                                    <article><span class="quote" aria-hidden="true">“</span><h3>{{ $n['t'] }}</h3><p>{{ $n['b'] }}</p>
                                        <div class="by"><span class="lp-chip">{{ $n['tag'] }}</span><span>{{ $n['d'] }}</span></div></article>
                                @endforeach
                            </div>
                        @else
                            <div class="lp-empty">No announcements right now.</div>
                        @endif
                    </div></section>
                    @break

                @case('faq')
                    <section class="lp-sec lp-dark-sec" id="lp-faq"><div class="lp-wrap">
                        <div class="lp-center mb-10"><span class="lp-eyebrow">{{ $c['faq']['eyebrow'] }}</span><h2 class="lp-h2">{{ $c['faq']['title'] }}</h2></div>
                        <div class="lp-faq">
                            <div class="art" aria-hidden="true">@include('site.partials.art-team')</div>
                            <div class="lp-acc">
                                @foreach ($c['faq']['items'] as $i => $f)
                                    <details name="lp-faq" @if ($i === 0) open @endif>
                                        <summary>{{ $f['q'] }}<span class="ico" aria-hidden="true">+</span></summary>
                                        <p>{{ $f['a'] }}</p>
                                    </details>
                                @endforeach
                            </div>
                        </div>
                    </div></section>
                    @break

                @case('contact')
                    <section class="lp-sec" id="lp-contact"><div class="lp-wrap">
                        <div class="mb-8"><span class="lp-eyebrow">{{ $c['contact']['eyebrow'] }}</span><h2 class="lp-h2">{{ $c['contact']['title'] }}</h2></div>
                        <div class="lp-contact">
                            <div><span class="k">Visit</span><b>{{ $c['contact']['office'] }}</b><span style="color: var(--lp-muted)">{{ $c['contact']['address'] }}</span></div>
                            <div><span class="k">Email and phone</span><b>{{ $c['contact']['email'] }}</b><span style="color: var(--lp-muted)">{{ $c['contact']['phone'] }}</span></div>
                            <div><span class="k">Office hours</span><b>{{ $c['contact']['hours'] }}</b></div>
                        </div>
                        <div class="lp-help"><b>Account help.</b> {{ $c['contact']['help'] }} <a class="font-semibold underline" href="{{ route('password.request') }}">Recover your account →</a></div>
                        <div class="lp-cta">
                            <h2>{{ $c['how']['intro'] }}</h2>
                            <a class="lp-btn lp-btn-lime" href="{{ route('login') }}">{{ $c['hero']['cta2'] }} →</a>
                        </div>
                    </div></section>
                    @break
            @endswitch
        @endforeach
    </main>

    <footer class="lp-foot">
        <div class="lp-wrap">
            <div class="cols">
                <div>
                    <a class="lp-logo" href="{{ route('home') }}"><span class="mark">TUA</span><span>{{ $site['name'] }}<small>{{ $site['tagline'] }}</small></span></a>
                    <p class="mt-4" style="color: var(--lp-on-dark-muted); font-size: .92rem; line-height: 1.6">{{ \Illuminate\Support\Str::limit($c['hero']['lead'], 160) }}</p>
                </div>
                <div><h4>Explore</h4><ul>@foreach ($links as $k => $label)<li><a href="#lp-{{ $k }}">{{ $label }}</a></li>@endforeach</ul></div>
                <div><h4>Account</h4><ul>
                    <li><a href="{{ route('login') }}">Sign in</a></li><li><a href="{{ route('register') }}">Create account</a></li>
                    <li><a href="{{ route('password.request') }}">Forgot password</a></li><li><a href="{{ route('catalog') }}">Public catalog</a></li>
                </ul></div>
                <div><h4>Contact</h4><ul>
                    <li>{{ $c['contact']['office'] }}</li><li>{{ $c['contact']['email'] }}</li><li>{{ $c['contact']['hours'] }}</li>
                </ul></div>
            </div>
            <div class="bar"><span>{{ $c['footer']['text'] }}</span><span><a href="{{ route('register') }}#privacy">Privacy Notice (RA 10173)</a> · <a href="{{ route('login') }}">Sign in</a></span></div>
        </div>
    </footer>
</body>
</html>
