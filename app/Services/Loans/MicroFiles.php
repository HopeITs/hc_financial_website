<?php

namespace App\Services\Loans;

use App\Enums\RoleId;
use App\Models\GlProject;
use App\Models\MicroItem;
use App\Models\MicroLoan;
use App\Models\MicroQuote;
use App\Models\User;
use App\Services\Approvals\ApprovalChain;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Micro-project files (finance decisions of 2026-11-23, from the micro-projects procedure). A beneficiary is first a
 * file with no voucher: the details, the purchase list (طلب مشتريات) and the price quotes. Its payment order (طلب صرف)
 * asks for the amount in the loan's currency, dollars or pounds (pounds worth a dollar value at a rate), with the
 * judicial seizure fee above 3,000 USD; the Program Manager, the Financial Manager and, above the threshold, the General
 * Manager approve it in turn. Only then can the purchases be paid (MicroPurchases).
 */
class MicroFiles
{
    /** The monthly instalment proposed for a loan: 0.5 % of its value (procedure 4, after the empowerment course). */
    public const INSTALMENT_SHARE = '0.005';

    /** A loan worth this many dollars or more carries the judicial seizure fee. */
    public const SEIZURE_FROM_USD = '3000.00';

    public const CURRENCIES = ['USD', 'SYP'];

    public function __construct(private readonly ApprovalChain $chain) {}

    /**
     * Creates or changes a file while it can be changed (new, or sent back).
     *
     * @param  array{beneficiary?: string, name_en?: string, national_number?: string, mobile?: string, partners?: string, project_number?: string, profession?: string, address?: string, funding?: string, currency?: string, rate?: string, requested_amount?: string, seizure_fee?: string, recovery_percent?: string, instalment_amount?: string, interval?: string}  $form
     * @param  list<array{name?: string, kind?: string, quantity?: string, condition?: string}>  $items
     *
     * @throws LoanException
     */
    public function save(User $by, ?MicroLoan $file, array $form, array $items): MicroLoan
    {
        if ($file !== null && ! in_array($file->stage, MicroLoan::EDITABLE, true)) {
            throw new LoanException([__('micro.problem.not_editable')]);
        }

        $problems = [];
        $text = fn (string $key, int $max = 255): ?string => ($value = trim((string) ($form[$key] ?? ''))) === '' ? null : mb_substr($value, 0, $max);
        $beneficiary = $text('beneficiary');
        $currency = (string) ($form['currency'] ?? '');
        $requested = $this->number((string) ($form['requested_amount'] ?? ''));
        $seizure = trim((string) ($form['seizure_fee'] ?? '')) === '' ? '0.00' : $this->number((string) $form['seizure_fee']);
        $recovery = $this->number((string) ($form['recovery_percent'] ?? '50'));
        $instalmentText = trim((string) ($form['instalment_amount'] ?? ''));
        $instalment = $instalmentText === '' ? null : $this->number($instalmentText);
        $interval = (int) ($form['interval'] ?? 1);
        $rate = null;

        if ($beneficiary === null) {
            $problems[] = __('loans.problem.beneficiary');
        }

        if (($mobile = $text('mobile', 50)) !== null && preg_match('/^[0-9+\-\s()]{5,50}$/', $mobile) !== 1) {
            $problems[] = __('loans.problem.mobile');
        }

        if (! in_array($currency, self::CURRENCIES, true)) {
            $problems[] = __('loans.problem.currency');
        }

        if ($currency === 'SYP') {
            $rate = $this->number((string) ($form['rate'] ?? ''), 6);
            if ($rate === null || bccomp($rate, '0', 6) <= 0) {
                $problems[] = __('loans.problem.rate');
            }
        }

        if ($requested === null || bccomp($requested, '0', 2) <= 0) {
            $problems[] = __('micro.problem.requested');
        }

        if ($seizure === null) {
            $problems[] = __('micro.problem.seizure');
        }

        if ($recovery === null || bccomp($recovery, '100', 2) > 0) {
            $problems[] = __('micro.problem.recovery');
        }

        if ($instalmentText !== '' && ($instalment === null || bccomp($instalment, '0', 2) <= 0)) {
            $problems[] = __('micro.problem.instalment');
        }

        if ($interval < 1 || $interval > 12) {
            $problems[] = __('loans.problem.interval');
        }

        [$projectCode, $funder, $city, $fundingProblem] = $this->funding((string) ($form['funding'] ?? ''));
        if ($fundingProblem !== null) {
            $problems[] = $fundingProblem;
        }

        $rows = [];
        foreach ($items as $index => $item) {
            $name = trim((string) ($item['name'] ?? ''));
            $quantity = trim((string) ($item['quantity'] ?? ''));
            if ($name === '' && $quantity === '' && trim((string) ($item['kind'] ?? '')) === '') {
                continue;
            }

            $count = $this->number($quantity === '' ? '1' : $quantity);
            if ($name === '' || $count === null || bccomp($count, '0', 2) <= 0) {
                $problems[] = __('micro.problem.item', ['line' => $index + 1]);

                continue;
            }

            $rows[] = [
                'name' => mb_substr($name, 0, 255), 'kind' => ($kind = trim((string) ($item['kind'] ?? ''))) === '' ? null : mb_substr($kind, 0, 255),
                'quantity' => $count, 'condition' => ($item['condition'] ?? '') === MicroItem::USED ? MicroItem::USED : MicroItem::NEW, 'sort' => count($rows) + 1,
            ];
        }

        if ($problems !== []) {
            throw new LoanException($problems);
        }

        /** @var numeric-string $requested */
        /** @var numeric-string $seizure */
        $total = bcadd($requested, $seizure, 2);

        return DB::transaction(function () use ($by, $file, $form, $text, $beneficiary, $mobile, $currency, $rate, $requested, $seizure, $recovery, $instalment, $interval, $projectCode, $funder, $city, $rows, $total): MicroLoan {
            $values = [
                'beneficiary' => $beneficiary, 'name_en' => $text('name_en'), 'national_number' => $text('national_number', 30), 'mobile' => $mobile,
                'partners' => $text('partners'), 'project_number' => $text('project_number', 40), 'profession' => $text('profession'), 'address' => $text('address'),
                'project_code' => $projectCode, 'funder' => $funder, 'city' => $city, 'currency' => $currency, 'rate' => $rate,
                'requested_amount' => $requested, 'seizure_fee' => $seizure, 'recovery_percent' => $recovery,
                'instalment_amount' => $instalment ?? $this->defaultInstalment($total), 'payment_interval_months' => $interval,
            ];

            if ($file === null) {
                $file = MicroLoan::query()->create($values + [
                    'stage' => MicroLoan::DRAFT, 'loan_amount' => '0.00', 'grant_amount' => '0.00', 'created_by' => (int) $by->getAuthIdentifier(),
                ]);
            } else {
                $file->update($values);
            }

            $file->items()->delete();
            foreach ($rows as $row) {
                $file->items()->create($row);
            }

            return $file->refresh();
        });
    }

    /**
     * The monthly instalment proposed for a loan's value.
     *
     * @param  numeric-string  $total
     * @return numeric-string
     */
    public function defaultInstalment(string $total): string
    {
        return Money::normalize(bcmul($total, self::INSTALMENT_SHARE, 6));
    }

    /**
     * What a file asks for, in dollars: its amount, the seizure fee and any extra amount (pounds at the file's rate).
     *
     * @return numeric-string
     */
    public function usdValue(MicroLoan $file): string
    {
        $total = bcadd(bcadd(Money::normalize($file->requested_amount ?? '0'), Money::normalize($file->seizure_fee), 2), Money::normalize($file->extra_amount), 2);

        if ($file->currency === 'USD') {
            return $total;
        }

        $rate = Money::normalize($file->rate ?? '0', 6);

        return bccomp($rate, '0', 6) > 0 ? Money::normalize(bcdiv($total, $rate, 6)) : '0.00';
    }

    /** Whether a file's amount reaches the judicial seizure fee and has none yet. */
    public function needsSeizureFee(MicroLoan $file): bool
    {
        $amount = $file->currency === 'USD' ? Money::normalize($file->requested_amount ?? '0')
            : (bccomp(Money::normalize($file->rate ?? '0', 6), '0', 6) > 0 ? Money::normalize(bcdiv(Money::normalize($file->requested_amount ?? '0'), Money::normalize($file->rate, 6), 6)) : '0.00');

        return bccomp($amount, self::SEIZURE_FROM_USD, 2) >= 0 && bccomp(Money::normalize($file->seizure_fee), '0', 2) === 0;
    }

    /**
     * Adds a price quote, with its scan when there is one.
     *
     * @throws LoanException
     */
    public function addQuote(User $by, MicroLoan $file, string $supplier, string $source, string $date, string $amount, string $currency, ?UploadedFile $scan): MicroQuote
    {
        $this->assertEditable($file);
        $problems = [];
        $value = $this->number($amount);
        $day = $this->day($date);

        if (trim($supplier) === '') {
            $problems[] = __('micro.problem.supplier');
        }

        if (! in_array($source, [MicroQuote::BENEFICIARY, MicroQuote::MERCHANT], true)) {
            $problems[] = __('micro.problem.source');
        }

        if ($value === null || bccomp($value, '0', 2) <= 0) {
            $problems[] = __('micro.problem.quote_amount');
        }

        if (! in_array($currency, self::CURRENCIES, true)) {
            $problems[] = __('loans.problem.currency');
        }

        if (trim($date) !== '' && $day === null) {
            $problems[] = __('loans.problem.date');
        }

        if ($problems !== []) {
            throw new LoanException($problems);
        }

        $stored = null;
        if ($scan !== null) {
            $stored = $scan->storeAs('micro-quotes/'.$file->id, Str::uuid()->toString().'.'.($scan->guessExtension() ?: 'bin'), 'local');
            if ($stored === false) {
                throw new LoanException([__('micro.problem.upload')]);
            }
        }

        return $file->quotes()->create([
            'supplier' => mb_substr(trim($supplier), 0, 255), 'source' => $source, 'quoted_on' => $day?->toDateString(), 'amount' => $value,
            'currency' => $currency, 'chosen' => ! $file->quotes()->exists(), 'original_name' => $scan !== null ? Str::limit(basename($scan->getClientOriginalName()), 250, '') : null,
            'stored_path' => $stored ?: null, 'created_by' => (int) $by->getAuthIdentifier(),
        ]);
    }

    /** @throws LoanException */
    public function removeQuote(MicroLoan $file, int $quoteId): void
    {
        $this->assertEditable($file);
        $quote = $file->quotes()->whereKey($quoteId)->firstOrFail();

        if ($quote->stored_path !== null) {
            Storage::disk('local')->delete($quote->stored_path);
        }

        $quote->delete();
    }

    /** @throws LoanException */
    public function chooseQuote(MicroLoan $file, int $quoteId): void
    {
        $this->assertEditable($file);
        $file->quotes()->whereKey($quoteId)->firstOrFail();

        DB::transaction(function () use ($file, $quoteId): void {
            $file->quotes()->update(['chosen' => false]);
            $file->quotes()->whereKey($quoteId)->update(['chosen' => true]);
        });
    }

    /**
     * Sends the payment order for approval: it needs its purchase list and at least two quotes, one of them chosen.
     *
     * @throws LoanException
     */
    public function submit(MicroLoan $file): MicroLoan
    {
        $this->assertEditable($file);
        $problems = [];

        if (! $file->items()->exists()) {
            $problems[] = __('micro.problem.no_items');
        }

        if ($file->quotes()->count() < MicroQuote::MIN_QUOTES) {
            $problems[] = __('micro.problem.quotes', ['min' => MicroQuote::MIN_QUOTES]);
        } elseif (! $file->quotes()->where('chosen', true)->exists()) {
            $problems[] = __('micro.problem.chosen');
        }

        if ($problems !== []) {
            throw new LoanException($problems);
        }

        $file->update(['stage' => MicroLoan::SUBMITTED, 'approvals' => [], 'submitted_at' => now(), 'return_reason' => null]);

        return $file->refresh();
    }

    /** The role whose approval the file waits for, or null when it waits for none. */
    public function nextRole(MicroLoan $file): ?RoleId
    {
        return $file->stage === MicroLoan::SUBMITTED ? $this->chain->nextStep($this->usdValue($file), $this->approvedRoles($file), true) : null;
    }

    public function mayApprove(User $user, MicroLoan $file): bool
    {
        return $file->stage === MicroLoan::SUBMITTED && $this->chain->mayApproveNext($this->usdValue($file), $this->approvedRoles($file), $user->roleIds(), true);
    }

    /**
     * One approval, in order: the Program Manager, the Financial Manager, then, above the threshold, the General Manager.
     * The last one approves the file, and its purchases can be paid.
     *
     * @throws LoanException
     */
    public function approve(User $by, MicroLoan $file): MicroLoan
    {
        return DB::transaction(function () use ($by, $file): MicroLoan {
            $file = MicroLoan::query()->lockForUpdate()->findOrFail($file->id);
            $role = $this->nextRole($file);

            if ($role === null || ! $this->mayApprove($by, $file)) {
                throw new LoanException([__('micro.problem.not_your_step')]);
            }

            $approvals = [...($file->approvals ?? []), ['role' => $role->value, 'user' => (int) $by->getAuthIdentifier(), 'name' => $by->name, 'at' => now()->toDateTimeString()]];
            $done = $this->chain->nextStep($this->usdValue($file), [...$this->approvedRoles($file), $role], true) === null;

            $file->update(['approvals' => $approvals] + ($done ? ['stage' => MicroLoan::APPROVED, 'approved_at' => now()] : []));

            return $file->refresh();
        });
    }

    /**
     * Sends the file back to the preparer, with the reason; its approvals start again when it is submitted again.
     *
     * @throws LoanException
     */
    public function returnForChanges(User $by, MicroLoan $file, string $reason): MicroLoan
    {
        if (! $this->mayApprove($by, $file)) {
            throw new LoanException([__('micro.problem.not_your_step')]);
        }

        if (trim($reason) === '') {
            throw new LoanException([__('micro.problem.reason')]);
        }

        $file->update(['stage' => MicroLoan::RETURNED, 'approvals' => [], 'return_reason' => mb_substr(trim($reason), 0, 255)]);

        return $file->refresh();
    }

    /**
     * Cancels a file that has not been paid for: before its approval, or approved with no purchase yet.
     *
     * @throws LoanException
     */
    public function cancel(MicroLoan $file, string $reason): MicroLoan
    {
        if (in_array($file->stage, [MicroLoan::ACTIVE, MicroLoan::CANCELLED], true) || $file->purchases()->exists()) {
            throw new LoanException([__('micro.problem.cancel')]);
        }

        if (trim($reason) === '') {
            throw new LoanException([__('micro.problem.reason')]);
        }

        $file->update(['stage' => MicroLoan::CANCELLED, 'return_reason' => mb_substr(trim($reason), 0, 255)]);

        return $file->refresh();
    }

    /**
     * Where a file's money comes from: "project:CODE" (a project, not a general account) or "fund:FUNDER|CITY" (an
     * instalment fund, no project).
     *
     * @return array{0: string|null, 1: string|null, 2: string|null, 3: string|null} project, funder, city, problem
     */
    public function funding(string $choice): array
    {
        if (str_starts_with($choice, 'fund:')) {
            [$funder, $city] = array_pad(explode('|', substr($choice, 5), 2), 2, '');

            return trim($funder) === '' || trim($city) === ''
                ? [null, null, null, __('micro.problem.funding')]
                : [null, trim($funder), trim($city), null];
        }

        $project = str_starts_with($choice, 'project:') ? GlProject::query()->find(substr($choice, 8)) : null;

        return $project === null || $project->is_general_account
            ? [null, null, null, __('micro.problem.funding')]
            : [$project->code, (string) $project->donor, $project->city !== null && $project->city !== 'All' ? (string) $project->city : null, null];
    }

    /** @return list<RoleId> */
    private function approvedRoles(MicroLoan $file): array
    {
        return array_values(array_filter(array_map(fn (array $step): ?RoleId => RoleId::tryFrom((int) $step['role']), $file->approvals ?? [])));
    }

    /** @throws LoanException */
    private function assertEditable(MicroLoan $file): void
    {
        if (! in_array($file->stage, MicroLoan::EDITABLE, true)) {
            throw new LoanException([__('micro.problem.not_editable')]);
        }
    }

    /** @return numeric-string|null */
    private function number(string $text, int $scale = 2): ?string
    {
        $clean = str_replace([',', ' '], '', trim($text));

        return preg_match('/^\d+(\.\d+)?$/', $clean) === 1 ? Money::normalize($clean, $scale) : null;
    }

    private function day(string $text): ?CarbonImmutable
    {
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) === 1 ? CarbonImmutable::createFromFormat('!Y-m-d', $text) : false;

        return $day instanceof CarbonImmutable && $day->format('Y-m-d') === $text ? $day : null;
    }
}
