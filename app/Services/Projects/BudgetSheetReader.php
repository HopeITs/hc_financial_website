<?php

namespace App\Services\Projects;

use InvalidArgumentException;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Reads a budget sheet in the format of the ADISC budget: the columns Budget Category, Budget Line Type, Budget Line
 * Name, Unit, Timing, Unit/$ Year 1, Total/$ Year 1, Unit/EUR, Total/EUR and Donor, in the first sheet.
 * Rows without a line name are skipped (totals, blank rows). Lines are in USD; the EUR columns are not imported.
 */
class BudgetSheetReader
{
    private const COLUMNS = [
        'category' => 'budget category',
        'type' => 'budget line type',
        'name' => 'budget line name',
        'unit' => 'unit',
        'timing' => 'timing',
        'price' => 'unit/$ year 1',
        'total' => 'total/$ year 1',
        'donor' => 'donor',
    ];

    /**
     * @return list<array{category: string, type: string, name: string, donor: string, budget: string}>
     */
    public function read(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);

        try {
            $columns = null;
            $lines = [];

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = array_map(fn (mixed $cell): string => trim((string) $cell), $row->toArray());

                    if ($columns === null) {
                        $columns = $this->columnsOf($cells);

                        continue;
                    }

                    $line = $this->lineOf($cells, $columns);

                    if ($line !== null) {
                        $lines[] = $line;
                    }
                }

                break; // the first sheet only
            }
        } finally {
            $reader->close();
        }

        if ($columns === null) {
            throw new InvalidArgumentException(__('projects.manage.import_empty'));
        }

        return $lines;
    }

    /**
     * @param  list<string>  $cells
     * @return array<string, int> field => column index
     */
    private function columnsOf(array $cells): array
    {
        $headers = array_map(fn (string $cell): string => mb_strtolower(preg_replace('/\s+/', ' ', $cell) ?? $cell), $cells);
        $columns = [];

        foreach (self::COLUMNS as $field => $header) {
            $index = array_search($header, $headers, true);

            if ($index !== false) {
                $columns[$field] = $index;
            }
        }

        foreach (['name', 'donor'] as $required) {
            if (! isset($columns[$required])) {
                throw new InvalidArgumentException(__('projects.manage.import_columns'));
            }
        }

        if (! isset($columns['total']) && ! isset($columns['price'])) {
            throw new InvalidArgumentException(__('projects.manage.import_columns'));
        }

        return $columns;
    }

    /**
     * @param  list<string>  $cells
     * @param  array<string, int>  $columns
     * @return array{category: string, type: string, name: string, donor: string, budget: string}|null
     */
    private function lineOf(array $cells, array $columns): ?array
    {
        $cell = fn (string $field): string => isset($columns[$field]) ? ($cells[$columns[$field]] ?? '') : '';
        $name = $cell('name');

        if ($name === '') {
            return null;
        }

        $total = $this->number($cell('total'));

        // A sheet without a total column (or a blank total) is Unit x Timing x Unit price.
        if ($total === null) {
            $unit = $this->number($cell('unit')) ?? '1';
            $timing = $this->number($cell('timing')) ?? '1';
            $total = bcmul(bcmul($unit, $timing, 4), $this->number($cell('price')) ?? '0', 2);
        }

        return [
            'category' => $cell('category'),
            'type' => $cell('type'),
            'name' => $name,
            'donor' => $cell('donor'),
            'budget' => bcadd($total, '0', 2),
        ];
    }

    /**
     * @return numeric-string|null
     */
    private function number(string $text): ?string
    {
        $text = str_replace([',', ' ', '$'], '', $text);

        if ($text === '' || ! is_numeric($text)) {
            return null;
        }

        // A float cell can arrive as 1.0E+5, which bcmath refuses.
        return stripos($text, 'e') === false ? $text : number_format((float) $text, 4, '.', '');
    }
}
