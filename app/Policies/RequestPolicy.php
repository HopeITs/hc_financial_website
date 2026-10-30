<?php

namespace App\Policies;

use App\Enums\RoleId;
use App\Enums\ScreenAction;
use App\Models\GlProject;
use App\Models\LedgerRequest;
use App\Models\User;
use App\Services\Approvals\ApprovalChain;
use App\Services\Projects\ProjectAccess;

/**
 * Who may do what with a request. A project manager works on the requests of the projects linked to them (checked
 * through the link, not the role alone); the Program Manager, the Financial Manager and then the General Manager decide, never the person
 * who made the request and never the same person twice. Whether the request is in the right state is the workflow's job.
 */
class RequestPolicy
{
    public const SCREEN = 'requests';

    public function __construct(private readonly ProjectAccess $access, private readonly ApprovalChain $chain) {}

    public function view(User $user, LedgerRequest $request): bool
    {
        return $user->canOnScreen(self::SCREEN) && $this->ownsProject($user, $request->project_code);
    }

    /** Whether the person may start a request for the project. */
    public function createFor(User $user, string $projectCode): bool
    {
        return $user->canOnScreen(self::SCREEN, ScreenAction::Create) && $this->ownsProject($user, $projectCode);
    }

    public function update(User $user, LedgerRequest $request): bool
    {
        return $user->canOnScreen(self::SCREEN, ScreenAction::Edit) && $this->ownsProject($user, $request->project_code);
    }

    public function decide(User $user, LedgerRequest $request): bool
    {
        if ($user->isSuperUser()) {
            return true;
        }

        if (! $user->canOnScreen(self::SCREEN, ScreenAction::Approve) || $request->requested_by === (int) $user->getAuthIdentifier()) {
            return false;
        }

        $approvals = $request->approvals()->get();

        if ($approvals->contains('decided_by', (int) $user->getAuthIdentifier())) {
            return false;
        }

        $done = array_values($approvals->where('decision', 'approved')->map(fn ($approval): RoleId => $approval->role_id)->all());

        return $this->chain->mayApproveNext($this->total($request), $done, $user->roleIds(), true);
    }

    public function close(User $user, LedgerRequest $request): bool
    {
        return $user->canOnScreen(self::SCREEN, ScreenAction::Approve);
    }

    /** @return numeric-string */
    private function total(LedgerRequest $request): string
    {
        $amount = (string) $request->amount;

        return is_numeric($amount) ? bcadd($amount, '0', 2) : '0.00';
    }

    private function ownsProject(User $user, string $projectCode): bool
    {
        $limit = $this->access->limitedTo($user);

        if ($limit === null) {
            return true;
        }

        $account = GlProject::query()->whereKey($projectCode)->value('legacy_account_id');

        return is_numeric($account) && in_array((int) $account, $limit, true);
    }
}
