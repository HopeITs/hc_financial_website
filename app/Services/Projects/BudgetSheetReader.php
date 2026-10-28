<?php

namespace App\Services\Projects;

use DateTimeInterface;
use InvalidArgumentException;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Reads budget lines out of an Excel workbook without insisting on one layout. Headers are recognised by meaning
 * (English or Arabic synonyms, any order, any extra columns), the header row may sit below a title block, and every
 * sheet is tried: the one that yields the most budget lines wins.
 *
 * When several columns could hold the same thing (Total/$, Total/EUR, a Year 2 total...) the most useful one is
 * picked: a USD column over other currencies, the first year over later ones, and a column that is actually filled
 * in over one that is mostly empty. Section headings (a name with no amount), totals and blank rows are skipped, and a
 * category or type left blank under a merged cell takes the one above it.
 */
class BudgetSheetReader
{
    private const HEADER_SEARCH_ROWS = 30;

    private const MAX_ROWS = 20000;

    /** @var array<string, string> field => pattern on the normalised header; the first field that matches owns the column */
    private const FIELDS = [
        'donor' => '/donor|funder|funding|grantor|مانح|ممول|الجهه المانحه/u',
        'type' => '/type|نوع/u',
        'category' => '/categor|section|group|فئه|تصنيف|قسم|مجموعه/u',
        'price' => '/(unit|per)\s*(cost|price|rate)|price|\brate\b|unit\s*\/|unit\s*(\$|usd|eur|€)|سعر|(تكلفه|كلفه)\s*الوحده/u',
        'total' => '/total|amount|\bsum\b|cost|budget(?!\s*(line|item|categor|type|name))|اجمالي|مجموع|مبلغ|ميزانيه|تكلفه|كلفه/u',
        'timing' => '/timing|duration|month|period|frequency|مده|اشهر|شهر|فتره/u',
        'unit' => '/^(units?|qty|quantity|no\.?|number|#)(\s|$)|no\.?\s*of\s*units|عدد|كميه|وحده|وحدات/u',
        'name' => '/name|item|description|activit|expense|line|detail|particular|بند|وصف|بيان|تفاصيل|نشاط|اسم|صنف/u',
    ];

    private const TOTAL_ROW = '/^\s*(grand\s+|sub\s*-?\s*)?total\b|^\s*(المجموع|الاجمالي|الإجمالي)/iu';

    /**
     * @return list<array{category: string, type: string, name: string, donor: string, budget: string, currency: string}>
     */
    public function read(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);

        $best = null;
        $headerFound = false;

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $rows = [];

                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = array_map($this->text(...), $row->toArray());

                    if (count($rows) >= self::MAX_ROWS) {
                        break;
                    }
                }

                $layout = $this->layoutOf($rows);

                if ($layout === null) {
                    continue;
                }

                $headerFound = true;
                $lines = $this->linesOf($rows, $layout);

                if ($best === null || count($lines) > count($best)) {
                    $best = $lines;
                }
            }
        } finally {
            $reader->close();
        }

        if (! $headerFound) {
            throw new InvalidArgumentException(__('projects.manage.import_columns'));
        }

        return $best ?? [];
    }

    private function text(mixed $cell): string
    {
        if ($cell instanceof DateTimeInterface) {
            return '';
        }

        if (is_float($cell)) {
            return rtrim(rtrim(number_format($cell, 6, '.', ''), '0'), '.');
        }

        return trim((string) $cell);
    }

    /**
     * Finds the header row (the one naming the most fields) and picks one column per field.
     *
     * @param  list<list<string>>  $rows
     * @return array{header: int, columns: array<string, int>, currencies: array<string, string>}|null
     */
    private function layoutOf(array $rows): ?array
    {
        $best = null;

        foreach (array_slice($rows, 0, self::HEADER_SEARCH_ROWS, true) as $index => $cells) {
            $fields = [];

            foreach ($cells as $column => $cell) {
                $field = $this->fieldOf($cell);

                if ($field !== null) {
                    $fields[$field][] = $column;
                }
            }

            if (! isset($fields['name']) || (! isset($fields['total']) && ! isset($fields['price']))) {
                continue;
            }

            if ($best === null || count($fields) > count($best['fields'])) {
                $best = ['header' => $index, 'fields' => $fields];
            }
        }

        if ($best === null) {
            return null;
        }

        $body = array_slice($rows, $best['header'] + 1);
        $columns = [];
        $currencies = [];

        foreach ($best['fields'] as $field => $candidates) {
            $column = $this->choose($field, $candidates, $rows[$best['header']], $body);
            $columns[$field] = $column;
            $currencies[$field] = $this->currencyOf($rows[$best['header']][$column]) ?? 'USD';
        }

        return ['header' => $best['header'], 'columns' => $columns, 'currencies' => $currencies];
    }

    private function fieldOf(string $header): ?string
    {
        $header = $this->normalise($header);

        if ($header === '') {
            return null;
        }

        foreach (self::FIELDS as $field => $pattern) {
            if (preg_match($pattern, $header) === 1) {
                return $field;
            }
        }

        return null;
    }

    private function normalise(string $text): string
    {
        $text = mb_strtolower(preg_replace('/\s+/u', ' ', $text) ?? $text);
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0640}]/u', '', $text) ?? $text; // Arabic marks and tatweel
        $text = strtr($text, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي']);

        return trim($text);
    }

    private function currencyOf(string $header): ?string
    {
        $header = $this->normalise($header);

        return match (true) {
            preg_match('/\$|usd|dollar|دولار/u', $header) === 1 => 'USD',
            preg_match('/€|eur|euro|يورو/u', $header) === 1 => 'EUR',
            preg_match('/syp|ليره|ل\.س/u', $header) === 1 => 'SYP',
            default => null,
        };
    }

    /**
     * @param  list<int>  $candidates  the columns whose header reads as this field
     * @param  list<string>  $header
     * @param  list<list<string>>  $body
     */
    private function choose(string $field, array $candidates, array $header, array $body): int
    {
        if (count($candidates) === 1) {
            return $candidates[0];
        }

        $density = [];

        foreach ($candidates as $column) {
            $density[$column] = count(array_filter($body, function (array $cells) use ($column, $field): bool {
                $cell = $cells[$column] ?? '';

                return in_array($field, ['price', 'total'], true) ? $this->number($cell) !== null : $cell !== '';
            }));
        }

        // Only columns that are really filled in compete; an almost empty one never beats a full one.
        $threshold = max($density) / 2;
        $candidates = array_values(array_filter($candidates, fn (int $column): bool => $density[$column] >= $threshold));

        usort($candidates, function (int $a, int $b) use ($header, $density, $field): int {
            if (in_array($field, ['price', 'total'], true)) {
                $byRank = $this->rank($header[$b] ?? '') <=> $this->rank($header[$a] ?? '');

                if ($byRank !== 0) {
                    return $byRank;
                }
            }

            return [$density[$b], $a] <=> [$density[$a], $b];
        });

        return $candidates[0];
    }

    /** USD beats an unlabelled currency beats any other; the first year beats later ones. */
    private function rank(string $header): int
    {
        $currency = match ($this->currencyOf($header)) {
            'USD' => 2,
            null => 1,
            default => 0,
        };
        $later = preg_match('/(year|yr|سنه)\s*([2-9])/u', $this->normalise($header)) === 1;

        return $currency * 2 + ($later ? 0 : 1);
    }

    /**
     * @param  list<list<string>>  $rows
     * @param  array{header: int, columns: array<string, int>, currencies: array<string, string>}  $layout
     * @return list<array{category: string, type: string, name: string, donor: string, budget: string, currency: string}>
     */
    private function linesOf(array $rows, array $layout): array
    {
        $lines = [];
        $category = '';
        $type = '';

        foreach (array_slice($rows, $layout['header'] + 1) as $cells) {
            $cell = fn (string $field): string => isset($layout['columns'][$field]) ? ($cells[$layout['columns'][$field]] ?? '') : '';
            $name = $cell('name');

            if ($name === '' || preg_match(self::TOTAL_ROW, $name) === 1) {
                continue;
            }

            $total = $this->number($cell('total'));
            $price = $this->number($cell('price'));

            // A name with no amount is a section heading: it names the lines below when there is no category column.
            if ($total === null && $price === null) {
                if (! isset($layout['columns']['category'])) {
                    $category = $name;
                }

                continue;
            }

            // Without a total (or with a blank one) the line is Unit x Timing x Unit price.
            if ($total === null) {
                $total = bcmul(bcmul($this->number($cell('unit')) ?? '1', $this->number($cell('timing')) ?? '1', 4), (string) $price, 2);
            }

            $category = $cell('category') !== '' ? $cell('category') : $category;
            $type = $cell('type') !== '' ? $cell('type') : $type;

            $lines[] = [
                'category' => $category,
                'type' => $type,
                'name' => $name,
                'donor' => $cell('donor'),
                'budget' => bcadd($total, '0', 2),
                'currency' => $layout['currencies']['total'] ?? $layout['currencies']['price'] ?? 'USD',
            ];
        }

        return $lines;
    }

    /**
     * @return numeric-string|null
     */
    private function number(string $text): ?string
    {
        $text = strtr($text, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٫' => '.', '٬' => '']);
        $text = preg_replace('/[,\s$€]|usd|eur/iu', '', $text) ?? $text;

        if ($text === '' || ! is_numeric($text)) {
            return null;
        }

        // A float cell can arrive as 1.0E+5, which bcmath refuses.
        return stripos($text, 'e') === false ? $text : number_format((float) $text, 4, '.', '');
    }
}
