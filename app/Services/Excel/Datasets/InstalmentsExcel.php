<?php

namespace App\Services\Excel\Datasets;

use App\Models\Contract;
use App\Models\ContractInstalment;
use App\Models\User;
use App\Services\Contracts\ContractReceipts;
use App\Services\Excel\ExcelDataset;
use App\Services\Projects\ProjectAccess;
use Carbon\CarbonImmutable;

/**
 * One agreement's instalments to and from Excel (context `contract`). A row with the number of an instalment changes
 * it; a row with a new number, or none, adds one. As on the screen, the instalments of an approved agreement are fixed:
 * its file is refused whole. The export also says what has arrived.
 */
class InstalmentsExcel extends ExcelDataset
{
    private const YES = ['1', 'yes', 'y', 'true', 'نعم'];

    private const NO = ['', '0', 'no', 'n', 'false', 'لا'];

    public function __construct(
        private readonly ContractReceipts $receipts,
        private readonly ProjectAccess $access,
    ) {}

    public function screen(): string
    {
        return 'contracts';
    }

    public function importAction(): string
    {
        return 'edit';
    }

    public function fileName(): string
    {
        return 'instalments';
    }

    public function columns(): array
    {
        return ['number', 'due_on', 'amount', 'conditional', 'condition'];
    }

    public function exportColumns(): array
    {
        return [...$this->columns(), 'received'];
    }

    public function exportRows(array $context): iterable
    {
        $contract = $this->contract($context);
        $status = $this->receipts->status($contract);

        foreach ($contract->instalments()->get() as $instalment) {
            yield [
                $instalment->number, $instalment->due_on->toDateString(), (float) $instalment->amount,
                $instalment->is_conditional ? 1 : 0, (string) $instalment->condition, (float) ($status[$instalment->id]['received'] ?? 0),
            ];
        }
    }

    public function exampleRows(array $context): array
    {
        $contract = $this->contract($context);
        $next = (int) $contract->instalments()->max('number') + 1;

        return [[$next, $contract->starts_on?->toDateString() ?? CarbonImmutable::today()->toDateString(), null, 0, '']];
    }

    protected function check(array $cells, array $before, User $user, array $context): array
    {
        $contract = $this->contract($context);
        $problems = [];

        if ($contract->isApproved() || ! $contract->isOpen()) {
            $problems[] = __('contracts.problem_locked');
        }

        if ($cells['number'] !== '' && (! ctype_digit($cells['number']) || (int) $cells['number'] < 1)) {
            $problems[] = __('excel.bad_number');
        }

        if (! $this->isDay($cells['due_on'])) {
            $problems[] = __('excel.bad_day');
        }

        if (! is_numeric(str_replace(',', '', $cells['amount'])) || (float) str_replace(',', '', $cells['amount']) <= 0) {
            $problems[] = __('excel.bad_amount');
        }

        $conditional = $this->yesNo($cells['conditional']);
        if ($conditional === null) {
            $problems[] = __('excel.bad_yes_no', ['column' => __('excel.col.conditional')]);
        } elseif ($conditional && $cells['condition'] === '') {
            $problems[] = __('excel.condition_needed');
        }

        if ($cells['number'] !== '' && in_array($cells['number'], array_column($before, 'number'), true)) {
            $problems[] = __('excel.twice');
        }

        $exists = $cells['number'] !== '' && ctype_digit($cells['number']) && $contract->instalments()->where('number', (int) $cells['number'])->exists();

        return ['action' => $exists ? 'update' : 'new', 'problems' => $problems];
    }

    protected function saveRow(array $cells, User $user, array $context): void
    {
        $contract = $this->contract($context);
        $number = $cells['number'] !== '' ? (int) $cells['number'] : (int) $contract->instalments()->max('number') + 1;

        ContractInstalment::query()->updateOrCreate(['contract_id' => $contract->id, 'number' => $number], [
            'due_on' => $cells['due_on'],
            'amount' => str_replace(',', '', $cells['amount']),
            'is_conditional' => (bool) $this->yesNo($cells['conditional']),
            'condition' => $cells['condition'] !== '' ? $cells['condition'] : null,
        ]);
    }

    /** The agreement of the context, when the signed-in person may see it. */
    private function contract(array $context): Contract
    {
        $user = auth()->user();

        return Contract::query()
            ->visibleTo($user instanceof User ? $this->access->limitedTo($user) : [])
            ->findOrFail((int) ($context['contract'] ?? 0));
    }

    private function yesNo(string $text): ?bool
    {
        $text = mb_strtolower(trim($text));

        return match (true) {
            in_array($text, self::YES, true) => true,
            in_array($text, self::NO, true) => false,
            default => null,
        };
    }

    private function isDay(string $text): bool
    {
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) === 1 ? CarbonImmutable::createFromFormat('!Y-m-d', $text) : false;

        return $day instanceof CarbonImmutable && $day->format('Y-m-d') === $text;
    }
}
