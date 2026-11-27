<?php

namespace App\Exports;

use App\Domain\Repository\Import\ImportTemplate;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

/** The bulk import template as XLSX. */
class ImportTemplateExport implements FromArray, ShouldAutoSize
{
    /** @return list<list<string>> */
    public function array(): array
    {
        return ImportTemplate::rows();
    }
}
