{{-- Top 5 similarity matches. Scores are sample until TF-IDF screening runs (Sprint 4). --}}
@props(['matches', 'selectUrl' => null, 'selected' => null])
<div class="tbl">
    <table>
        <thead><tr><th>#</th><th>Similar study</th><th>Year</th><th style="min-width:160px">Score</th><th>Flag</th></tr></thead>
        <tbody>
            @foreach ($matches as $i => $m)
                <tr @class(['click' => $selectUrl])>
                    <td class="num">{{ $i + 1 }}</td>
                    <td>
                        @if ($selectUrl)
                            <a href="{{ str_replace('__i__', (string) $i, $selectUrl) }}" @if ($selected === $i) aria-current="true" class="font-semibold" @endif>{{ $m['record']['title'] }}</a>
                        @else
                            <a href="{{ route('studies.show', $m['record']['public_id']) }}">{{ $m['record']['title'] }}</a>
                        @endif
                        <div class="text-muted small">{{ $m['record']['college'] }} · {{ $m['record']['type_label'] }}</div>
                    </td>
                    <td class="num">{{ $m['record']['year'] }}</td>
                    <td><x-score-bar :score="$m['score']" /></td>
                    <td><x-flag-pill :score="$m['score']" /></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
