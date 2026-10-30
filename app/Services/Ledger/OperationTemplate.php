<?php

namespace App\Services\Ledger;

use App\Enums\FundSource;
use App\Models\GlAccount;
use App\Models\GlBudgetLine;
use App\Models\GlProject;
use DateTimeInterface;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/**
 * The Excel file of Multi entry (F-04). The program writes it: an "Operations" sheet whose headers carry the column name
 * and its Arabic label ("date | التاريخ"), and a "Lists" sheet with the projects, budget lines, cash accounts, income and
 * expense accounts, kinds and fund sources to copy codes from. The same file is read back into the tab-separated text
 * that OperationSheet parses, so the preview, the checks and the all-or-nothing save are the ones of the paste box.
 * A row of the sheet keeps its number in the text (blank rows stay blank lines), so an error points at the Excel row.
 */
class OperationTemplate
{
    public const SHEET = 'Operations';

    public const LISTS = 'Lists';

    /** @return string path of a temporary file the caller sends and deletes */
    public function write(bool $rtl): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'opt');
        $writer = new Writer;
        $writer->openToFile($path);
        $bold = (new Style)->withFontBold(true);

        $entry = $writer->getCurrentSheet();
        $entry->setName(self::SHEET);
        $entry->setSheetView((new SheetView)->withRightToLeft($rtl));
        $writer->addRow(new Row(array_map(
            fn (string $column) => Cell::fromValue($column.' | '.trans('ledger.template.columns.'.$column, [], 'ar').' | '.trans('ledger.template.columns.'.$column, [], 'en'), $bold),
            OperationSheet::COLUMNS,
        )));

        $lists = $writer->addNewSheetAndMakeItCurrent();
        $lists->setName(self::LISTS);
        $lists->setSheetView((new SheetView)->withRightToLeft($rtl));

        foreach ($this->lists() as $block) {
            $writer->addRow(new Row([Cell::fromValue($block['title'], $bold)]));
            foreach ($block['rows'] as $row) {
                $writer->addRow(Row::fromValues($row));
            }
            $writer->addRow(new Row([]));
        }

        $writer->close();

        return $path;
    }

    /**
     * The first sheet's rows as tab-separated text, a header row included; dates become YYYY-MM-DD.
     *
     * @throws InvalidArgumentException when the file cannot be read as a workbook
     */
    public function read(string $path): string
    {
        $reader = new Reader(new Options(SHOULD_PRESERVE_EMPTY_ROWS: true));

        try {
            $reader->open($path);
            $lines = [];

            foreach ($reader->getSheetIterator() as $sheet) {
                if ($sheet->getName() === self::LISTS) {
                    continue;
                }

                foreach ($sheet->getRowIterator() as $row) {
                    $cells = array_map(fn (mixed $cell) => $this->text($cell), $row->toArray());
                    $lines[] = trim(implode('', $cells)) === '' ? '' : implode("\t", $cells);
                }

                break;
            }
        } catch (\Throwable) {
            throw new InvalidArgumentException(__('ledger.template.unreadable'));
        } finally {
            try {
                $reader->close();
            } catch (\Throwable) {
            }
        }

        return rtrim(implode("\n", $lines));
    }

    /** @return array<int, array{title: string, rows: array<int, array<int, string>>}> */
    private function lists(): array
    {
        $accounts = fn (string $class) => GlAccount::query()->where('level', 'posting')->where('is_active', true)->where('class', $class)->orderBy('code')->get()
            ->map(fn (GlAccount $account) => [$account->code, $account->name_en, $account->name_ar])->values()->all();

        return [
            ['title' => (string) __('ledger.template.kinds'), 'rows' => array_map(fn (string $kind) => [$kind, (string) __('ledger.entry.kinds.'.$kind)], OperationEntry::KINDS)],
            ['title' => (string) __('ledger.template.projects'), 'rows' => GlProject::query()->where('is_general_account', false)->orderBy('code')->get(['code', 'name'])->map(fn (GlProject $project) => [(string) $project->code, (string) $project->name])->values()->all()],
            ['title' => (string) __('ledger.template.budget_lines'), 'rows' => GlBudgetLine::query()->orderBy('key')->get(['key', 'project_code', 'account_code'])->map(fn (GlBudgetLine $line) => [(string) $line->key, (string) $line->project_code, (string) $line->account_code])->values()->all()],
            ['title' => (string) __('ledger.template.cash'), 'rows' => GlAccount::query()->where('is_cash', true)->where('level', 'posting')->where('is_active', true)->orderBy('code')->get()
                ->map(fn (GlAccount $account) => [$account->code, $account->name_en, $account->name_ar, (string) $account->currency])->values()->all()],
            ['title' => (string) __('ledger.template.income'), 'rows' => $accounts('income')],
            ['title' => (string) __('ledger.template.expenses'), 'rows' => $accounts('expenses')],
            ['title' => (string) __('ledger.template.fund_sources'), 'rows' => array_map(fn (FundSource $source) => [$source->value, (string) $source->label()], FundSource::cases())],
        ];
    }

    private function text(mixed $cell): string
    {
        if ($cell instanceof DateTimeInterface) {
            return $cell->format('Y-m-d');
        }

        if (is_float($cell)) {
            return rtrim(rtrim(number_format($cell, 6, '.', ''), '0'), '.');
        }

        return str_replace(["\t", "\r", "\n"], ' ', trim((string) (is_scalar($cell) ? $cell : '')));
    }
}
