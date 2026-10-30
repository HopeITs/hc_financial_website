<?php

namespace App\Services\Home;

use App\Enums\RequestStatus;
use App\Enums\ScreenAction;
use App\Enums\VoucherStatus;
use App\Models\GlProject;
use App\Models\LedgerRequest;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Projects\ProjectAccess;
use App\Support\Navigation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * What waits for the signed-in person (U-03), shown at the top of the home page: vouchers to approve, their own
 * vouchers rejected lately, requests to decide and their own requests still waiting. The same approval rules as the
 * bell decide what counts. Only the items their role deals with are listed, each with a link to the filtered list.
 */
class WaitingWork
{
    /** A rejected voucher stays on the home page this many days. */
    public const REJECTED_DAYS = 30;

    public function __construct(private readonly ProjectAccess $access) {}

    /** @return list<array{key: string, count: int, url: string}> */
    public function for(User $user): array
    {
        $id = (int) $user->getAuthIdentifier();
        $items = [];

        if ($user->canOnScreen('vouchers', ScreenAction::Approve)) {
            $items[] = [
                'key' => 'approve',
                'count' => Voucher::query()->where('status', VoucherStatus::PendingApproval)->where('prepared_by', '!=', $id)->get()
                    ->filter(fn (Voucher $voucher): bool => Gate::forUser($user)->allows('approve', $voucher))->count(),
                'url' => route(Navigation::routeName('vouchers'), ['status' => VoucherStatus::PendingApproval->value]),
            ];
        }

        if (collect(['single', 'multi', 'vouchers'])->contains(fn (string $screen): bool => $user->canOnScreen($screen, ScreenAction::Create))) {
            $items[] = [
                'key' => 'rejected',
                'count' => Voucher::onlyTrashed()->where('prepared_by', $id)->where('deleted_at', '>=', now()->subDays(self::REJECTED_DAYS))->count(),
                'url' => route(Navigation::routeName('vouchers'), ['status' => 'rejected', 'mine' => 1]),
            ];
        }

        if ($user->canOnScreen('requests', ScreenAction::Approve)) {
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
