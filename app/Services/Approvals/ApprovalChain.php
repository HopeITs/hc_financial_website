<?php

namespace App\Services\Approvals;

use App\Enums\RoleId;

/**
 * The approvals a request needs, in order: the financial manager first, and for more than
 * USD 2,000 (config `permissions.general_manager_threshold_usd`) the General Manager after.
 */
class ApprovalChain
{
    /**
     * @param  numeric-string  $amountUsd
     * @return list<RoleId>
     */
    public function stepsFor(string $amountUsd): array
    {
        $steps = [RoleId::FinancialManager];
        $threshold = config('permissions.general_manager_threshold_usd');

        if (is_string($threshold) && is_numeric($threshold) && bccomp($amountUsd, $threshold, 2) > 0) {
            $steps[] = RoleId::GeneralManager;
        }

        return $steps;
    }

    /**
     * The step still to be done, or null when the request is fully approved.
     *
     * @param  numeric-string  $amountUsd
     * @param  list<RoleId>  $approvedSteps  the steps already approved, in order
     */
    public function nextStep(string $amountUsd, array $approvedSteps): ?RoleId
    {
        return $this->stepsFor($amountUsd)[count($approvedSteps)] ?? null;
    }

    /**
     * Whether a person holding $roles may do the next step. Head Office stands in for the financial
     * manager, never for the General Manager.
     *
     * @param  numeric-string  $amountUsd
     * @param  list<RoleId>  $approvedSteps
     * @param  list<RoleId>  $roles
     */
    public function mayApproveNext(string $amountUsd, array $approvedSteps, array $roles): bool
    {
        $next = $this->nextStep($amountUsd, $approvedSteps);

        if ($next === null) {
            return false;
        }

        return in_array($next, $roles, true)
            || ($next === RoleId::FinancialManager && in_array(RoleId::HeadOffice, $roles, true));
    }
}
