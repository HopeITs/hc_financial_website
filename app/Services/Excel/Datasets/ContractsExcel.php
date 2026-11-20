<?php

namespace App\Services\Excel\Datasets;

use App\Models\Contract;
use App\Models\User;
use App\Services\Excel\ExcelDataset;
use App\Services\Projects\ProjectAccess;
use LogicException;

/**
 * The agreements list to Excel, for reading: an agreement brings its projects, budget and instalments, so it is made on
 * its own screen, and its instalments have their own import there.
 */
class ContractsExcel extends ExcelDataset
{
    public function __construct(private readonly ProjectAccess $access) {}

    public function screen(): string
    {
        return 'contracts';
    }

    public function importable(): bool
    {
        return false;
    }

    public function fileName(): string
    {
        return 'agreements';
    }

    public function columns(): array
    {
        return ['number', 'title', 'donor', 'currency', 'amount', 'fixed_rate', 'signed_on', 'starts_on', 'ends_on', 'restriction', 'status', 'approved'];
    }

    public function exportRows(array $context): iterable
    {
        $user = auth()->user();
        $contracts = Contract::query()
            ->visibleTo($user instanceof User ? $this->access->limitedTo($user) : [])
            ->with('donor')
            ->orderByDesc('signed_on')
            ->orderByDesc('id')
            ->get();

        foreach ($contracts as $contract) {
            yield [
                (string) $contract->number, (string) $contract->title, (string) $contract->donor?->code, (string) $contract->currency,
                (float) $contract->amount, (float) $contract->fixed_rate, $contract->signed_on?->toDateString(), $contract->starts_on?->toDateString(),
                $contract->ends_on?->toDateString(), $contract->restriction->label(), $contract->status->label(), $contract->isApproved() ? 1 : 0,
            ];
        }
    }

    protected function check(array $cells, array $before, User $user, array $context): array
    {
        throw new LogicException('The agreements list is not imported.');
    }

    protected function saveRow(array $cells, User $user, array $context): void
    {
        throw new LogicException('The agreements list is not imported.');
    }
}
