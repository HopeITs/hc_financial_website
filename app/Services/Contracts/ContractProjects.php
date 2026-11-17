<?php

namespace App\Services\Contracts;

use App\Enums\ContractRestriction;
use App\Models\Contract;
use App\Models\ContractProject;
use App\Models\GlProject;
use App\Services\Ledger\NetAssets;
use Illuminate\Support\Facades\DB;

/**
 * Projects are created from inside their agreement (finance decision of 2026-11-17): the new ledger project takes the
 * agreement's donor, currency, fixed rate and restriction, and its share of the agreement is saved with it, both or
 * neither.
 */
class ContractProjects
{
    public const UNRESTRICTED = 'Without donor restrictions';

    /**
     * @param  array{code: string, name: string, city: string|null, amount: string}  $project
     */
    public function create(Contract $contract, array $project): ContractProject
    {
        $contract->loadMissing('donor');

        return DB::transaction(function () use ($contract, $project): ContractProject {
            $ledgerProject = GlProject::query()->create([
                'code' => self::normalCode($project['code']),
                'name' => trim($project['name']),
                'city' => $project['city'] !== null && trim($project['city']) !== '' ? trim($project['city']) : null,
                'donor' => $contract->donor->name_en,
                'restriction' => $contract->restriction === ContractRestriction::Unrestricted ? self::UNRESTRICTED : NetAssets::RESTRICTED,
                'donor_currency' => $contract->currency,
                'donor_rate' => (string) $contract->fixed_rate,
                'is_general_account' => false,
            ]);

            return ContractProject::query()->create([
                'contract_id' => $contract->id,
                'project_code' => $ledgerProject->code,
                'amount' => $project['amount'],
            ]);
        });
    }

    /** Project codes are stored in capitals without spaces around them, as the chart's project codes are. */
    public static function normalCode(string $code): string
    {
        return mb_strtoupper(trim($code));
    }
}
