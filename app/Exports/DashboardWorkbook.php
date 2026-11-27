<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/** A dashboard export as an Excel workbook: a cover sheet, then one sheet per panel (CLAUDE.md §7 M7). */
class DashboardWorkbook implements WithMultipleSheets
{
    /**
     * @param  array<string, string>  $meta
     * @param  list<array{title: string, note?: string, headers: list<string>, rows: list<list<string|int|float>>}>  $tables
     */
    public function __construct(private readonly array $meta, private readonly array $tables) {}

    /** @return list<DashboardSheet> */
    public function sheets(): array
    {
        $cover = ['title' => 'About', 'headers' => ['TUA Research Repository'], 'rows' => [
            [$this->meta['title']], [$this->meta['scope']], [$this->meta['filters']], ['Generated '.$this->meta['generated']], [$this->meta['asOf']],
            ['One sheet per dashboard panel. Trend lines are descriptive only.'],
        ]];

        return array_map(fn (array $t) => new DashboardSheet($t), [$cover, ...$this->tables]);
    }
}
