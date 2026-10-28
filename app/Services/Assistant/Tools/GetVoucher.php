<?php

namespace App\Services\Assistant\Tools;

use App\Enums\RoleId;
use App\Enums\ScreenAction;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherAudit;
use App\Models\VoucherLine;
use App\Services\Approvals\ApprovalChain;
use App\Services\Assistant\ToolInputException;

/**
 * One voucher with its lines and its history: who prepared, approved, amended or rejected it and why, and the
 * approval steps its amount needs. The cashier sees only the vouchers they prepared.
 */
class GetVoucher extends Tool
{
    public function __construct(private readonly ApprovalChain $chain) {}

    public function name(): string
    {
        return 'get_voucher';
    }

    public function allows(User $user): bool
    {
        return $this->can($user, 'vouchers') || $this->can($user, 'single', ScreenAction::Create);
    }

    public function definition(): array
    {
        return [
            'description' => 'One voucher by number (e.g. CRV-2026-0001): header, status, every line (account, project, budget line, currency, amounts in the original currency and in USD, rate), its audit history with reasons (a rejected voucher carries the rejection reason), and the roles whose approval its USD amount needs.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'number' => ['type' => 'string'],
                ],
                'required' => ['number'],
            ],
        ];
    }

    public function run(User $user, array $input, string $conversationId): array
    {
        $voucher = Voucher::withTrashed()->with(['lines.account', 'audits'])->find($this->text($input, 'number', 20));
        $own = $voucher !== null && $voucher->prepared_by === (int) $user->getAuthIdentifier();

        if ($voucher === null || (! $own && ! $this->can($user, 'vouchers'))) {
            throw new ToolInputException('No voucher with that number that this person may see.');
        }

        $audits = $voucher->audits->sortBy('id')->values();
        $people = User::query()->whereIn('ID', $audits->pluck('changed_by')->push($voucher->prepared_by, $voucher->approved_by)->filter()->unique()->all())->get()
            ->mapWithKeys(fn (User $person) => [(int) $person->getAuthIdentifier() => $person->name]);
        $amount = $voucher->approvalAmount();

        return [
            'number' => $voucher->id,
            'type' => $voucher->type->value,
            'date' => $voucher->date->toDateString(),
            'description' => $voucher->description,
            'status' => $voucher->trashed() ? 'rejected' : $voucher->status->value,
            'prepared_by' => $people[$voucher->prepared_by] ?? null,
            'approved_by' => $voucher->approved_by !== null ? ($people[$voucher->approved_by] ?? null) : null,
            'approved_at' => $voucher->approved_at?->toDateTimeString(),
            'total_usd' => $amount,
            'approval_steps' => array_map(fn (RoleId $role) => $role->name, $this->chain->stepsFor($amount)),
            'lines' => $voucher->lines->map(fn (VoucherLine $line) => [
                'account' => $line->account_code,
                'account_name' => $line->account?->name_en,
                'project' => $line->project_code,
                'budget_line' => $line->budget_line_key,
                'currency' => $line->currency,
                'debit' => $line->debit_orig,
                'credit' => $line->credit_orig,
                'rate' => $line->rate,
                'debit_usd' => $line->debit_usd,
                'credit_usd' => $line->credit_usd,
                'fund_source' => $line->fund_source?->value,
            ])->values()->all(),
            'history' => $audits->map(fn (VoucherAudit $audit) => [
                'action' => $audit->action,
                'by' => $people[$audit->changed_by] ?? null,
                'at' => $audit->created_at->toDateTimeString(),
                'field' => $audit->field,
                'old' => $audit->old_value,
                'new' => $audit->new_value,
                'reason' => $audit->reason,
            ])->values()->all(),
        ];
    }
}
