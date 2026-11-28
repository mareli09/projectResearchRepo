<?php

namespace App\Domain\Search;

use App\Domain\Repository\ResearchTypes;
use App\Domain\Site\Corpus;
use App\Models\Program;
use App\Models\ResearchRecord;
use App\Models\Sdg;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Catalog and search over published records (CLAUDE.md §7 M4), with facets:
 * college, program, year, research type, theme, SDG and adviser; sort by relevance, newest or most viewed.
 *
 * Text search uses the MySQL FULLTEXT index on (title, abstract):
 * - Matching: every word of 3 or more letters must appear, in any order, as a whole word or the
 *   start of one (BOOLEAN MODE, "+word*"). A plain substring match on the title, abstract and
 *   keywords is added, so short terms such as "AI" and exact phrases are still found.
 * - Relevance: the title contains the whole query first, then the FULLTEXT score
 *   (NATURAL LANGUAGE MODE), then the most viewed.
 *
 * Laravel's query builder issues the FULLTEXT SQL directly (whereFullText). Scout's database
 * engine was not used: it orders results by id, not by relevance.
 */
final class RecordSearch
{
    public const PER_PAGE = 20;

    /** InnoDB's default full-text stop words of 3 or more letters: a "+word" for one of them would match nothing. */
    private const FT_STOP = ['about', 'are', 'com', 'for', 'from', 'how', 'that', 'the', 'this', 'was', 'what', 'when', 'where', 'who', 'will', 'with', 'und', 'www'];

    /** @return array<string, mixed> validated filters */
    public static function filters(Request $request): array
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'college' => ['nullable', 'string', 'max:10'],
            'program' => ['nullable', 'integer', 'min:1'],
            'year' => ['nullable', 'integer'],
            'type' => ['nullable', 'string', 'max:40'],
            'theme' => ['nullable', 'integer', 'min:1', 'max:20'],
            'sdg' => ['nullable', 'integer', 'min:1', 'max:17'],
            'adviser' => ['nullable', 'string', 'max:190'],
            'sort' => ['nullable', 'in:relevance,newest,views'],
        ]);
        if (! empty($f['type'])) {
            $f['type'] = ResearchTypes::typeKey($f['type']);
        }
        // A program from another college than the one chosen is dropped, so the two filters never contradict.
        if (! empty($f['program']) && ! empty($f['college'])
            && ! Program::whereKey((int) $f['program'])->whereHas('academicUnit', fn (Builder $u) => $u->where('code', $f['college']))->exists()) {
            unset($f['program']);
        }

        return array_filter($f, fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @param  array<string, mixed>  $f
     * @return LengthAwarePaginator<int, ResearchRecord>
     */
    public static function run(array $f): LengthAwarePaginator
    {
        $q = trim((string) ($f['q'] ?? ''));
        $like = '%'.self::escapeLike($q).'%';
        $boolean = self::booleanQuery($q);

        $query = ResearchRecord::published()
            ->with(['authors', 'academicUnit:id,code', 'program:id,name', 'keywords:id,term'])
            ->when($q !== '', function (Builder $b) use ($like, $boolean): void {
                $b->where(function (Builder $w) use ($like, $boolean): void {
                    if ($boolean !== '') {
                        $w->whereFullText(['title', 'abstract'], $boolean, ['mode' => 'boolean']);
                    }
                    $w->orWhere('title', 'like', $like)
                        ->orWhere('abstract', 'like', $like)
                        ->orWhereHas('keywords', fn (Builder $k) => $k->where('term', 'like', $like));
                });
            })
            ->when($f['college'] ?? null, fn (Builder $b, $c) => $b->whereHas('academicUnit', fn (Builder $u) => $u->where('code', $c)))
            ->when($f['program'] ?? null, fn (Builder $b, $p) => $b->where('program_id', (int) $p))
            ->when($f['year'] ?? null, fn (Builder $b, $y) => $b->where('year', $y))
            ->when($f['type'] ?? null, fn (Builder $b, $t) => $b->where('research_type', $t))
            ->when($f['sdg'] ?? null, fn (Builder $b, $s) => $b->whereHas('sdgs', fn (Builder $x) => $x->where('sdgs.id', $s)))
            ->when($f['adviser'] ?? null, fn (Builder $b, $a) => $b->where('adviser_name', $a))
            ->when($f['theme'] ?? null, fn (Builder $b, $t) => $b->whereIn('id', Corpus::records()->where('theme', (int) $t)->pluck('id')));

        match ($f['sort'] ?? ($q !== '' ? 'relevance' : 'newest')) {
            'views' => $query->orderByDesc('views'),
            'relevance' => $q === '' ? $query->orderByDesc('year')->orderByDesc('views') : $query
                ->orderByRaw('CASE WHEN title LIKE ? THEN 0 ELSE 1 END', [$like])
                ->orderByRaw('MATCH (title, abstract) AGAINST (? IN NATURAL LANGUAGE MODE) DESC', [$q])
                ->orderByDesc('views'),
            default => $query->orderByDesc('year')->orderByDesc('views'),
        };

        return $query->paginate(self::PER_PAGE)->withQueryString();
    }

    /**
     * "+word*" for every word of 3 or more letters (at most 8), with no FULLTEXT operators left.
     * Empty when no such word remains: the substring match then does the work.
     */
    public static function booleanQuery(string $q): string
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($q), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_values(array_unique(array_filter($words, fn (string $w) => mb_strlen($w) >= 3 && ! in_array($w, self::FT_STOP, true))));

        return implode(' ', array_map(fn (string $w) => '+'.$w.'*', array_slice($words, 0, 8)));
    }

    private static function escapeLike(string $v): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $v);
    }

    /**
     * Facet options. With a college chosen, the program list shows only that college's programs.
     *
     * @return array<string, array<int|string, string>>
     */
    public static function facets(?string $college = null): array
    {
        $corpus = Corpus::records();

        return [
            'college' => array_combine($c = ['CAHS', 'CASE', 'IBAM', 'CEIS', 'CMT', 'SLCN'], $c),
            'program' => self::programOptions($college),
            'year' => $corpus->pluck('year')->unique()->sortDesc()->mapWithKeys(fn ($y) => [$y => (string) $y])->all(),
            'type' => ResearchTypes::TYPES,
            'theme' => Corpus::themeLabels(),
            'sdg' => Sdg::orderBy('id')->get()->mapWithKeys(fn ($s) => [$s->id => 'SDG '.$s->id.' · '.$s->name])->all(),
            'adviser' => $corpus->pluck('adviser')->filter()->unique()->sort()->mapWithKeys(fn ($a) => [$a => $a])->all(),
        ];
    }

    /**
     * Programs as id => "CEIS · BS Information Technology" (or the bare name within one college).
     *
     * @param  list<string>|null  $colleges  limit to these colleges (for a coordinator's cluster)
     * @return array<int, string>
     */
    public static function programOptions(?string $college = null, ?array $colleges = null): array
    {
        return Program::with('academicUnit:id,code')
            ->when($college, fn (Builder $b, $c) => $b->whereHas('academicUnit', fn (Builder $u) => $u->where('code', $c)))
            ->when($colleges !== null, fn (Builder $b) => $b->whereHas('academicUnit', fn (Builder $u) => $u->whereIn('code', $colleges ?? [])))
            ->get()
            ->sortBy(fn (Program $p) => ($p->academicUnit->code ?? '').' '.$p->name)
            ->mapWithKeys(fn (Program $p) => [$p->id => $college ? $p->name : ($p->academicUnit->code ?? '').' · '.$p->name])
            ->all();
    }
}
