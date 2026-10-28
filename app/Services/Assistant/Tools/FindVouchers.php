<?php

namespace App\Services\Assistant\Tools;

use App\Enums\ScreenAction;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\User;
use App\Models\Voucher;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Vouchers by status, type, period or text, as the vouchers list filters them. A person who may enter operations but
 * not see the vouchers list (the cashier) sees only the vouchers they prepared.
 */
class FindVouchers extends Tool
{
    public function name(): string
    {
        return 'find_vouchers';
    }

    public function allows(User $user): bool
    {
        return $this->can($user, 'vouchers') || $this->can($user, 'single', ScreenAction::Create);
    }

    public function definition(): array
    {
        return [
            'description' => 'List vouchers, newest first, with type, date, description, status, preparer and USD total. Status "rejected" lists rejected vouchers. Types: CRV receipt, CPV payment, TRF transfer/exchange, ALC allocation, JV journal, OPN opening.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'status' => ['type' => 'string', 'enum' => [...array_map(fn (VoucherStatus $status) => $status->value, VoucherStatus::cases()), 'rejected']],
                    'type' => ['type' => 'string', 'enum' => array_map(fn (VoucherType $type) => $type->value, VoucherType::cases())],
                    'from' => ['type' => 'string', 'description' => 'First day, YYYY-MM-DD.'],
                    'to' => ['type' => 'string', 'description' => 'Last day, YYYY-MM-DD.'],
                    'search' => ['type' => 'string', 'description' => 'Part of the number or the description.'],
                    'mine' => ['type' => 'boolean', 'description' => 'Only vouchers this person prepared.'],
                ],
            ],
        ];
    }

    public function run(User $user, array $input, string $conversationId): array
    {
        $status = $this->text($input, 'status', 20);
        $type = VoucherType::tryFrom($this->text($input, 'type', 3));
        $from = $this->day($input, 'from');
        $to = $this->day($input, 'to');
        $term = $this->text($input, 'search', 60);
        $mine = $this->flag($input, 'mine') || ! $this->can($user, 'vouchers');

        $vouchers = Voucher::query()
            ->when($status === 'rejected', fn ($query) => $query->onlyTrashed())
            ->when(VoucherStatus::tryFrom($status) !== null, fn ($query) => $query->where('status', $status))
            ->when($type !== null, fn ($query) => $query->where('type', $type?->value))
            ->when($from !== null, fn ($query) => $query->whereDate('date', '>=', $from))
            ->when($to !== null, fn ($query) => $query->whereDate('date', '<=', $to))
            ->when($term !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('id', 'like', '%'.$term.'%')
                ->orWhere('description', 'like', '%'.$term.'%')))
            ->when($mine, fn ($query) => $query->where('prepared_by', (int) $user->getAuthIdentifier()))
            ->orderByDesc('date')->orderByDesc('id')
            ->limit($this->maxRows() + 1)
            ->get();

        $shown = $vouchers->take($this->maxRows());
        $totals = DB::table('gl_voucher_lines')->whereIn('voucher_id', $shown->pluck('id')->all())
            ->groupBy('voucher_id')->selectRaw('voucher_id, sum(debit_usd) as total')->pluck('total', 'voucher_id');
        $names = User::query()->whereIn('ID', $shown->pluck('prepared_by')->unique()->all())->get()
            ->mapWithKeys(fn (User $person) => [(int) $person->getAuthIdentifier() => $person->name]);

        return [
            'only_mine' => $mine,
            'vouchers' => $shown->map(fn (Voucher $voucher) => [
                'number' => $voucher->id,
                'type' => $voucher->type->value,
                'date' => $voucher->date->toDateString(),
                'description' => $voucher->description,
                'status' => $voucher->trashed() ? 'rejected' : $voucher->status->value,
                'prepared_by' => $names[$voucher->prepared_by] ?? null,
                'total_usd' => Money::normalize($totals[$voucher->id] ?? 0),
            ])->values()->all(),
            'more' => $vouchers->count() > $this->maxRows(),
        ];
    }
}
