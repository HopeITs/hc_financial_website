<?php

namespace App\Services\Approvals;

use App\Enums\RoleId;
use App\Models\Legacy\RoleCurrencyLimit;
use App\Models\Role;

/**
 * How much a role may approve. The per-currency limit (`role_currency_limit`) wins;
 * without a row for that currency the role's own `Approval_Limit` applies.
 * A limit of zero or none means the role may not approve, except Head Office, which has no ceiling.
 */
class ApprovalLimits
{
    /**
     * The limit as a decimal string, or null when the role has no ceiling.
     * A zero result means "may not approve".
     *
     * @return numeric-string|null
     */
    public function limitFor(RoleId $role, ?int $tillTypeId): ?string
    {
        if ($role === RoleId::HeadOffice) {
            return null;
        }

        if ($role->isWebOnly()) {
            return '0.00';
        }

        if ($tillTypeId !== null) {
            $row = RoleCurrencyLimit::query()
                ->where('Role_ID', $role->value)
                ->where('TillType_ID', $tillTypeId)
                ->first();

            if ($row !== null) {
                return $this->decimal($row->getAttribute('Approval_Limit'));
            }
        }

        return $this->decimal(Role::query()->whereKey($role->value)->value('Approval_Limit'));
    }

    /**
     * @param  numeric-string  $amount
     */
    public function allows(RoleId $role, ?int $tillTypeId, string $amount): bool
    {
        $limit = $this->limitFor($role, $tillTypeId);

        if ($limit === null) {
            return true;
        }

        return bccomp($limit, '0', 2) > 0 && bccomp($amount, $limit, 2) <= 0;
    }

    /**
     * @return numeric-string
     */
    private function decimal(mixed $value): string
    {
        return is_numeric($value) ? bcadd((string) $value, '0', 2) : '0.00';
    }
}
