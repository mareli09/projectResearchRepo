{{-- One study in a result list. $r is a ResearchRecord (with authors, academicUnit, program, keywords) or a Corpus row. --}}
@props(['r', 'href'])
@php
    $isModel = $r instanceof \App\Models\ResearchRecord;
    $title = $isModel ? $r->title : $r['title'];
    $authors = $isModel ? $r->authors->pluck('name')->implode(', ') : null;
    $college = $isModel ? $r->academicUnit->code : $r['college'];
    $program = $isModel ? $r->program?->name : $r['program'];
    $year = $isModel ? $r->year : $r['year'];
    $type = \App\Domain\Repository\ResearchTypes::TYPES[$isModel ? $r->research_type : $r['type']];
    $abstract = $isModel ? $r->abstract : null;
    $keywords = $isModel ? $r->keywords->pluck('term')->all() : $r['keywords'];
@endphp
<div class="result">
    <a class="t" href="{{ $href }}">{{ $title }}</a>
    <div class="meta">
        @if ($authors)<span>{{ $authors }}</span>@endif
        <span>{{ $college }}{{ $program ? ' · '.$program : '' }}</span><span>{{ $year }}</span><span>{{ $type }}</span>
    </div>
    @if ($abstract)<p class="small text-muted">{{ \Illuminate\Support\Str::limit($abstract, 190) }}</p>@endif
    <div class="chips">@foreach ($keywords as $k)<span class="chip">{{ $k }}</span>@endforeach{{ $slot }}</div>
</div>
