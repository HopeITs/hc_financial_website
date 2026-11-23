<?php

namespace App\Services\Excel;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * One kind of record a data screen exports to Excel and imports from it (finance request of 2026-11-20): its columns,
 * the rows it exports, a template with examples and reference sheets, and how one imported row is checked and saved.
 * An import is checked whole first; it is saved only when every row is right, all in one transaction.
 *
 * @phpstan-type Reviewed array{row: int, cells: array<string, string>, action: string, problems: list<string>}
 */
abstract class ExcelDataset
{
    /** The most rows one file may hold. */
    public const MAX_ROWS = 2000;

    /** The screen whose permission the export (view) and the import ({@see importAction()}) need. */
    abstract public function screen(): string;

    /** The action on the screen the import needs. */
    public function importAction(): string
    {
        return 'create';
    }

    /** False for a list that is only exported (its records are made on their own screen). */
    public function importable(): bool
    {
        return true;
    }

    /** The start of the file names: `donors` gives donors-2026-11-20.xlsx and donors-template.xlsx. */
    abstract public function fileName(): string;

    /** @return list<string> the column keys, in order; their headings are lang `excel.col.<key>` */
    abstract public function columns(): array;

    /**
     * The columns of an export: the import's, then any that only say more (a status, who entered it). The import
     * reads the first {@see columns()} cells, so an export can be changed and imported again.
     *
     * @return list<string>
     */
    public function exportColumns(): array
    {
        return $this->columns();
    }

    /**
     * One row per record, in the order of {@see exportColumns()}.
     *
     * @param  array<string, mixed>  $context
     * @return iterable<list<string|int|float|null>>
     */
    abstract public function exportRows(array $context): iterable;

    /**
     * @param  array<string, mixed>  $context
     * @return list<list<string|int|float|null>>
     */
    public function exampleRows(array $context): array
    {
        return [];
    }

    /**
     * Sheets that list the codes a row can take, after the table.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, array{headings: list<string>, rows: iterable<list<string|int|float|null>>}>
     */
    public function referenceSheets(array $context): array
    {
        return [];
    }

    /**
     * A second sheet the import reads too when the file has it (finance request of 2026-11-23): the lang key of its
     * name (`excel.sheet.<key>`) and its columns. Its rows reach {@see check()} and {@see saveRow()} in the context, under
     * `extra`. Null when the import reads one sheet only.
     *
     * @return array{key: string, columns: list<string>}|null
     */
    public function extraSheet(): ?array
    {
        return null;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<list<string|int|float|null>>
     */
    public function extraExampleRows(array $context): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return iterable<list<string|int|float|null>>
     */
    public function extraExportRows(array $context): iterable
    {
        return [];
    }

    /**
     * What is wrong in the second sheet as a whole, such as rows that belong to no record of the file.
     *
     * @param  list<array{row: int, cells: list<string>}>  $read
     * @param  array<string, mixed>  $context
     * @return list<string>
     */
    public function extraProblems(array $read, array $context): array
    {
        return [];
    }

    /** @return list<string> */
    public function extraHeadings(): array
    {
        return array_map(fn (string $column): string => __('excel.col.'.$column), $this->extraSheet()['columns'] ?? []);
    }

    /**
     * What a row would do ('new', 'update', or 'skip' for a row left to fill later) and what is wrong in it. The rows before it are given, to find the same
     * record twice in one file.
     *
     * @param  array<string, string>  $cells
     * @param  list<array<string, string>>  $before
     * @param  array<string, mixed>  $context
     * @return array{action: string, problems: list<string>}
     */
    abstract protected function check(array $cells, array $before, User $user, array $context): array;

    /**
     * Saves one row that {@see check()} found right.
     *
     * @param  array<string, string>  $cells
     * @param  array<string, mixed>  $context
     */
    abstract protected function saveRow(array $cells, User $user, array $context): void;

    /** @return list<string> */
    public function headings(): array
    {
        return array_map(fn (string $column): string => __('excel.col.'.$column), $this->columns());
    }

    /** @return list<string> */
    public function exportHeadings(): array
    {
        return array_map(fn (string $column): string => __('excel.col.'.$column), $this->exportColumns());
    }

    /**
     * @param  list<array{row: int, cells: list<string>}>  $read
     * @param  array<string, mixed>  $context
     * @return list<Reviewed>
     */
    public function review(array $read, User $user, array $context): array
    {
        $reviewed = [];
        $before = [];

        foreach ($read as $row) {
            $cells = array_combine($this->columns(), array_pad(array_slice($row['cells'], 0, count($this->columns())), count($this->columns()), ''));
            $cells = array_map(fn (string $cell): string => trim($cell), $cells);
            $result = $this->check($cells, $before, $user, $context);
            $reviewed[] = ['row' => $row['row'], 'cells' => $cells, 'action' => $result['action'], 'problems' => $result['problems']];
            $before[] = $cells;
        }

        return $reviewed;
    }

    /**
     * Saves every row of the file, or none.
     *
     * @param  list<array{row: int, cells: list<string>}>  $read
     * @param  array<string, mixed>  $context
     *
     * @throws ExcelException when one row is wrong
     */
    public function save(array $read, User $user, array $context): int
    {
        $reviewed = $this->review($read, $user, $context);
        $broken = count(array_filter($reviewed, fn (array $row): bool => $row['problems'] !== []));

        if ($broken > 0) {
            throw new ExcelException([trans_choice('excel.not_saved', $broken, ['count' => $broken])]);
        }

        if (($extraProblems = $this->extraProblems($read, $context)) !== []) {
            throw new ExcelException($extraProblems);
        }

        $saving = array_values(array_filter($reviewed, fn (array $row): bool => $row['action'] !== 'skip'));

        DB::transaction(function () use ($saving, $user, $context): void {
            foreach ($saving as $row) {
                $this->saveRow($row['cells'], $user, $context);
            }
        });

        return count($saving);
    }
}
