<?php

namespace App\Services\Requests;

use App\Enums\TransferKind;
use App\Models\GlAccount;
use App\Models\GlProject;
use App\Models\LedgerRequest;
use App\Models\Voucher;
use App\Services\Ledger\LedgerRuleException;
use App\Services\Ledger\PostVoucher;
use App\Services\Ledger\TransferBuilder;
use App\Services\Ledger\VoucherDraft;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * The allocation voucher of an approved request: its SYP total from the city's general account to the project at the
 * city's approved rate (the project repays the dollar value from its 1161 share) and its USD total likewise, exactly as
 * TransferBuilder builds `syp_to_project` and `usd_to_project`. Both are pending vouchers linked by `request_id`; if either
 * cannot be built nothing is created and the problems say what is missing. A request that already has them gets no more.
 */
class RequestAllocation
{
    public function __construct(private readonly TransferBuilder $builder, private readonly PostVoucher $posting) {}

    /** @return array{vouchers: array<int, string>, problems: array<int, string>} the numbers of the vouchers created, or what is missing */
    public function create(LedgerRequest $request, int $preparedBy): array
    {
        $exists = Voucher::query()->where('request_id', $request->id)->whereIn('transfer_kind', [TransferKind::SypToProject->value, TransferKind::UsdToProject->value])->exists();
        if ($exists) {
            return ['vouchers' => [], 'problems' => []];
        }

        $project = GlProject::query()->whereKey($request->project_code)->first();
        $city = (string) $project?->city;
        if ($project === null || $city === '') {
            return ['vouchers' => [], 'problems' => [(string) __('requests.allocation.no_city', ['project' => $request->project_code])]];
        }

        $syp = Money::zero();
        $usd = Money::zero();
        foreach ($request->lines()->get() as $line) {
            if ($line->currency === 'SYP') {
                $syp = bcadd($syp, Money::normalize($line->amount), 2);
            } else {
                $usd = bcadd($usd, Money::normalize($line->amount_usd), 2);
            }
        }

        $today = now()->toDateString();
        $wanted = array_filter([TransferKind::SypToProject->value => ['SYP', $syp], TransferKind::UsdToProject->value => ['USD', $usd]], fn (array $part) => bccomp($part[1], '0', 2) > 0);
        $drafts = [];
        $problems = [];

        foreach ($wanted as $kind => [$currency, $amount]) {
            $cash = $this->cash($city, $currency);
            $built = $this->builder->build($kind, $today, $city, $request->project_code, '', $currency, $amount, $cash, '', __('requests.allocation.description', ['number' => $request->id]), $preparedBy);

            if ($built->draft === null || $built->problems !== []) {
                array_push($problems, ...array_map('strval', $built->problems !== [] ? $built->problems : [(string) __('ledger.builder.no_lines')]));

                continue;
            }

            $drafts[] = $built->draft->forRequest($request->id);
        }

        if ($problems !== [] || $drafts === []) {
            return ['vouchers' => [], 'problems' => $problems];
        }

        try {
            $numbers = DB::transaction(fn () => array_map(fn (VoucherDraft $draft) => $this->posting->post($draft)->id, $drafts));
        } catch (LedgerRuleException $e) {
            return ['vouchers' => [], 'problems' => array_map(fn ($violation) => (string) $violation->message, $e->violations)];
        }

        return ['vouchers' => $numbers, 'problems' => []];
    }

    /** The first of the city's own cash accounts in the currency, in transit left out. */
    private function cash(string $city, string $currency): string
    {
        $account = GlAccount::query()
            ->where('is_cash', true)->where('level', 'posting')->where('is_active', true)
            ->where('city', $city)->where('currency', $currency)
            ->orderBy('code')->get()
            ->first(fn (GlAccount $account) => preg_match((string) config('ledger.in_transit_pattern'), $account->code) !== 1);

        return $account->code ?? '';
    }
}
