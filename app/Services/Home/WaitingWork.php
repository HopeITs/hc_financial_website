<?php

namespace App\Services\Home;

use App\Enums\RequestStatus;
use App\Enums\ScreenAction;
use App\Enums\VoucherStatus;
use App\Models\CashCount;
use App\Models\DailyRate;
use App\Models\GlProject;
use App\Models\LedgerRequest;
use App\Models\MonthClose;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Loans\MicroOverview;
use App\Services\Projects\ProjectAccess;
use App\Services\Treasury\CashBook;
use App\Services\Treasury\Revaluations;
use App\Support\Navigation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

/**
 * What waits for the signed-in person (U-03), shown at the top of the home page: vouchers to approve, their own
 * vouchers rejected lately, requests to decide and their own requests still waiting, and the treasury's routine. The same approval rules as the
 * bell decide what counts. Only the items their role deals with are listed, each with a link to the filtered list.
 */
class WaitingWork
{
    /** A rejected voucher stays on the home page this many days. */
    public const REJECTED_DAYS = 30;

    public function __construct(private readonly ProjectAccess $access) {}

    /** @return list<array{key: string, count: int, url: string, params?: array<string, string>}> */
    public function for(User $user): array
    {
        $id = (int) $user->getAuthIdentifier();
        $items = [];

        // For those who approve, everything waiting for them comes first, in one place (finance decision of 2026-11-25).
        // Those with the inbox see their approvals there only, not again card by card (role review of 2026-11-26).
        $hasInbox = $user->canOnScreen('inbox', ScreenAction::View);

        if ($hasInbox) {
            $items[] = ['key' => 'inbox', 'count' => app(ApprovalsInbox::class)->count($user), 'url' => route(Navigation::routeName('inbox'))];
        }

        if (! $hasInbox && $user->canOnScreen('vouchers', ScreenAction::Approve)) {
            $items[] = [
                'key' => 'approve',
                'count' => Voucher::query()->where('status', VoucherStatus::PendingApproval)->where('prepared_by', '!=', $id)->get()
                    ->filter(fn (Voucher $voucher): bool => Gate::forUser($user)->allows('approve', $voucher))->count(),
                'url' => route(Navigation::routeName('vouchers'), ['status' => VoucherStatus::PendingApproval->value]),
            ];
        }

        // The card opens the vouchers list, so only for those who may open it (role review of 2026-11-26: a cashier who
        // may not got a refused page); the cashier sees their own vouchers on their till's page.
        if ($user->canOnScreen('vouchers', ScreenAction::View)
            && collect(['single', 'multi', 'vouchers'])->contains(fn (string $screen): bool => $user->canOnScreen($screen, ScreenAction::Create))) {
            $items[] = [
                'key' => 'rejected',
                'count' => Voucher::onlyTrashed()->where('prepared_by', $id)->where('deleted_at', '>=', now()->subDays(self::REJECTED_DAYS))->count(),
                'url' => route(Navigation::routeName('vouchers'), ['status' => 'rejected', 'mine' => 1]),
            ];
        }

        if (! $hasInbox && $user->canOnScreen('requests', ScreenAction::Approve)) {
            $items[] = [
                'key' => 'decide',
                'count' => $this->visibleRequests($user)->where('status', RequestStatus::Submitted->value)->get()
                    ->filter(fn (LedgerRequest $request): bool => Gate::forUser($user)->allows('decide', $request))->count(),
                'url' => route(Navigation::routeName('requests'), ['status' => RequestStatus::Submitted->value]),
            ];
        }

        if ($user->canOnScreen('requests', ScreenAction::Create)) {
            $items[] = [
                'key' => 'my_requests',
                'count' => LedgerRequest::query()->where('requested_by', $id)->where('status', RequestStatus::Submitted->value)->count(),
                'url' => route(Navigation::routeName('requests'), ['status' => RequestStatus::Submitted->value, 'mine' => 1]),
            ];
        }

        return [...$items, ...$this->microProjects($user, $hasInbox), ...$this->treasury($user)];
    }

    /**
     * Micro projects (finance request of 2026-11-23): payment orders waiting for the person's approval, and the files
     * sent back for changes, for those who prepare them.
     *
     * @return list<array{key: string, count: int, url: string}>
     */
    private function microProjects(User $user, bool $hasInbox): array
    {
        // Until the migration of 2026-11-23 has run there are no files.
        if (! Schema::hasColumn('gl_micro_loans', 'stage')) {
            return [];
        }

        $items = [];
        $waiting = null;

        if (! $hasInbox && $user->canOnScreen('microfiles', ScreenAction::Approve)) {
            $waiting = app(MicroOverview::class)->waitingFor($user);
            $items[] = ['key' => 'micro_approve', 'count' => $waiting['approve']->count(), 'url' => route(Navigation::routeName('microfiles'), ['show' => 'submitted'])];
        }

        if ($user->canOnScreen('microfiles', ScreenAction::Create)) {
            $waiting ??= app(MicroOverview::class)->waitingFor($user);
            $items[] = ['key' => 'micro_returned', 'count' => $waiting['returned']->count(), 'url' => route(Navigation::routeName('microfiles'), ['show' => 'draft'])];
        }

        return $items;
    }

    /**
     * The treasury's routine (finance request of 2026-11-22): daily rates to approve, today's cash counts not done yet,
     * last month not closed and its revaluation of pounds not prepared. Each only for the roles that do it.
     *
     * @return list<array{key: string, count: int, url: string, params?: array<string, string>}>
     */
    private function treasury(User $user): array
    {
        $id = (int) $user->getAuthIdentifier();
        $today = CarbonImmutable::today();
        $lastMonth = $today->subMonthNoOverflow()->format('Y-m');
        $items = [];

        if ($user->canOnScreen('alloc', ScreenAction::Approve)) {
            $items[] = [
                'key' => 'rates',
                'count' => DailyRate::query()->where('status', DailyRate::PENDING)->where('proposed_by', '!=', $id)->count(),
                'url' => route(Navigation::routeName('alloc')),
            ];
        }

        if ($user->canOnScreen('tills', ScreenAction::Create)) {
            $book = app(CashBook::class);
            $balances = $book->balances($today);
            $counted = CashCount::query()->whereDate('day', $today->toDateString())->pluck('account_code')->all();
            $items[] = [
                'key' => 'counts',
                'count' => $book->accounts()->keys()
                    ->filter(fn (string $code): bool => bccomp($balances[$code]['orig'] ?? '0', '0', 2) !== 0 && ! in_array($code, $counted, true))
                    ->count(),
                'url' => route(Navigation::routeName('tills'), ['day' => $today->toDateString()]),
            ];
        }

        if ($user->canOnScreen('monthend', ScreenAction::Approve)) {
            $items[] = [
                'key' => 'month_close',
                'count' => MonthClose::query()->whereKey($lastMonth)->exists() ? 0 : 1,
                'url' => route(Navigation::routeName('monthend')),
                'params' => ['month' => CarbonImmutable::parse($lastMonth.'-01')->format('m/Y')],
            ];
        }

        if ($user->canOnScreen('treasury', ScreenAction::Create)) {
            $preview = app(Revaluations::class)->preview($lastMonth);
            $items[] = [
                'key' => 'revaluation',
                'count' => bccomp($preview['difference'], '0', 2) !== 0 || $preview['missing_rates'] !== [] ? 1 : 0,
                'url' => route(Navigation::routeName('treasury')),
                'params' => ['month' => CarbonImmutable::parse($lastMonth.'-01')->format('m/Y')],
            ];
        }

        return $items;
    }

    /**
     * The requests of the projects the person may see, as on the requests screen.
     *
     * @return Builder<LedgerRequest>
     */
    private function visibleRequests(User $user): Builder
    {
        $limit = $this->access->limitedTo($user);

        return LedgerRequest::query()->when($limit !== null, fn ($query) => $query->whereIn(
            'project_code',
            GlProject::query()->whereIn('legacy_account_id', $limit ?? [])->select('code'),
        ));
    }
}
