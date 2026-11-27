<?php

namespace App\Domain\Search;

use App\Domain\Repository\ResearchTypes;
use App\Domain\Site\Corpus;
use App\Models\ResearchRecord;
use App\Models\Sdg;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Catalog and search over published records (CLAUDE.md §7 M4), with facets:
 * college, year, research type, theme, SDG and adviser; sort by relevance, newest or most viewed.
 *
 * Today the text match is a simple LIKE over title, abstract and keywords. Sprint 5 switches to
 * MySQL FULLTEXT (Scout database driver), logs every search to search_logs, and ranks by relevance.
 */
final class RecordSearch
{
    public const PER_PAGE = 20;

    /** @return array<string, mixed> validated filters */
    public static function filters(Request $request): array
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'college' => ['nullable', 'string', 'max:10'],
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

        return array_filter($f, fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @param  array<string, mixed>  $f
     * @return LengthAwarePaginator<int, ResearchRecord>
     */
    public static function run(array $f): LengthAwarePaginator
    {
        $q = trim((string) ($f['q'] ?? ''));

        $query = ResearchRecord::published()
            ->with(['authors', 'academicUnit:id,code', 'program:id,name', 'keywords:id,term'])
            ->when($q !== '', function (Builder $b) use ($q): void {
                $b->where(fn (Builder $w) => $w
                    ->where('title', 'like', "%{$q}%")
                    ->orWhere('abstract', 'like', "%{$q}%")
                    ->orWhereHas('keywords', fn (Builder $k) => $k->where('term', 'like', "%{$q}%")));
            })
            ->when($f['college'] ?? null, fn (Builder $b, $c) => $b->whereHas('academicUnit', fn (Builder $u) => $u->where('code', $c)))
            ->when($f['year'] ?? null, fn (Builder $b, $y) => $b->where('year', $y))
            ->when($f['type'] ?? null, fn (Builder $b, $t) => $b->where('research_type', $t))
            ->when($f['sdg'] ?? null, fn (Builder $b, $s) => $b->whereHas('sdgs', fn (Builder $x) => $x->where('sdgs.id', $s)))
            ->when($f['adviser'] ?? null, fn (Builder $b, $a) => $b->where('adviser_name', $a))
            ->when($f['theme'] ?? null, fn (Builder $b, $t) => $b->whereIn('id', Corpus::records()->where('theme', (int) $t)->pluck('id')));

        match ($f['sort'] ?? ($q !== '' ? 'relevance' : 'newest')) {
            'views' => $query->orderByDesc('views'),
            // Relevance for now: title matches first, then most viewed (Sprint 5: FULLTEXT score).
            'relevance' => $query->orderByRaw('CASE WHEN title LIKE ? THEN 0 ELSE 1 END', ["%{$q}%"])->orderByDesc('views'),
            default => $query->orderByDesc('year')->orderByDesc('views'),
        };

        return $query->paginate(self::PER_PAGE)->withQueryString();
    }

    /** @return array<string, array<int|string, string>> facet options */
    public static function facets(): array
    {
        $corpus = Corpus::records();

        return [
            'college' => array_combine($c = ['CAHS', 'CASE', 'IBAM', 'CEIS', 'CMT', 'SLCN'], $c),
            'year' => $corpus->pluck('year')->unique()->sortDesc()->mapWithKeys(fn ($y) => [$y => (string) $y])->all(),
            'type' => ResearchTypes::TYPES,
            'theme' => Corpus::themeLabels(),
            'sdg' => Sdg::orderBy('id')->get()->mapWithKeys(fn ($s) => [$s->id => 'SDG '.$s->id.' · '.$s->name])->all(),
            'adviser' => $corpus->pluck('adviser')->filter()->unique()->sort()->mapWithKeys(fn ($a) => [$a => $a])->all(),
        ];
    }
}
