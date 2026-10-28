<?php

namespace App\Services\Ledger;

use App\Models\GlAccount;
use App\Models\GlBudgetLine;
use App\Support\Money;
use InvalidArgumentException;

/**
 * The entry screens' rule from an operation of the Windows program to a voucher, in chart terms (plan 1, "Windows
 * program to ledger"). One operation is one voucher, built from the same rows the voucher builder uses, so the rate of
 * a pound payment, the donor-currency amounts and the posting rules are the builder's own:
 *
 *  - income:    CRV, debit the cash account, credit the income account (the donor's), the project on both lines.
 *  - expense:   CPV, debit the expense account (a budget line fills it and its project), credit the cash account.
 *  - exchange:  TRF, debit the cash account received, credit the cash account given, at the typed rate.
 *  - advance:   CPV, debit 1201 (the person is the contact), credit the cash account.
 *  - repayment: CRV, debit the cash account, credit 1201.
 *
 * It never posts: it returns the builder's BuiltVoucher, and the caller checks and posts it.
 */
class OperationEntry
{
    public const KINDS = ['income', 'expense', 'exchange', 'advance', 'repayment'];

    public const ADVANCE_ACCOUNT = '1201';

    private const BLANK = [
        'kind' => '', 'date' => '', 'description' => '', 'project' => '', 'budget_line' => '', 'cash' => '', 'account' => '',
        'amount' => '', 'rate' => '', 'contact' => '', 'fund_source' => '', 'to_cash' => '', 'document' => '',
    ];

    public function __construct(private readonly VoucherDraftBuilder $builder, private readonly Currencies $currencies) {}

    /** @param  array<string, string>  $operation */
    public function build(array $operation, int $preparedBy): BuiltVoucher
    {
        $op = $operation + self::BLANK;
        $op = array_map(fn (string $value) => trim($value), $op);

        if (! in_array($op['kind'], self::KINDS, true)) {
            return new BuiltVoucher(null, [], [__('ledger.entry.bad_kind')]);
        }

        $cash = $op['cash'] !== '' ? GlAccount::query()->whereKey($op['cash'])->first() : null;
        if ($cash === null || ! $cash->is_cash) {
            return new BuiltVoucher(null, [], [__('ledger.entry.bad_cash')]);
        }

        $budgetLine = $op['budget_line'] !== '' ? GlBudgetLine::query()->whereKey($op['budget_line'])->first() : null;
        $project = $op['project'] !== '' ? $op['project'] : ($budgetLine !== null ? $budgetLine->project_code : '');
        $currency = $this->currencies->isActive((string) $cash->currency) ? $cash->currency : Currencies::BASE;
        $fund = $op['fund_source'] !== '' ? $op['fund_source'] : 'donor_grant';

        $row = fn (string $account, string $side, string $amount, string $rate = '', string $line = '', ?string $curr = null) => [
            'account' => $account, 'currency' => $curr ?? $currency, 'side' => $side, 'amount' => $amount, 'rate' => $rate,
            'project' => $project, 'budget_line' => $line, 'fund_source' => $fund, 'contact' => $op['contact'],
        ];

        $description = $op['document'] !== '' ? trim($op['document'].' · '.$op['description'], ' ·') : $op['description'];

        switch ($op['kind']) {
            case 'income':
                $type = 'CRV';
                $rows = [$row($cash->code, 'debit', $op['amount'], $op['rate']), $row($op['account'], 'credit', $op['amount'], $op['rate'])];
                break;
            case 'expense':
                $type = 'CPV';
                $rows = [
                    $row($op['account'], 'debit', $op['amount'], $op['rate'], $op['budget_line']),
                    $row($cash->code, 'credit', $op['amount'], $op['rate']),
                ];
                break;
            case 'advance':
                $type = 'CPV';
                $rows = [$row(self::ADVANCE_ACCOUNT, 'debit', $op['amount'], $op['rate']), $row($cash->code, 'credit', $op['amount'], $op['rate'])];
                break;
            case 'repayment':
                $type = 'CRV';
                $rows = [$row($cash->code, 'debit', $op['amount'], $op['rate']), $row(self::ADVANCE_ACCOUNT, 'credit', $op['amount'], $op['rate'])];
                break;
            default:
                $type = 'TRF';
                $exchange = $this->exchange($op, $cash, $row);
                if (is_string($exchange)) {
                    return new BuiltVoucher(null, [], [$exchange]);
                }
                $rows = $exchange;
        }

        return $this->builder->build($type, $op['date'], $description, '', $rows, $preparedBy);
    }

    /**
     * The two lines of an exchange: the cash account given (credit, the typed amount in its own currency) and the cash
     * account received (debit, the amount converted at the typed rate: pounds = dollars x rate).
     *
     * @param  array<string, string>  $op
     * @param  callable(string, string, string, string, string, ?string): array<string, string>  $row
     * @return list<array<string, string>>|string the rows, or the problem
     */
    private function exchange(array $op, GlAccount $given, callable $row): array|string
    {
        $received = $op['to_cash'] !== '' ? GlAccount::query()->whereKey($op['to_cash'])->first() : null;
        if ($received === null || ! $received->is_cash || ! $this->currencies->isActive((string) $given->currency) || ! $this->currencies->isActive((string) $received->currency) || $received->currency === $given->currency || ($given->currency !== Currencies::BASE && $received->currency !== Currencies::BASE)) {
            return __('ledger.entry.bad_exchange');
        }

        try {
            $amount = Money::normalize(str_replace([',', ' '], '', $op['amount']));
            $rate = Money::normalize(str_replace([',', ' '], '', $op['rate']), 6);
        } catch (InvalidArgumentException) {
            return __('ledger.entry.bad_exchange_amount');
        }

        if (bccomp($amount, '0', 2) <= 0 || bccomp($rate, '0', 6) <= 0) {
            return __('ledger.entry.bad_exchange_amount');
        }

        if ($given->currency === Currencies::BASE) {
            $converted = $this->currencies->fromUsd((string) $received->currency, $amount, $rate);

            return [
                $row($received->code, 'debit', $converted, $rate, '', (string) $received->currency),
                $row($given->code, 'credit', $amount, '', '', 'USD'),
            ];
        }

        $converted = $this->currencies->toUsd((string) $given->currency, $amount, $rate);

        return [
            $row($received->code, 'debit', $converted, '', '', 'USD'),
            $row($given->code, 'credit', $amount, $rate, '', (string) $given->currency),
        ];
    }
}
