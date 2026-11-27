<?php

namespace App\Domain\Repository\Import;

use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;

/** Reads the first sheet of a CSV or XLSX file as rows keyed by the heading row. */
class SpreadsheetRows implements SkipsEmptyRows, WithHeadingRow
{
    /** @return list<array<string, mixed>> */
    public static function read(string $path): array
    {
        $sheets = Excel::toArray(new self, $path);

        return array_values($sheets[0] ?? []);
    }
}
