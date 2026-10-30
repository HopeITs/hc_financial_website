<?php

namespace App\Services\Ledger;

use App\Enums\AccountClass;
use App\Enums\AccountLevel;
use App\Enums\FundSource;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\GlAccount;
use App\Models\GlProject;
use App\Models\Voucher;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Opening balances (F-05, decision R5-2): one opening voucher (OPN) per city when work starts on the program.
 *
 * The person types what each asset and liability account holds for each owner (project or general account) on the
 * opening date, on its normal side: an asset is a debit, a liability a credit, a negative amount the other side. Net
 * assets are never typed (R5-3, rule 16): for each owner the program writes the difference, assets minus liabilities, on
 * 3101 for an owner without donor restrictions and on 3201 for one with them, so the voucher always balances. An owner
 * whose restriction is unknown goes to 3901, which must be zero: such a voucher is refused.
 *
 * It never posts and never throws on bad input: it reports problems and returns no draft.
 */
class OpeningBalances
{
    public const UNRESTRICTED_ACCOUNT = '3101';

    public const RESTRICTED_ACCOUNT = '3201';

    public const SUSPENSE_ACCOUNT = '3901';

    public function __construct(private readonly Currencies $currencies) {}

    /**
     * @param  list<array{account?: string, project?: string, amount?: string, rate?: string, contact?: string, center?: string}>  $rows
     */
    public function build(string $city, string $date, array $rows, int $preparedBy): BuiltOpening
    {
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? CarbonImmutable::createFromFormat('!Y-m-d', $date) : false;
        if (! $day instanceof CarbonImmutable || $day->format('Y-m-d') !== $date) {
            return $this->failed(__('ledger.builder.bad_date'));
        }

        if (trim($city) === '') {
            return $this->failed(__('ledger.opening.bad_city'));
        }

        $existing = $this->existing($city);
        if ($existing !== null) {
            return $this->failed(__('ledger.opening.already', ['city' => $city, 'voucher' => $existing->id]));
        }

        $filled = array_values(array_filter($rows, fn (array $row) => trim($row['account'] ?? '') !== '' || trim($row['amount'] ?? '') !== ''));
        if ($filled === []) {
            return $this->failed(__('ledger.opening.no_rows'));
        }

        $accounts = GlAccount::query()->whereIn('code', array_column($filled, 'account'))->get()->keyBy('code');
        $projects = GlProject::query()->whereIn('code', array_column($filled, 'project'))->get()->keyBy('code');

        $problems = [];
        $lines = [];
        /** @var array<string, numeric-string> $nets owner => assets minus liabilities in USD */
        $nets = [];

        foreach ($filled as $index => $row) {
            $n = $index + 1;
            $account = $accounts->get(trim($row['account'] ?? ''));
            $project = $projects->get(trim($row['project'] ?? ''));

            if ($account === null || $account->level !== AccountLevel::Posting || ! $account->is_active || ! in_array($account->class, [AccountClass::Assets, AccountClass::Liabilities], true)) {
                $problems[] = __('ledger.opening.bad_account', ['line' => $n, 'account' => $row['account'] ?? '']);

                continue;
            }
            if ($account->city !== null && $account->city !== '' && $account->city !== $city) {
                $problems[] = __('ledger.opening.other_city', ['line' => $n, 'account' => $account->code, 'city' => $account->city]);

                continue;
            }
            if ($project === null) {
                $problems[] = __('ledger.opening.bad_project', ['line' => $n]);

                continue;
            }

            $amount = $this->amount($row['amount'] ?? '');
            if ($amount === null || bccomp($amount, '0', 2) === 0) {
                $problems[] = __('ledger.opening.bad_amount', ['line' => $n]);

                continue;
            }

            $currency = $this->currencies->isActive((string) $account->currency) ? (string) $account->currency : Currencies::BASE;
            $rate = '1.000000';
            $abs = Money::abs($amount);
            $usd = $abs;
            if ($currency !== Currencies::BASE) {
                $rate = $this->rate($row['rate'] ?? '');
                if ($rate === null) {
                    $problems[] = __('ledger.opening.bad_rate', ['line' => $n, 'currency' => $currency]);

                    continue;
                }
                $usd = $this->currencies->toUsd($currency, $abs, $rate);
            }

            // An asset holds a debit and a liability a credit; a negative amount is on the other side.
            $debit = ($account->class === AccountClass::Assets) === (bccomp($amount, '0', 2) > 0);
            $lines[] = $this->line($account->code, $currency, $debit, $abs, $rate, $usd, $project, $city, $row);

            $signed = $debit ? $usd : bcsub('0', $usd, 2);
            $nets[$project->code] = bcadd($nets[$project->code] ?? '0.00', $signed, 2);
        }

        if ($problems !== []) {
            return new BuiltOpening(null, $problems);
        }

        $netAssets = ['unrestricted' => Money::zero(), 'restricted' => Money::zero(), 'unknown' => Money::zero()];
        foreach ($nets as $code => $net) {
            if (bccomp($net, '0', 2) === 0) {
                continue;
            }

            $project = $projects->get($code);
            [$account, $part] = match ($project?->restriction) {
                NetAssets::RESTRICTED => [self::RESTRICTED_ACCOUNT, 'restricted'],
                'Without donor restrictions' => [self::UNRESTRICTED_ACCOUNT, 'unrestricted'],
                default => [self::SUSPENSE_ACCOUNT, 'unknown'],
            };
            $netAssets[$part] = bcadd($netAssets[$part], $net, 2);

            // Positive net assets sit on the credit side, opposite the assets they balance.
            $lines[] = $this->line($account, Currencies::BASE, bccomp($net, '0', 2) < 0, Money::abs($net), '1.000000', Money::abs($net), $project, $city, []);
        }

        if (bccomp($netAssets['unknown'], '0', 2) !== 0) {
            return new BuiltOpening(null, [__('ledger.opening.suspense_not_zero', ['amount' => number_format((float) $netAssets['unknown'], 2)])], $netAssets);
        }

        $draft = new VoucherDraft(
            type: VoucherType::Opening, date: $day, preparedBy: $preparedBy, lines: $lines,
            description: __('ledger.opening.description', ['city' => $city]),
        );

        return new BuiltOpening($draft, [], $netAssets);
    }

    /** The city's opening voucher, pending or approved, when there is one: a city opens once. */
    public function existing(string $city): ?Voucher
    {
        return Voucher::query()
            ->where('type', VoucherType::Opening->value)
            ->whereIn('status', [VoucherStatus::PendingApproval->value, VoucherStatus::Approved->value])
            ->whereIn('id', DB::table('gl_voucher_lines')->where('city', $city)->select('voucher_id'))
            ->first();
    }

    /** @return Collection<int, GlAccount> the asset and liability accounts a city can open: its own and those of no city */
    public function accounts(string $city): Collection
    {
        return GlAccount::query()
            ->where('level', 'posting')->where('is_active', true)
            ->whereIn('class', [AccountClass::Assets->value, AccountClass::Liabilities->value])
            ->where(fn ($query) => $query->whereNull('city')->orWhere('city', '')->orWhere('city', $city))
            ->orderBy('code')->get();
    }

    /** @param  array{contact?: string, center?: string}  $row */
    private function line(string $account, string $currency, bool $debit, string $amount, string $rate, string $usd, ?GlProject $project, string $city, array $row): LineDraft
    {
        $contact = trim($row['contact'] ?? '');
        $center = trim($row['center'] ?? '');
        $donorAmount = $usd;
        if ($project !== null && $project->donor_currency !== 'USD' && bccomp(Money::normalize($project->donor_rate, 6), '0', 6) > 0) {
            $donorAmount = Money::normalize(bcdiv($usd, Money::normalize($project->donor_rate, 6), 6));
        }

        return new LineDraft(
            accountCode: $account,
            currency: $currency,
            debit: $debit ? $amount : null,
            credit: $debit ? null : $amount,
            rate: $rate,
            debitUsd: $debit ? $usd : null,
            creditUsd: $debit ? null : $usd,
            projectCode: $project?->code,
            fundSource: $project === null ? null : ($project->is_general_account ? FundSource::Treasury : FundSource::DonorGrant),
            contact: $contact !== '' ? $contact : null,
            center: $center !== '' ? $center : null,
            donor: $project?->donor,
            city: $city,
            restriction: $project?->restriction,
            donorCurrency: $project?->donor_currency,
            debitDonor: $project !== null && $debit ? $donorAmount : null,
            creditDonor: $project !== null && ! $debit ? $donorAmount : null,
        );
    }

    private function failed(string $problem): BuiltOpening
    {
        return new BuiltOpening(null, [$problem]);
    }

    /** @return numeric-string|null */
    private function amount(string $text): ?string
    {
        try {
            return Money::normalize(str_replace([',', ' '], '', trim($text)));
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /** @return numeric-string|null */
    private function rate(string $text): ?string
    {
        try {
            $rate = Money::normalize(str_replace([',', ' '], '', trim($text)), 6);
        } catch (InvalidArgumentException) {
            return null;
        }

        return bccomp($rate, '0', 6) > 0 ? $rate : null;
    }
}
