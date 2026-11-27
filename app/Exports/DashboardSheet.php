<?php

namespace App\Exports;

use App\Domain\Analytics\DashboardExport;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** One dashboard panel as a sheet: an optional note row, the bold header row, then the data. */
class DashboardSheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    /** @param array{title: string, note?: string, headers: list<string>, rows: list<list<string|int|float>>} $table */
    public function __construct(private readonly array $table) {}

    /** @return list<list<string|int|float>> */
    public function array(): array
    {
        $rows = [];
        if (! empty($this->table['note'])) {
            $rows[] = [DashboardExport::safe($this->table['note'])];
        }
        $rows[] = array_map(DashboardExport::safe(...), $this->table['headers']);
        foreach ($this->table['rows'] as $row) {
            $rows[] = array_map(DashboardExport::safe(...), $row);
        }

        return $rows;
    }

    public function title(): string
    {
        // Excel sheet names: at most 31 characters, none of : \ / ? * [ ]
        return mb_substr((string) preg_replace('/[:\\\\\/?*\[\]]/', '', $this->table['title']), 0, 31);
    }

    /** @return array<int, array<string, mixed>> */
    public function styles(Worksheet $sheet): array
    {
        $header = empty($this->table['note']) ? 1 : 2;

        return array_filter([1 => empty($this->table['note']) ? null : ['font' => ['italic' => true]], $header => ['font' => ['bold' => true]]]);
    }
}
