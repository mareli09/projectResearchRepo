{{-- Similarity screening summary for a presented submission ($sub). --}}
@if ($sub['screened'] ?? false)
    @php $flag = $sub['score'] >= config('tua.screening.duplicate_threshold') ? 'bad' : ($sub['score'] >= config('tua.screening.review_threshold') ? 'gold' : 'ok'); @endphp
    <x-callout :variant="$flag">Similarity screening: highest match <b class="mono">{{ number_format($sub['score'], 2) }}</b> · <x-flag-pill :score="$sub['score']" />. {{ $note ?? 'The coordinator decides. Nothing is rejected automatically.' }}</x-callout>
@else
    <x-callout>Similarity screening is queued. The TF-IDF screening service is connected in Sprint 4; until then the coordinator reviews without a score.</x-callout>
@endif
