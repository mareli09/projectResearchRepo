<?php

namespace App\Domain\Analytics;

use App\Domain\Audit\Audit;
use App\Domain\Audit\AuditEvent;
use App\Domain\Site\Corpus;
use App\Exports\DashboardWorkbook;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dashboard exports for accreditation reports (CLAUDE.md §7 M7): every panel of a dashboard
 * as a table, in Excel (one sheet per panel), CSV (one file, panel after panel) or PDF.
 * The same filters as the page apply. Each export is logged.
 */
final class DashboardExport
{
    public const FORMATS = ['xlsx', 'csv', 'pdf'];

    /**
     * @param  list<string>  $scope
     * @param  array<string, mixed>  $filters
     */
    public static function response(string $format, string $title, string $scopeLabel, array $scope, array $filters, bool $full, User $by): Response
    {
        abort_unless(in_array($format, self::FORMATS, true), 404);

        $d = Dashboard::build($scope, $filters, $full);
        $tables = self::tables($d, $full);
        $meta = [
            'title' => $title,
            'scope' => $scopeLabel,
            'filters' => self::filterText($filters),
            'generated' => now(config('app.timezone'))->format('M j, Y · g:i A'),
            'asOf' => $d['asOf'] ? 'Summary figures as of '.$d['asOf'].' (nightly snapshot).' : 'Summary figures counted live.',
        ];
        $name = Str::slug($title).'-'.now(config('app.timezone'))->format('Y-m-d');

        Audit::record(AuditEvent::DOWNLOAD, "Dashboard exported ({$format}): {$title}", null, ['format' => $format, 'filters' => $filters, 'scope' => $scope], $by);

        return match ($format) {
            'xlsx' => Excel::download(new DashboardWorkbook($meta, $tables), $name.'.xlsx'),
            'csv' => self::csv($meta, $tables, $name.'.csv'),
            default => Pdf::loadView('analytics.export-pdf', ['meta' => $meta, 'tables' => $tables, 'kpis' => $d['kpis']])
                ->setPaper('a4')->download($name.'.pdf'),
        };
    }

    /**
     * Every panel of the dashboard as a table.
     *
     * @param  array<string, mixed>  $d  the output of Dashboard::build()
     * @return list<array{title: string, note?: string, headers: list<string>, rows: list<list<string|int|float>>}>
     */
    public static function tables(array $d, bool $full): array
    {
        $c = $d['charts'];
        $t = [];

        $t[] = ['title' => 'Summary', 'headers' => ['Figure', 'Value', 'Note'], 'rows' => array_map(fn ($k) => [$k[0], $k[1], $k[2]], $d['kpis'])];

        $years = $c['trend']['xAxis']['data'];
        $t[] = ['title' => 'Research output per year', 'note' => 'The trend line is descriptive only, never a projection used for decisions.',
            'headers' => ['Year', 'Outputs', 'Trend (descriptive only)'],
            'rows' => array_map(fn ($i) => [(string) $years[$i], $c['trend']['series'][0]['data'][$i], $c['trend']['series'][1]['data'][$i]], array_keys($years))];

        $cols = $c['college']['xAxis']['data'];
        $t[] = ['title' => 'Outputs by college and type', 'headers' => ['Research type', ...$cols],
            'rows' => array_map(fn ($s) => [$s['name'], ...$s['data']], $c['college']['series'])];

        $t[] = ['title' => 'Distribution by research type', 'headers' => ['Research type', 'Outputs'],
            'rows' => array_map(fn ($p) => [$p['name'], $p['value']], $c['type']['series'][0]['data'])];

        $themes = $c['themes']['yAxis']['data'];
        $t[] = ['title' => 'Theme distribution', 'note' => Corpus::currentRun() ? 'Themes of clustering run '.Corpus::currentRun()->public_id.'.' : 'Sample themes until the URDC confirms a clustering run.',
            'headers' => ['Theme', 'Outputs'],
            'rows' => array_map(fn ($i) => [$themes[$i], $c['themes']['series'][0]['data'][$i]['value']], array_keys($themes))];

        if ($full && isset($c['heat'])) {
            $hx = $c['heat']['xAxis']['data'];
            $hy = $c['heat']['yAxis']['data'];
            $grid = [];
            foreach ($c['heat']['series'][0]['data'] as [$j, $i, $v]) {
                $grid[$i][$j] = $v;
            }
            $t[] = ['title' => 'Themes by college', 'headers' => ['Theme', ...$hx],
                'rows' => array_map(fn ($i) => [$hy[$i], ...array_map(fn ($j) => $grid[$i][$j] ?? 0, array_keys($hx))], array_keys($hy))];
        }

        $t[] = ['title' => 'SDG alignment', 'headers' => ['SDG', 'Outputs'],
            'rows' => array_map(fn ($p) => [$p['name'], $p['value']], $c['sdg']['series'][0]['data'])];

        $bars = fn (array $o) => array_map(fn ($i) => [(string) $o['yAxis']['data'][$i], $o['series'][0]['data'][$i]], array_keys($o['yAxis']['data']));
        $t[] = ['title' => 'Top keywords', 'note' => 'Last 3 years.', 'headers' => ['Keyword', 'Outputs'], 'rows' => $bars($c['keywords'])];
        $t[] = ['title' => 'Adviser load', 'note' => 'Studies supervised, last 3 years.', 'headers' => ['Adviser', 'Studies'], 'rows' => $bars($c['advisers'])];

        $st = $d['screeningTotals'];
        $months = $c['screening']['xAxis']['data'];
        $t[] = ['title' => 'Similarity screening', 'note' => "{$st['year']}: {$st['screened']} screened, {$st['flagged']} flagged, {$st['confirmed']} confirmed duplicates. Screening never rejects a study by itself.",
            'headers' => ['Month', 'Original', 'Similar, needs review', 'Probable duplicate'],
            'rows' => array_map(fn ($i) => [$months[$i], ...array_map(fn ($s) => $s['data'][$i], $c['screening']['series'])], array_keys($months))];

        $t[] = ['title' => 'Submission compliance per unit', 'note' => 'Records with all required files.', 'headers' => ['Unit', 'Expected', 'Complete', 'Rate (%)'],
            'rows' => array_map(fn ($r) => [$r['college'], $r['expected'], $r['complete'], round($r['rate'], 1)], $d['compliance'])];

        if ($full) {
            $weeks = $c['usage']['xAxis']['data'];
            $t[] = ['title' => 'Repository usage', 'note' => 'Per week, last 12 weeks. Searches are university-wide.', 'headers' => ['Week of', 'Views', 'Searches', 'Downloads'],
                'rows' => array_map(fn ($i) => [$weeks[$i], ...array_map(fn ($s) => $s['data'][$i], $c['usage']['series'])], array_keys($weeks))];
            $t[] = ['title' => 'Most accessed studies', 'headers' => ['Study', 'Title', 'Views'],
                'rows' => array_map(fn ($r) => [$r['public_id'], $r['title'], $r['views']], $d['topAccessed'])];
            $t[] = ['title' => 'Searches with no or few results', 'note' => 'Last 90 days: possible gaps in the collection.', 'headers' => ['Search term', 'Times searched', 'Results', 'Last searched'],
                'rows' => array_map(fn ($g) => [$g[0], $g[1], $g[2], $g[3]], $d['searchGaps'])];
        }

        return $t;
    }

    /** @param array<string, mixed> $filters */
    public static function filterText(array $filters): string
    {
        $labels = Corpus::themeLabels();
        $parts = array_filter([
            ! empty($filters['college']) ? 'College: '.$filters['college'] : null,
            ! empty($filters['year']) ? 'Year: '.$filters['year'] : null,
            ! empty($filters['theme']) ? 'Theme: '.($labels[(int) $filters['theme']] ?? $filters['theme']) : null,
            ! empty($filters['sdg']) ? 'SDG '.(int) $filters['sdg'] : null,
        ]);

        return $parts === [] ? 'No filters' : implode(' · ', $parts);
    }

    /**
     * @param  array<string, string>  $meta
     * @param  list<array{title: string, note?: string, headers: list<string>, rows: list<list<string|int|float>>}>  $tables
     */
    private static function csv(array $meta, array $tables, string $name): Response
    {
        return response()->streamDownload(function () use ($meta, $tables): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads the characters correctly
            foreach (['TUA Research Repository', $meta['title'], $meta['scope'], $meta['filters'], 'Generated '.$meta['generated'], $meta['asOf']] as $line) {
                fputcsv($out, [self::safe($line)]);
            }
            foreach ($tables as $table) {
                fputcsv($out, []);
                fputcsv($out, [self::safe($table['title'])]);
                if (! empty($table['note'])) {
                    fputcsv($out, [self::safe($table['note'])]);
                }
                fputcsv($out, array_map(self::safe(...), $table['headers']));
                foreach ($table['rows'] as $row) {
                    fputcsv($out, array_map(self::safe(...), $row));
                }
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Stops a cell from being read as a formula when the file is opened in a spreadsheet (CSV injection). */
    public static function safe(string|int|float|null $v): string|int|float
    {
        if (is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$v;
        }

        return $v ?? '';
    }
}
