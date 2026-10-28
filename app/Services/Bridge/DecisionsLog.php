<?php

namespace App\Services\Bridge;

use Illuminate\Support\Str;

/**
 * The decisions log as written in docs/DECISIONS.md, read into sections of tables, notes and lists, so the
 * `decisions` bridge page always shows the file as it is in the repository. The empty sign-off section is left out.
 *
 * @phpstan-type Cell array{html: string, text: string}
 * @phpstan-type Table array{type: 'table', head: list<string>, rows: list<list<Cell>>, status: int|null}
 * @phpstan-type Block Table|array{type: 'list', items: list<string>}|array{type: 'text', html: string}
 * @phpstan-type Section array{title: string, title_html: string, blocks: list<Block>}
 */
class DecisionsLog
{
    public const FILE = 'docs/DECISIONS.md';

    public function __construct(private readonly ?string $path = null) {}

    /** @return list<Section> */
    public function sections(): array
    {
        $file = $this->path ?? base_path(self::FILE);

        if (! is_file($file)) {
            return [];
        }

        $raw = [];
        $current = null;

        // `u` matters: without it `\R` also breaks at byte 0x85, which is part of Arabic letters such as م.
        foreach (preg_split('/\R/u', (string) file_get_contents($file)) ?: [] as $line) {
            $line = trim($line);

            if (str_starts_with($line, '## ')) {
                if ($current !== null) {
                    $raw[] = $current;
                }

                $title = trim(substr($line, 3));
                $current = preg_match('/sign-off/i', $title) === 1 ? null : ['title' => $title, 'lines' => []];
            } elseif ($current !== null) {
                $current['lines'][] = $line;
            }
        }

        if ($current !== null) {
            $raw[] = $current;
        }

        return array_map(fn (array $section): array => [
            'title' => $section['title'],
            'title_html' => $this->inline($section['title']),
            'blocks' => $this->blocks($section['lines']),
        ], $raw);
    }

    /**
     * Groups a section's lines into tables, lists and paragraphs; a blank line or a change of kind ends a block.
     *
     * @param  list<string>  $lines
     * @return list<Block>
     */
    private function blocks(array $lines): array
    {
        $groups = [];
        $kind = null;
        $buffer = [];

        foreach ([...$lines, ''] as $line) {
            $type = match (true) {
                $line === '' => null,
                str_starts_with($line, '|') => 'table',
                str_starts_with($line, '- ') => 'list',
                default => 'text',
            };

            if ($type !== $kind && $buffer !== []) {
                $groups[] = ['kind' => (string) $kind, 'lines' => $buffer];
                $buffer = [];
            }

            $kind = $type;

            if ($type !== null) {
                $buffer[] = $type === 'list' ? substr($line, 2) : $line;
            }
        }

        return array_map(fn (array $group): array => $this->block($group['kind'], $group['lines']), $groups);
    }

    /**
     * @param  list<string>  $lines
     * @return Block
     */
    private function block(string $kind, array $lines): array
    {
        return match ($kind) {
            'table' => $this->table($lines),
            'list' => ['type' => 'list', 'items' => array_map($this->inline(...), $lines)],
            default => ['type' => 'text', 'html' => $this->inline(implode(' ', $lines))],
        };
    }

    /**
     * How many rows of the log are decided and how many are still open, from every table's Status column.
     *
     * @param  list<Section>  $sections
     * @return array{decided: int, open: int, other: int}
     */
    public function summary(array $sections): array
    {
        $counts = ['decided' => 0, 'open' => 0, 'other' => 0];

        foreach ($sections as $section) {
            foreach ($section['blocks'] as $block) {
                if ($block['type'] !== 'table' || $block['status'] === null) {
                    continue;
                }

                foreach ($block['rows'] as $row) {
                    $tone = self::statusTone($row[$block['status']]['text'] ?? '');
                    $counts[$tone === 'decided' ? 'decided' : ($tone === 'open' ? 'open' : 'other')]++;
                }
            }
        }

        return $counts;
    }

    /** The kind of a Status cell: decided, open, default (applied, not yet confirmed), closed, or null for anything else. */
    public static function statusTone(string $status): ?string
    {
        $status = ltrim($status);

        return match (true) {
            str_starts_with($status, 'Decided') => 'decided',
            str_starts_with($status, 'Open') => 'open',
            str_starts_with($status, 'Default') => 'default',
            str_starts_with($status, 'Closed'), str_starts_with($status, 'Resolved') => 'closed',
            default => null,
        };
    }

    /**
     * @param  list<string>  $lines
     * @return Table
     */
    private function table(array $lines): array
    {
        $rows = array_map($this->cells(...), $lines);
        $head = array_map(fn (string $cell): string => strip_tags($this->inline($cell)), array_shift($rows) ?? []);
        $rows = array_values(array_filter($rows, fn (array $cells): bool => ! $this->isSeparator($cells)));
        $status = array_search('Status', $head, true);

        return [
            'type' => 'table',
            'head' => $head,
            'rows' => array_map(fn (array $cells): array => array_map(function (string $cell): array {
                $html = $this->inline($cell);

                return ['html' => $html, 'text' => html_entity_decode(strip_tags($html))];
            }, $cells), $rows),
            'status' => $status === false ? null : $status,
        ];
    }

    /** @return list<string> */
    private function cells(string $line): array
    {
        $inner = preg_replace('/^\||\|$/u', '', $line) ?? $line;

        return array_map(fn (string $cell): string => trim(str_replace('\|', '|', $cell)), preg_split('/(?<!\\\\)\|/u', $inner) ?: []);
    }

    /** @param list<string> $cells */
    private function isSeparator(array $cells): bool
    {
        return $cells !== [] && array_filter($cells, fn (string $cell): bool => preg_match('/^:?-{3,}:?$/', $cell) !== 1) === [];
    }

    private function inline(string $markdown): string
    {
        return trim(Str::inlineMarkdown($markdown, ['html_input' => 'escape', 'allow_unsafe_links' => false]));
    }
}
