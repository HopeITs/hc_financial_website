<?php

namespace App\Services\Loans;

use App\Enums\ScreenAction;
use App\Models\MicroLoan;
use App\Models\MicroPurchase;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The micro-projects section at a glance (finance request of 2026-11-23): the files at each step, what waits for the
 * person, the loans' instalments owed and late, and the instalment funds' value today.
 */
class MicroOverview
{
    /** The steps a file goes through, in order, as the section page shows them. */
    public const STEPS = ['draft', 'submitted', 'approved', 'ready', 'active'];

    public function __construct(
        private readonly MicroFiles $files,
        private readonly MicroPurchases $purchases,
        private readonly MicroLoans $loans,
        private readonly InstalmentFunds $funds,
    ) {}

    /**
     * How many files are at each step: new or sent back, waiting for approval, buying, ready for the delivery protocol
     * (invoices booked and no advance open), and open loans.
     *
     * @return array<string, int>
     */
    public function steps(): array
    {
        $counts = array_fill_keys(self::STEPS, 0);

        foreach ($this->files() as $file) {
            $step = $this->stepOf($file);
            if ($step !== null) {
                $counts[$step]++;
            }
        }

        $counts['active'] = MicroLoan::query()->active()->count();

        return $counts;
    }

    /**
     * The files that wait for this person: their approval, their own files sent back, and the files ready for the
     * delivery protocol (for those who prepare the files).
     *
     * @return array{approve: Collection<int, MicroLoan>, returned: Collection<int, MicroLoan>, ready: Collection<int, MicroLoan>}
     */
    public function waitingFor(User $user): array
    {
        $files = $this->files();
        $prepares = $user->canOnScreen('microfiles', ScreenAction::Create);

        return [
            'approve' => $user->canOnScreen('microfiles', ScreenAction::Approve)
                ? $files->filter(fn (MicroLoan $file): bool => $this->files->mayApprove($user, $file))->values()
                : collect(),
            'returned' => $prepares ? $files->filter(fn (MicroLoan $file): bool => $file->stage === MicroLoan::RETURNED)->values() : collect(),
            'ready' => $prepares ? $files->filter(fn (MicroLoan $file): bool => $this->stepOf($file) === 'ready')->values() : collect(),
        ];
    }

    /**
     * The open loans' instalments: still owed and late, by currency, with the latest beneficiaries first.
     *
     * @return array{loans: int, owed: array<string, string>, late: array<string, string>, late_loans: list<array{loan: MicroLoan, amount: string, days: int}>}
     */
    public function instalments(CarbonImmutable $asAt): array
    {
        $owed = [];
        $late = [];
        $lateLoans = [];
        $loans = MicroLoan::query()->active()->with('instalments')->get();

        foreach ($loans as $loan) {
            $owed[$loan->currency] = bcadd($owed[$loan->currency] ?? '0.00', $loan->owed(), 2);
            $arrears = $this->loans->arrears($loan, $asAt);

            if (bccomp($arrears['amount'], '0', 2) > 0) {
                $late[$loan->currency] = bcadd($late[$loan->currency] ?? '0.00', $arrears['amount'], 2);
                $lateLoans[] = ['loan' => $loan, 'amount' => $arrears['amount'], 'days' => $arrears['days']];
            }
        }

        usort($lateLoans, fn (array $a, array $b): int => $b['days'] <=> $a['days']);

        return ['loans' => $loans->count(), 'owed' => $owed, 'late' => $late, 'late_loans' => array_slice($lateLoans, 0, 10)];
    }

    /**
     * Every instalment fund at today's value, in dollars, or null while today's pound rate is missing.
     *
     * @return numeric-string|null
     */
    public function fundsValue(CarbonImmutable $asAt): ?string
    {
        $total = '0.00';

        foreach ($this->funds->balances($asAt) as $fund) {
            if ($fund['value_usd'] === null) {
                return null;
            }

            $total = bcadd($total, $fund['value_usd'], 2);
        }

        return $total;
    }

    /** The step of a file on its way, or null for a cancelled or opened one. */
    public function stepOf(MicroLoan $file): ?string
    {
        return match ($file->stage) {
            MicroLoan::DRAFT, MicroLoan::RETURNED => 'draft',
            MicroLoan::SUBMITTED => 'submitted',
            MicroLoan::APPROVED => $this->isReady($file) ? 'ready' : 'approved',
            MicroLoan::ACTIVE => 'active',
            default => null,
        };
    }

    /** An approved file whose invoices are booked and whose advances are settled: the protocol can be signed. */
    private function isReady(MicroLoan $file): bool
    {
        if (! $file->purchases()->where('kind', MicroPurchase::INVOICE)->exists()) {
            return false;
        }

        $totals = $this->purchases->totals($file);

        return bccomp($totals['open_advances'], '0', 2) === 0 && bccomp(Money::normalize($totals['invoiced']), '0', 2) > 0;
    }

    /** @return Collection<int, MicroLoan> the files on their way */
    private function files(): Collection
    {
        return MicroLoan::query()->whereIn('stage', [MicroLoan::DRAFT, MicroLoan::RETURNED, MicroLoan::SUBMITTED, MicroLoan::APPROVED])
            ->orderByDesc('id')->get();
    }
}
