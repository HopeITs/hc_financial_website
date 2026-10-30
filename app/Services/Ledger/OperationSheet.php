<?php

namespace App\Services\Ledger;

use App\Models\Voucher;
use Illuminate\Support\Facades\DB;

/**
 * Multi entry: operations pasted from Excel, one per row, checked row by row and saved all together or not at all.
 *
 * The columns are those of OperationEntry, in this order (a header row, if present, may use them in any order):
 * date, kind, project, budget_line, cash, account, to_cash, amount, rate, contact, fund_source, document, description.
 * The kind is a word (income, expense, exchange, advance, repayment) or the old program's letter (I, E, X, A, R).
 * A date is YYYY-MM-DD, YYYY/MM/DD or DD/MM/YYYY.
 */
class OperationSheet
{
    public const MAX_ROWS = 500;

    public const COLUMNS = ['date', 'kind', 'project', 'budget_line', 'cash', 'account', 'to_cash', 'amount', 'rate', 'contact', 'fund_source', 'document', 'description'];

    private const LETTERS = ['i' => 'income', 'e' => 'expense', 'x' => 'exchange', 'ex' => 'exchange', 'a' => 'advance', 'r' => 'repayment'];

    public function __construct(private readonly OperationEntry $entry, private readonly PostVoucher $posting) {}

    /**
     * The operations in the pasted text, with the line each came from. Blank lines are skipped.
     *
     * @return list<array{line: int, operation: array<string, string>}>
     */
    public function parse(string $text): array
    {
        $lines = preg_split('/\R/', $text) ?: [];
        $columns = self::COLUMNS;
        $rows = [];

        foreach ($lines as $index => $raw) {
            if (trim($raw) === '') {
                continue;
            }

            $cells = array_map(fn (string $cell) => trim($cell, " \t\"'"), explode("\t", $raw));

            if ($rows === [] && $this->isHeader($cells)) {
                $columns = array_map(fn (string $cell) => $this->column($cell), $cells);

                continue;
            }

            $operation = array_fill_keys(self::COLUMNS, '');
            foreach ($cells as $position => $cell) {
                $name = $columns[$position] ?? null;
                if ($name !== null && $name !== '') {
                    $operation[$name] = $cell;
                }
            }

            $operation['kind'] = self::LETTERS[strtolower($operation['kind'])] ?? strtolower($operation['kind']);
            $operation['date'] = $this->date($operation['date']);
            $operation['amount'] = str_replace([',', ' '], '', $operation['amount']);
            $operation['rate'] = str_replace([',', ' '], '', $operation['rate']);

            $rows[] = ['line' => $index + 1, 'operation' => $operation];
        }

        return $rows;
    }

    /**
     * Every row built and checked against the posting rules, without saving anything.
     *
     * @param  list<array{line: int, operation: array<string, string>}>  $rows
     * @return list<array{line: int, operation: array<string, string>, draft: VoucherDraft|null, messages: list<string>}>
     */
    public function review(array $rows, int $preparedBy): array
    {
        $reviewed = [];

        foreach (array_slice($rows, 0, self::MAX_ROWS) as $row) {
            $built = $this->entry->build($row['operation'], $preparedBy);
            $messages = $built->problems;

            if ($built->draft !== null && $messages === []) {
                foreach ($this->posting->check($built->draft) as $violation) {
                    $messages[] = $violation->message;
                }
            }

            $reviewed[] = ['line' => $row['line'], 'operation' => $row['operation'], 'draft' => $built->draft, 'messages' => $messages];
        }

        return $reviewed;
    }

    /**
     * Saves every row as a pending voucher in one transaction. If any row is wrong, or posting fails, nothing is saved.
     *
     * @param  list<array{line: int, operation: array<string, string>}>  $rows
     * @return list<Voucher> the vouchers saved; empty when nothing was saved
     *
     * @throws LedgerRuleException
     */
    public function postAll(array $rows, int $preparedBy): array
    {
        $reviewed = $this->review($rows, $preparedBy);

        $drafts = [];
        foreach ($reviewed as $row) {
            if ($row['draft'] === null || $row['messages'] !== []) {
                return [];
            }

            $drafts[] = $row['draft'];
        }

        return DB::transaction(function () use ($drafts) {
            $vouchers = [];

            foreach ($drafts as $draft) {
                $vouchers[] = $this->posting->post($draft);
            }

            return $vouchers;
        });
    }

    /** @param  list<string>  $cells */
    private function isHeader(array $cells): bool
    {
        foreach ($cells as $cell) {
            if ($this->column($cell) === 'date' || $this->column($cell) === 'kind') {
                return true;
            }
        }

        return false;
    }

    private function column(string $header): string
    {
        $key = strtolower(str_replace([' ', '-'], '_', trim(explode('|', $header)[0])));

        return match ($key) {
            'date' => 'date',
            'kind', 'type', 'operation' => 'kind',
            'project' => 'project',
            'budget_line', 'line' => 'budget_line',
            'cash', 'cash_till', 'till', 'cash_account' => 'cash',
            'account', 'income_account', 'expense_account' => 'account',
            'to_cash', 'corresponding_cash_till', 'corresponding_till' => 'to_cash',
            'amount' => 'amount',
            'rate' => 'rate',
            'contact', 'person', 'payee' => 'contact',
            'fund_source', 'fund' => 'fund_source',
            'document', 'receipt' => 'document',
            'description', 'note', 'notes' => 'description',
            default => '',
        };
    }

    private function date(string $text): string
    {
        if (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$#', $text, $m) === 1) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        if (preg_match('#^(\d{4})[/.-](\d{1,2})[/.-](\d{1,2})$#', $text, $m) === 1) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }

        return $text;
    }
}
