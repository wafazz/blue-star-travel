<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReportExport implements FromArray, WithHeadings, ShouldAutoSize, WithTitle
{
    public function __construct(private array $report) {}

    public function headings(): array
    {
        return array_column($this->report['columns'], 'label');
    }

    public function array(): array
    {
        $rows = array_map([$this, 'normalize'], $this->report['rows']);

        $totals = $this->report['totals'] ?? [];
        if ($totals) {
            $line = [];
            foreach (array_keys($this->report['columns']) as $i) {
                $line[] = $totals[$i] ?? '';
            }
            $rows[] = array_fill(0, count($this->report['columns']), '');
            $rows[] = $this->normalize($line);
        }

        return $rows;
    }

    // PhpSpreadsheet's fromArray drops values loosely equal to null — a numeric 0
    // would silently export as a blank cell, so pass it as the string "0" instead
    // (the default value binder converts it back to a numeric cell).
    private function normalize(array $row): array
    {
        return array_map(fn ($v) => is_numeric($v) && (float) $v === 0.0 ? '0' : $v, $row);
    }

    public function title(): string
    {
        return substr($this->report['title'], 0, 31);
    }
}
