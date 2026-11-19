<?php

namespace App\Services\Entries;

use App\Enums\AccountClass;
use App\Enums\FundSource;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\GlAccount;
use App\Models\GlBudgetLine;
use App\Models\GlProject;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Ledger\Currencies;
use App\Services\Ledger\LedgerRuleException;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\PostVoucher;
use App\Services\Ledger\Violation;
use App\Services\Ledger\VoucherAttachments;
use App\Services\Ledger\VoucherDraft;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * Advances to staff (finance design of 2026-11-19), the way accounting programs handle expense claims:
 *
 * - Giving one is a payment voucher: debit 1201 operational advances on the project and the person, credit the cash or
 *   bank account (CashVouchers, at the month's rate unless changed).
 * - An advance is open per person, project and currency until its bills cover it.
 * - Settling it is one journal voucher: the bills on their budget lines (debit), the advance (credit 1201), and the
 *   difference in cash: what the person hands back (debit cash), or what they are paid on top when the bills are more
 *   (credit cash). The person may also keep the rest open for bills still to come. Every line takes the rate the advance
 *   was given at, so the advance clears exactly and the bills cost what the money cost.
 */
class Advances
{
    public const ACCOUNT = '1201';

    public function __construct(
        private readonly PostVoucher $posting,
        private readonly Currencies $currencies,
        private readonly CashVouchers $cash,
        private readonly VoucherAttachments $attachments,
    ) {}

    /**
     * The advances still open, from every voucher that stands (pending or approved), oldest first.
     *
     * @return list<array{key: string, person: string, project: string, currency: string, advanced: string, settled: string, open: string, open_usd: string, rate: string, since: string, days: int, pending: bool}>
     */
    public function open(?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $rows = DB::table('gl_voucher_lines as l')
            ->join('gl_vouchers as v', 'v.id', '=', 'l.voucher_id')
            ->where('l.account_code', self::ACCOUNT)
            ->where('v.status', '!=', VoucherStatus::Replaced->value)
            ->whereNull('v.deleted_at')
            ->groupBy('l.contact', 'l.project_code', 'l.currency')
            ->selectRaw('l.contact as person, l.project_code as project, l.currency as currency, sum(l.debit_orig) as advanced, sum(l.credit_orig) as settled, sum(l.debit_usd) - sum(l.credit_usd) as usd, min(v.date) as since, sum(case when v.status = ? then 1 else 0 end) as pending', [VoucherStatus::PendingApproval->value])
            ->orderBy('since')
            ->get();

        $open = [];

        foreach ($rows as $row) {
            $advanced = $this->money($row->advanced);
            $settled = $this->money($row->settled);
            $balance = bcsub($advanced, $settled, 2);

            if (bccomp($balance, '0', 2) !== 1) {
                continue;
            }

            $usd = $this->money($row->usd);
            $since = CarbonImmutable::parse(substr((string) $row->since, 0, 10));

            $open[] = [
                'key' => $this->key((string) $row->person, (string) $row->project, (string) $row->currency),
                'person' => (string) $row->person,
                'project' => (string) $row->project,
                'currency' => (string) $row->currency,
                'advanced' => $advanced,
                'settled' => $settled,
                'open' => $balance,
                'open_usd' => $usd,
                'rate' => $this->currencies->rateOf((string) $row->currency, $balance, $usd) ?? '1.000000',
                'since' => $since->toDateString(),
                'days' => (int) $since->diffInDays($today),
                'pending' => (int) $row->pending > 0,
            ];
        }

        return $open;
    }

    /**
     * Gives an advance: a payment voucher on 1201 for the person and the project.
     *
     * @param  array{date: string, account: string, rate: string, person: string, project: string, amount: string, purpose: string, request: string, center: string}  $form
     * @param  iterable<TemporaryUploadedFile>  $files
     *
     * @throws EntryException
     */
    public function give(array $form, iterable $files, User $by): Voucher
    {
        if (trim($form['project']) === '') {
            throw new EntryException([__('advances.needs_project')]);
        }

        return $this->cash->save(CashVouchers::PAYMENT, [
            'date' => $form['date'], 'account' => $form['account'], 'rate' => $form['rate'], 'party' => $form['person'],
            'reference' => '', 'center' => $form['center'], 'request' => $form['request'],
            'description' => __('advances.give_description', ['person' => trim($form['person']), 'purpose' => trim($form['purpose'])]),
        ], [[
            'project' => $form['project'], 'budget_line' => '', 'account' => self::ACCOUNT, 'description' => trim($form['purpose']), 'amount' => $form['amount'],
        ]], $files, $by);
    }

    /**
     * What a settlement would do, without saving it.
     *
     * @param  list<array{project?: string, budget_line?: string, account?: string, description?: string, amount?: string}>  $bills
     * @return array{advance: array<string, mixed>|null, billed: string, back: string, extra: string, left: string, draft: VoucherDraft|null, problems: list<string>}
     */
    public function preview(string $key, string $date, array $bills, bool $close, string $cashAccount, string $center, string $note): array
    {
        $advance = collect($this->open())->firstWhere('key', $key);
        $result = ['advance' => $advance, 'billed' => '0.00', 'back' => '0.00', 'extra' => '0.00', 'left' => '0.00', 'draft' => null, 'problems' => []];

        if ($advance === null) {
            return [...$result, 'problems' => [__('advances.not_open')]];
        }

        $problems = [];
        $day = $this->day($date);
        if ($day === null) {
            $problems[] = __('entries.bad_date');
        }

        $currency = $advance['currency'];
        $rate = $advance['rate'];
        $accounts = GlAccount::query()->where('is_active', true)->where('is_cash', false)->where('class', '!=', AccountClass::NetAssets->value)->get()->keyBy('code');
        $budgetLines = GlBudgetLine::query()->whereIn('key', collect($bills)->pluck('budget_line')->filter()->unique()->values())->get()->keyBy('key');
        $projects = GlProject::query()->whereIn('code', collect($bills)->pluck('project')->filter()->push($advance['project'])->unique()->values())->get()->keyBy('code');
        $lines = [];
        $billed = Money::zero();
        $billedUsd = Money::zero();
        $number = 0;

        foreach ($bills as $bill) {
            $amountText = trim((string) ($bill['amount'] ?? ''));
            if ($amountText === '' && trim((string) ($bill['budget_line'] ?? '')) === '' && trim((string) ($bill['account'] ?? '')) === '') {
                continue;
            }

            $number++;
            $budgetLine = $budgetLines->get((string) ($bill['budget_line'] ?? ''));
            $projectCode = trim((string) ($bill['project'] ?? '')) ?: ($budgetLine?->project_code ?? $advance['project']);
            $accountCode = $budgetLine?->account_code ?? trim((string) ($bill['account'] ?? ''));
            $amount = $this->number($amountText);

            if ($amount === null || bccomp($amount, '0', 2) !== 1) {
                $problems[] = __('entries.line_amount', ['line' => $number]);

                continue;
            }

            if (! $accounts->has($accountCode)) {
                $problems[] = __('entries.line_account', ['line' => $number]);

                continue;
            }

            $usd = $this->currencies->toUsd($currency, $amount, $rate);
            $billed = bcadd($billed, $amount, 2);
            $billedUsd = bcadd($billedUsd, $usd, 2);
            $project = $projects->get($projectCode);
            $tags = (array) $accounts[$accountCode]->required_tags;

            $lines[] = new LineDraft(
                accountCode: $accountCode, currency: $currency, debit: $amount, rate: $rate, debitUsd: $usd,
                projectCode: $project?->code, budgetLineKey: $budgetLine?->key,
                fundSource: $project !== null ? FundSource::DonorGrant : null,
                contact: $advance['person'],
                center: in_array('Center', $tags, true) ? (trim($center) ?: null) : null,
                donor: $project?->donor, restriction: $project?->restriction, reason: trim((string) ($bill['description'] ?? '')) ?: null,
                donorCurrency: $project?->donor_currency, debitDonor: $project !== null ? $this->donorAmount($usd, $project) : null,
            );
        }

        if ($number === 0) {
            $problems[] = __('advances.needs_bills');
        }

        $open = $advance['open'];
        $back = $close && bccomp($billed, $open, 2) === -1 ? bcsub($open, $billed, 2) : '0.00';
        $extra = bccomp($billed, $open, 2) === 1 ? bcsub($billed, $open, 2) : '0.00';
        $left = ! $close && bccomp($billed, $open, 2) === -1 ? bcsub($open, $billed, 2) : '0.00';
        $cash = bccomp($back, '0', 2) === 1 || bccomp($extra, '0', 2) === 1 ? $this->cash->cashAccounts()->get($cashAccount) : null;

        if ((bccomp($back, '0', 2) === 1 || bccomp($extra, '0', 2) === 1) && ($cash === null || $cash->currency !== $currency)) {
            $problems[] = __('advances.needs_cash', ['currency' => $currency]);
        }

        $result = [...$result, 'billed' => $billed, 'back' => $back, 'extra' => $extra, 'left' => $left];

        if ($problems !== [] || $day === null) {
            return [...$result, 'problems' => $problems];
        }

        // The advance clears at its own dollars when it is closed, else in proportion to what the bills cover.
        $cleared = bccomp($extra, '0', 2) === 1 || bccomp($left, '0', 2) === 0 ? $open : $billed;
        $clearedUsd = bccomp($cleared, $open, 2) === 0 ? $advance['open_usd'] : $this->currencies->toUsd($currency, $cleared, $rate);
        $lines[] = new LineDraft(self::ACCOUNT, $currency, credit: $cleared, rate: $rate, creditUsd: $clearedUsd, projectCode: $advance['project'],
            fundSource: $projects->get($advance['project']) !== null ? FundSource::DonorGrant : null, contact: $advance['person']);

        if ($cash !== null) {
            $cashTags = (array) $cash->required_tags;
            $amount = bccomp($back, '0', 2) === 1 ? $back : $extra;
            $usd = bccomp($back, '0', 2) === 1 ? bcsub($clearedUsd, $billedUsd, 2) : $this->currencies->toUsd($currency, $extra, $rate);
            $returned = bccomp($back, '0', 2) === 1;
            $lines[] = new LineDraft($cash->code, $currency, debit: $returned ? $amount : null, credit: $returned ? null : $amount, rate: $rate,
                debitUsd: $returned ? $usd : null, creditUsd: $returned ? null : $usd,
                contact: $cash->code === (string) config('ledger.transfer_company_account') || in_array('Contact', $cashTags, true) ? $advance['person'] : null,
                center: in_array('Center', $cashTags, true) ? (trim($center) ?: null) : null);
        }

        $draft = new VoucherDraft(
            type: VoucherType::Journal,
            date: $day,
            preparedBy: 0,
            lines: $lines,
            description: trim($note) ?: __('advances.settle_description', ['person' => $advance['person'], 'project' => $advance['project']]),
        );

        $problems = array_map(fn (Violation $violation): string => $violation->message, $this->posting->check($draft));

        return [...$result, 'draft' => $problems === [] ? $draft : null, 'problems' => $problems];
    }

    /**
     * Saves the settlement as one journal voucher pending approval, with the bills attached.
     *
     * @param  list<array<string, string>>  $bills
     * @param  iterable<TemporaryUploadedFile>  $files
     *
     * @throws EntryException
     */
    public function settle(string $key, string $date, array $bills, bool $close, string $cashAccount, string $center, string $note, iterable $files, User $by): Voucher
    {
        $built = $this->preview($key, $date, $bills, $close, $cashAccount, $center, $note);

        if ($built['draft'] === null) {
            throw new EntryException($built['problems']);
        }

        $draft = $built['draft'];
        $draft = new VoucherDraft(type: $draft->type, date: $draft->date, preparedBy: (int) $by->getAuthIdentifier(), lines: $draft->lines, description: $draft->description);
        $stored = [];

        try {
            return DB::transaction(function () use ($draft, $files, $by, &$stored): Voucher {
                $voucher = $this->posting->post($draft);
                $stored = $this->attachments->store($voucher->id, $files, (int) $by->getAuthIdentifier());

                return $voucher;
            });
        } catch (LedgerRuleException $refused) {
            throw new EntryException(array_map(fn (Violation $violation): string => $violation->message, $refused->violations));
        } catch (Throwable $failed) {
            $this->attachments->forget($stored);

            throw $failed;
        }
    }

    /** A safe key for an open advance (person, project, currency), for the screen to send back. */
    public function key(string $person, string $project, string $currency): string
    {
        return md5($person."\u{1F}".$project."\u{1F}".$currency);
    }

    /** @return numeric-string|null */
    private function donorAmount(string $usd, GlProject $project): ?string
    {
        $rate = Money::normalize($project->donor_rate ?? '0', 6);

        if ($project->donor_currency === null || $project->donor_currency === Currencies::BASE || bccomp($rate, '0', 6) !== 1) {
            return $usd;
        }

        return Money::normalize(bcdiv($usd, $rate, 6));
    }

    private function day(string $text): ?CarbonImmutable
    {
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) === 1 ? CarbonImmutable::createFromFormat('!Y-m-d', $text) : false;

        return $day instanceof CarbonImmutable && $day->format('Y-m-d') === $text ? $day : null;
    }

    /** @return numeric-string|null */
    private function number(string $text): ?string
    {
        if (trim($text) === '') {
            return null;
        }

        try {
            return Money::normalize(str_replace([',', ' '], '', trim($text)));
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /** @return numeric-string */
    private function money(mixed $value): string
    {
        return Money::normalize(number_format((float) $value, 2, '.', ''));
    }
}
