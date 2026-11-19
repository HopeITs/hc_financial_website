<?php

namespace App\Services\Ledger;

use App\Enums\FundSource;
use App\Models\GlAccount;
use App\Models\GlBudgetLine;
use App\Models\Voucher;
use App\Models\VoucherLine;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Vouchers to and from Excel (finance decision of 2026-11-20). One row per line, in columns that the import reads back
 * (VoucherImport::COLUMNS), then a few that only say more (status, account name, line description, dollars). The
 * template is the same table, empty but for two example vouchers, with the accounts and the budget lines on their own
 * sheets to copy codes from. In Arabic the sheets open right to left.
 */
class VoucherExport
{
    /** The most vouchers one export writes. */
    public const MAX_VOUCHERS = 5000;

    /** The columns after the import's, written for reading only. */
    public const EXTRA_COLUMNS = ['status', 'account_name', 'line_description', 'debit_usd', 'credit_usd'];

    /**
     * @param  list<string>  $voucherIds
     * @return string Path of a temporary file the caller sends and deletes.
     */
    public function xlsx(array $voucherIds, bool $rtl): string
    {
        $writer = $this->open($rtl, __('vouchers.excel.sheet_vouchers'));
        $writer->addRow($this->headings([...VoucherImport::COLUMNS, ...self::EXTRA_COLUMNS]));
        $arabic = app()->getLocale() === 'ar';

        foreach (array_chunk($voucherIds, 200) as $chunk) {
            $vouchers = Voucher::withTrashed()->with('lines.account')->whereIn('id', $chunk)->get()->keyBy('id');

            foreach ($chunk as $id) {
                $voucher = $vouchers->get($id);
                if ($voucher === null) {
                    continue;
                }

                foreach ($voucher->lines->sortBy('line_no') as $line) {
                    $writer->addRow(Row::fromValues($this->line($voucher, $line, $arabic)));
                }
            }
        }

        $writer->close();

        return $this->path;
    }

    /** @return string Path of the template, a temporary file the caller sends and deletes. */
    public function template(bool $rtl): string
    {
        $writer = $this->open($rtl, __('vouchers.excel.sheet_vouchers'));
        $writer->addRow($this->headings(VoucherImport::COLUMNS));
        foreach ($this->examples() as $example) {
            $writer->addRow(Row::fromValues($example));
        }

        $arabic = app()->getLocale() === 'ar';
        $bold = (new Style)->withFontBold(true);

        $accounts = $writer->addNewSheetAndMakeItCurrent();
        $accounts->setName(mb_substr(__('vouchers.excel.sheet_accounts'), 0, 31));
        $accounts->setSheetView((new SheetView)->withRightToLeft($rtl));
        $writer->addRow(new Row(array_map(fn (string $heading): Cell => Cell::fromValue($heading, $bold), [
            __('vouchers.excel.col.account'), __('vouchers.excel.col.account_name'), __('vouchers.excel.col.currency'), __('vouchers.excel.needs'),
        ])));
        foreach (GlAccount::query()->where('level', 'posting')->where('is_active', true)->orderBy('code')->get() as $account) {
            $writer->addRow(Row::fromValues([$account->code, $arabic ? $account->name_ar : $account->name_en, (string) $account->currency, implode(', ', (array) $account->required_tags)]));
        }

        $lines = $writer->addNewSheetAndMakeItCurrent();
        $lines->setName(mb_substr(__('vouchers.excel.sheet_budget_lines'), 0, 31));
        $lines->setSheetView((new SheetView)->withRightToLeft($rtl));
        $writer->addRow(new Row(array_map(fn (string $heading): Cell => Cell::fromValue($heading, $bold), [
            __('vouchers.excel.col.budget_line'), __('vouchers.excel.col.project'), __('vouchers.excel.col.account'),
        ])));
        foreach (GlBudgetLine::query()->orderBy('key')->get() as $budgetLine) {
            $writer->addRow(Row::fromValues([$budgetLine->key, $budgetLine->project_code, $budgetLine->account_code]));
        }

        $writer->close();

        return $this->path;
    }

    private string $path = '';

    private function open(bool $rtl, string $sheet): Writer
    {
        $this->path = (string) tempnam(sys_get_temp_dir(), 'vch');
        $writer = new Writer;
        $writer->openToFile($this->path);
        $writer->getCurrentSheet()->setName(mb_substr($sheet, 0, 31));
        $writer->getCurrentSheet()->setSheetView((new SheetView)->withRightToLeft($rtl));

        return $writer;
    }

    /** @param  list<string>  $columns */
    private function headings(array $columns): Row
    {
        $bold = (new Style)->withFontBold(true);

        return new Row(array_map(fn (string $column): Cell => Cell::fromValue(__('vouchers.excel.col.'.$column), $bold), $columns));
    }

    /** @return list<string|float|null> */
    private function line(Voucher $voucher, VoucherLine $line, bool $arabic): array
    {
        $amount = fn (mixed $value): ?float => bccomp((string) $value, '0', 2) === 1 ? (float) $value : null;

        return [
            $voucher->id,
            $voucher->type->value,
            $voucher->date->toDateString(),
            (string) $voucher->description,
            $voucher->request_id !== null ? (string) $voucher->request_id : '',
            $line->account_code,
            (string) $line->project_code,
            (string) $line->budget_line_key,
            $line->currency,
            $amount($line->debit_orig),
            $amount($line->credit_orig),
            $line->currency === Currencies::BASE ? null : (float) $line->rate,
            (string) $line->contact,
            (string) $line->center,
            (string) $line->fund_source?->value,
            $voucher->trashed() ? __('vouchers.status.rejected') : $voucher->status->label(),
            $line->account !== null ? ($arabic ? $line->account->name_ar : $line->account->name_en) : '',
            (string) $line->reason,
            (float) $line->debit_usd,
            (float) $line->credit_usd,
        ];
    }

    /** @return list<list<string|float|null>> two example vouchers of the template: a payment on a budget line, and a journal */
    private function examples(): array
    {
        $budgetLine = GlBudgetLine::query()->with('project')->orderBy('key')->first();
        $date = now()->toDateString();
        $fundSource = ($budgetLine?->project?->donor ?? '') !== '' ? FundSource::DonorGrant : FundSource::ChcOwnFunds;

        return [
            ['1', 'CPV', $date, __('vouchers.excel.example_payment'), '', '', $budgetLine?->project_code ?? 'PROJECT', $budgetLine?->key ?? 'PROJECT|Budget line', 'USD', 100, null, null, __('vouchers.excel.example_supplier'), '', $fundSource->value],
            ['1', 'CPV', $date, '', '', '1111', '', '', 'USD', null, 100, null, '', '', ''],
            ['2', 'JV', $date, __('vouchers.excel.example_journal'), '', '5503', '', '', 'USD', 10, null, null, '', '', ''],
            ['2', 'JV', $date, '', '', '1111', '', '', 'USD', null, 10, null, '', '', ''],
        ];
    }
}
