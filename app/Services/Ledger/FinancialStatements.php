<?php

namespace App\Services\Ledger;

use App\Models\GlAccount;
use App\Services\Treasury\DailyValuation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The two financial statements, built from approved vouchers and grouped by each account's `statement_line`.
 *
 * Position: assets, liabilities and net assets as at a date. Net assets also carry the result of income and
 * expenses not yet closed into them, so the statement balances. Activities: income and expenses over a period
 * (5503 exchange differences sit under financial and banking costs, D11). Both take an optional comparative.
 *
 * Euros and other money kept in a currency the day's rate values (DailyValuation) show at that rate on the date, not
 * at their book dollars; the gap sits on 5503 as an unrealised exchange difference, and nothing is posted for it.
 *
 * The balance check always runs on USD. In a foreign-currency view (SYP, EUR, ...) a USD amount is converted at the typed rate and a line in that
 * currency stays as typed (decision of round 2). Accounts with no statement line (headers) never appear.
 *
 * @phpstan-type Line array{label: string, current: numeric-string, previous: numeric-string|null}
 * @phpstan-type Section array{key: string, lines: list<Line>, current: numeric-string, previous: numeric-string|null}
 */
class FinancialStatements
{
    /** The exchange differences account, where the estimate's gap shows as unrealised. */
    public const EXCHANGE_DIFFERENCES = '5503';

    public function __construct(private readonly Currencies $currencies) {}

    /**
     * @return array{sections: list<Section>, liabilities_and_net_assets: array{current: numeric-string, previous: numeric-string|null}, balanced: bool, difference: numeric-string}
     */
    public function position(?string $asAt, ?string $compareAsAt = null, string $currency = 'USD', ?string $rate = null): array
    {
        $current = $this->nets(null, $asAt, $currency, $rate);
        $previous = $compareAsAt !== null ? $this->nets(null, $compareAsAt, $currency, $rate) : null;

        $sections = [];
        foreach (['assets' => 1, 'liabilities' => -1, 'net_assets' => -1] as $class => $sign) {
            $sections[$class] = $this->section($class, $sign, $current['view'], $previous['view'] ?? null);
        }

        $sections['net_assets']['lines'][] = [
            'label' => '__unclosed_result__',
            'current' => $this->result($current['view']),
            'previous' => $previous !== null ? $this->result($previous['view']) : null,
        ];
        $sections['net_assets'] = $this->total($sections['net_assets'], $previous !== null);

        $combined = [
            'current' => bcadd($sections['liabilities']['current'], $sections['net_assets']['current'], 2),
            'previous' => $this->combine($sections['liabilities']['previous'], $sections['net_assets']['previous']),
        ];

        $difference = bcsub(
            $this->sumClass($current['usd'], 'assets'),
            bcadd(bcmul($this->sumClass($current['usd'], 'liabilities'), '-1', 2), bcadd(bcmul($this->sumClass($current['usd'], 'net_assets'), '-1', 2), $this->result($current['usd']), 2), 2),
            2
        );

        return [
            'sections' => array_values($sections),
            'liabilities_and_net_assets' => $combined,
            'balanced' => bccomp($difference, '0', 2) === 0,
            'difference' => $difference,
        ];
    }

    /**
     * @return array{sections: list<Section>, surplus: array{current: numeric-string, previous: numeric-string|null}}
     */
    public function activities(?string $from, ?string $to, ?string $compareFrom = null, ?string $compareTo = null, string $currency = 'USD', ?string $rate = null, bool $compare = false): array
    {
        $current = $this->nets($from, $to, $currency, $rate)['view'];
        $previous = $compare ? $this->nets($compareFrom, $compareTo, $currency, $rate)['view'] : null;

        $income = $this->section('income', -1, $current, $previous);
        $expenses = $this->section('expenses', 1, $current, $previous);

        return [
            'sections' => [$income, $expenses],
            'surplus' => [
                'current' => bcsub($income['current'], $expenses['current'], 2),
                'previous' => $this->combine($income['previous'], $expenses['previous'], subtract: true),
            ],
        ];
    }

    /**
     * Debit minus credit per posting account, in USD and in the view currency.
     *
     * @return array{usd: array<string, array{class: string, line: string, net: numeric-string}>, view: array<string, array{class: string, line: string, net: numeric-string}>}
     */
    private function nets(?string $from, ?string $to, string $currency, ?string $rate): array
    {
        $code = $this->currencies->isActive($currency) ? $currency : Currencies::BASE;
        $syp = $code !== Currencies::BASE;
        $rate = $syp && is_numeric($rate) && bccomp((string) $rate, '0', 6) > 0 ? number_format((float) $rate, 6, '.', '') : '1.000000';

        $sums = DB::table('gl_voucher_lines as l')
            ->join('gl_vouchers as v', 'v.id', '=', 'l.voucher_id')
            ->where('v.status', 'approved')
            ->whereNull('v.deleted_at')
            ->when($from !== null && $from !== '', fn ($query) => $query->where('v.date', '>=', $from))
            ->when($to !== null && $to !== '', fn ($query) => $query->where('v.date', '<=', $to))
            ->groupBy('l.account_code')
            ->selectRaw(
                'l.account_code as code, sum(l.debit_usd - l.credit_usd) as net_usd, '
                .'sum(case when l.currency = ? then l.debit_orig - l.credit_orig else 0 end) as net_syp, '
                .'sum(case when l.currency = ? then 0 else l.debit_usd - l.credit_usd end) as net_other',
                [$code, $code]
            )
            ->get()->keyBy('code');

        $usd = [];
        $view = [];
        $accounts = GlAccount::query()->whereNotNull('statement_line')->orderBy('code')->get()->filter(fn (GlAccount $account) => $sums->has($account->code));

        foreach ($accounts as $account) {
            $sum = $sums[$account->code];
            $base = ['class' => $account->class->value, 'line' => (string) $account->statement_line];
            $usd[$account->code] = $base + ['net' => $this->money($sum->net_usd)];
            $view[$account->code] = $base + ['net' => $syp ? bcadd($this->money($sum->net_syp), $this->currencies->fromUsd($code, $this->money($sum->net_other), $rate), 2) : $this->money($sum->net_usd)];
        }

        foreach ($this->estimates($from, $to) as $account => $change) {
            $inViewCurrency = $syp && $this->accountCurrency($account) === $code;
            $this->addTo($usd, $account, $change);
            // A line already in the view currency shows its amount, which the estimate does not change.
            $this->addTo($view, $account, $inViewCurrency ? '0.00' : ($syp ? $this->currencies->fromUsd($code, $change, $rate) : $change));
            $this->addTo($usd, self::EXCHANGE_DIFFERENCES, bcmul($change, '-1', 2));
            $this->addTo($view, self::EXCHANGE_DIFFERENCES, bcmul($syp ? $this->currencies->fromUsd($code, $change, $rate) : $change, '-1', 2));
        }

        return ['usd' => $usd, 'view' => $view];
    }

    /**
     * The change over the period of the day's estimate of euros and other daily-valued money (DailyValuation): what is
     * added to each account's book dollars to show it at the day's rate. As at a date, the whole gap; over a period,
     * its change from the day before the period starts.
     *
     * @return array<string, numeric-string> account code => dollars to add
     */
    private function estimates(?string $from, ?string $to): array
    {
        $valuation = app(DailyValuation::class);
        $end = $to !== null && $to !== '' ? CarbonImmutable::parse($to) : CarbonImmutable::today();
        $changes = array_map(fn (array $row): string => $row['difference'], $valuation->asAt($end));

        if ($from !== null && $from !== '') {
            foreach ($valuation->asAt(CarbonImmutable::parse($from)->subDay()) as $account => $row) {
                $changes[$account] = bcsub($changes[$account] ?? '0.00', $row['difference'], 2);
            }
        }

        return array_filter($changes, fn (string $change): bool => bccomp($change, '0', 2) !== 0);
    }

    /** @param  array<string, array{class: string, line: string, net: numeric-string}>  $nets */
    private function addTo(array &$nets, string $code, string $amount): void
    {
        if (! isset($nets[$code])) {
            $account = GlAccount::query()->find($code);
            if ($account === null || $account->statement_line === null) {
                return;
            }
            $nets[$code] = ['class' => $account->class->value, 'line' => (string) $account->statement_line, 'net' => '0.00'];
        }

        $nets[$code]['net'] = bcadd($nets[$code]['net'], $amount, 2);
    }

    private function accountCurrency(string $code): ?string
    {
        return GlAccount::query()->whereKey($code)->value('currency');
    }

    /**
     * One class: its statement lines in chart order. A line that is zero in both periods is left out.
     *
     * @param  array<string, array{class: string, line: string, net: numeric-string}>  $current
     * @param  array<string, array{class: string, line: string, net: numeric-string}>|null  $previous
     * @return Section
     */
    private function section(string $class, int $sign, array $current, ?array $previous): array
    {
        $lines = [];
        $labels = [];

        foreach ([$current, $previous ?? []] as $nets) {
            foreach ($nets as $code => $entry) {
                if ($entry['class'] === $class) {
                    $labels[$entry['line']] = min($labels[$entry['line']] ?? $code, $code);
                }
            }
        }
        asort($labels);

        foreach (array_keys($labels) as $label) {
            $now = $this->sumLine($current, $class, $label, $sign);
            $before = $previous !== null ? $this->sumLine($previous, $class, $label, $sign) : null;

            if (bccomp($now, '0', 2) === 0 && ($before === null || bccomp($before, '0', 2) === 0)) {
                continue;
            }

            $lines[] = ['label' => $label, 'current' => $now, 'previous' => $before];
        }

        return $this->total(['key' => $class, 'lines' => $lines, 'current' => '0.00', 'previous' => null], $previous !== null);
    }

    /**
     * @param  Section  $section
     * @return Section
     */
    private function total(array $section, bool $withPrevious): array
    {
        $section['current'] = '0.00';
        $section['previous'] = $withPrevious ? '0.00' : null;

        foreach ($section['lines'] as $line) {
            $section['current'] = bcadd($section['current'], $line['current'], 2);
            $section['previous'] = $this->combine($section['previous'], $line['previous']);
        }

        return $section;
    }

    /**
     * @param  array<string, array{class: string, line: string, net: numeric-string}>  $nets
     * @return numeric-string
     */
    private function sumLine(array $nets, string $class, string $label, int $sign): string
    {
        $sum = '0.00';
        foreach ($nets as $entry) {
            if ($entry['class'] === $class && $entry['line'] === $label) {
                $sum = bcadd($sum, $entry['net'], 2);
            }
        }

        return $sign < 0 ? bcmul($sum, '-1', 2) : $sum;
    }

    /**
     * @param  array<string, array{class: string, line: string, net: numeric-string}>  $nets
     * @return numeric-string
     */
    private function sumClass(array $nets, string $class): string
    {
        $sum = '0.00';
        foreach ($nets as $entry) {
            if ($entry['class'] === $class) {
                $sum = bcadd($sum, $entry['net'], 2);
            }
        }

        return $sum;
    }

    /**
     * Income minus expenses over the whole nets given (credit side positive).
     *
     * @param  array<string, array{class: string, line: string, net: numeric-string}>  $nets
     * @return numeric-string
     */
    private function result(array $nets): string
    {
        return bcmul(bcadd($this->sumClass($nets, 'income'), $this->sumClass($nets, 'expenses'), 2), '-1', 2);
    }

    /**
     * Sum or difference of two amounts; null when either period is missing.
     *
     * @param  numeric-string|null  $first
     * @param  numeric-string|null  $second
     * @return numeric-string|null
     */
    private function combine(?string $first, ?string $second, bool $subtract = false): ?string
    {
        if ($first === null || $second === null) {
            return null;
        }

        return $subtract ? bcsub($first, $second, 2) : bcadd($first, $second, 2);
    }

    /** @return numeric-string */
    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
