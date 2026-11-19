<?php

namespace App\Services\Ledger;

use App\Enums\VoucherType;
use App\Models\GlAccount;
use App\Models\Voucher;
use App\Services\Treasury\CashBook;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Vouchers from Excel (finance decision of 2026-11-20): the first sheet of the file, one row per line, in the columns of
 * the template (and of the export). Rows with the same voucher reference make one voucher; its type, date, description
 * and request are read from its first row. Each voucher is built and checked like one typed in the voucher builder
 * (VoucherDraftBuilder, PostVoucher::check). A line in another currency with no rate takes the month's rate for
 * pounds and the day's for other currencies. Nothing is saved unless every voucher is right, and then all of them are,
 * as pending approval, in one transaction.
 */
class VoucherImport
{
    /** The columns of the template, in order. */
    public const COLUMNS = ['voucher', 'type', 'date', 'description', 'request', 'account', 'project', 'budget_line', 'currency', 'debit', 'credit', 'rate', 'contact', 'center', 'fund_source'];

    /** The most rows one file may hold. */
    public const MAX_ROWS = 2000;

    /** The types a file may hold: everything but the opening voucher. */
    private const TYPES = [VoucherType::Receipt, VoucherType::Payment, VoucherType::Journal, VoucherType::Transfer, VoucherType::Exchange];

    public function __construct(
        private readonly VoucherDraftBuilder $builder,
        private readonly PostVoucher $posting,
        private readonly CashBook $book,
    ) {}

    /**
     * Reads the file into rows keyed by the template's columns, with their row number in the sheet.
     *
     * @return list<array<string, string>>
     *
     * @throws InvalidArgumentException when the file cannot be read or holds no row
     */
    public function read(string $path): array
    {
        $reader = new Reader;

        try {
            $reader->open($path);
        } catch (\Throwable) {
            throw new InvalidArgumentException(__('vouchers.excel.unreadable'));
        }

        $rows = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $number => $row) {
                    if ($number === 1) {
                        continue;
                    }

                    $cells = array_map($this->text(...), array_slice($row->toArray(), 0, count(self::COLUMNS)));
                    if (implode('', $cells) === '') {
                        continue;
                    }

                    $rows[] = array_combine(self::COLUMNS, array_pad($cells, count(self::COLUMNS), '')) + ['row' => (string) $number];

                    if (count($rows) > self::MAX_ROWS) {
                        throw new InvalidArgumentException(__('vouchers.excel.too_many', ['max' => self::MAX_ROWS]));
                    }
                }

                break;
            }
        } finally {
            $reader->close();
        }

        if ($rows === []) {
            throw new InvalidArgumentException(__('vouchers.excel.empty'));
        }

        return $rows;
    }

    /**
     * Groups the rows into vouchers and checks each, as the preview shows them.
     *
     * @param  list<array<string, string>>  $rows
     * @return list<array{ref: string, type: string, date: string, description: string, rows: list<string>, lines: int, debit: string, credit: string, problems: list<string>, draft: VoucherDraft|null}>
     */
    public function review(array $rows, int $preparedBy): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $groups[trim($row['voucher']) !== '' ? trim($row['voucher']) : '#'.$row['row']][] = $row;
        }

        $fixed = GlAccount::query()->whereIn('code', collect($rows)->pluck('account')->filter()->unique()->values())->pluck('currency', 'code');
        $vouchers = [];

        foreach ($groups as $ref => $lines) {
            $head = $lines[0];
            $problems = [];
            $type = strtoupper(trim($head['type']));
            $date = trim($head['date']);

            if (! in_array(VoucherType::tryFrom($type), self::TYPES, true)) {
                $problems[] = __('vouchers.excel.bad_type', ['row' => $head['row'], 'types' => implode(', ', array_map(fn (VoucherType $t): string => $t->value, self::TYPES))]);
            }

            $builderRows = [];
            foreach ($lines as $line) {
                $debit = trim($line['debit']);
                $credit = trim($line['credit']);

                if (($debit !== '') === ($credit !== '')) {
                    $problems[] = __('vouchers.excel.debit_or_credit', ['row' => $line['row']]);
                }

                $currency = strtoupper(trim($line['currency']));
                $accountCurrency = (string) ($fixed[trim($line['account'])] ?? '');
                $currency = in_array($accountCurrency, ['', 'Any'], true) ? ($currency !== '' ? $currency : Currencies::BASE) : $accountCurrency;
                $rate = trim($line['rate']);

                if ($currency !== Currencies::BASE && $rate === '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
                    $rate = (string) ($this->book->rateFor($currency, CarbonImmutable::parse($date)) ?? '');
                }

                $builderRows[] = [
                    'account' => trim($line['account']),
                    'currency' => $currency,
                    'side' => $debit !== '' ? 'debit' : 'credit',
                    'amount' => $debit !== '' ? $debit : $credit,
                    'rate' => $rate,
                    'project' => trim($line['project']),
                    'budget_line' => trim($line['budget_line']),
                    'fund_source' => trim($line['fund_source']),
                    'contact' => trim($line['contact']),
                    'center' => trim($line['center']),
                ];
            }

            $built = $this->builder->build($type, $date, trim($head['description']), trim($head['request']), $builderRows, $preparedBy);
            $problems = [...$problems, ...$built->problems];
            $draft = $built->draft;

            if ($draft !== null && $problems === []) {
                $problems = array_map(fn (Violation $violation): string => $violation->line !== null && isset($lines[$violation->line - 1])
                    ? __('vouchers.excel.row_prefix', ['row' => $lines[$violation->line - 1]['row'], 'message' => $violation->message])
                    : $violation->message, $this->posting->check($draft));
            }

            $vouchers[] = [
                'ref' => (string) $ref,
                'type' => $type,
                'date' => $date,
                'description' => trim($head['description']),
                'rows' => array_column($lines, 'row'),
                'lines' => count($lines),
                'debit' => $draft !== null ? array_reduce($draft->lines, fn (string $sum, LineDraft $l): string => bcadd($sum, $l->debitUsd, 2), '0.00') : '0.00',
                'credit' => $draft !== null ? array_reduce($draft->lines, fn (string $sum, LineDraft $l): string => bcadd($sum, $l->creditUsd, 2), '0.00') : '0.00',
                'problems' => array_values(array_unique($problems)),
                'draft' => $problems === [] ? $draft : null,
            ];
        }

        return $vouchers;
    }

    /**
     * Saves every voucher of the file as pending approval, or none of them.
     *
     * @param  list<array<string, string>>  $rows
     * @return list<Voucher>
     *
     * @throws LedgerRuleException when one of them breaks a rule
     */
    public function save(array $rows, int $preparedBy): array
    {
        $vouchers = $this->review($rows, $preparedBy);
        $broken = array_filter($vouchers, fn (array $voucher): bool => $voucher['problems'] !== []);

        if ($broken !== []) {
            throw new LedgerRuleException([new Violation(0, __('vouchers.excel.not_saved', ['count' => count($broken)]))]);
        }

        return DB::transaction(fn (): array => array_map(fn (array $voucher): Voucher => $this->posting->post($voucher['draft']), $vouchers));
    }

    private function text(mixed $cell): string
    {
        if ($cell instanceof DateTimeInterface) {
            return $cell->format('Y-m-d');
        }

        if (is_float($cell) || is_int($cell)) {
            return rtrim(rtrim(number_format((float) $cell, 6, '.', ''), '0'), '.');
        }

        return trim((string) $cell);
    }
}
