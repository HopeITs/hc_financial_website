<?php

namespace App\Services\Ledger;

use App\Models\GlAccount;
use App\Models\GlBudgetLine;
use App\Models\Voucher;
use Illuminate\Support\Collection;

/**
 * What the posting rules need to look up for one draft, loaded once: its accounts and budget lines.
 */
final class LedgerContext
{
    /**
     * @param  Collection<string, GlAccount>  $accounts
     * @param  Collection<string, GlBudgetLine>  $budgetLines
     */
    private function __construct(
        private readonly Collection $accounts,
        private readonly Collection $budgetLines,
    ) {}

    public static function forDraft(VoucherDraft $draft): self
    {
        $lines = collect($draft->lines);

        return new self(
            GlAccount::query()->whereIn('code', $lines->pluck('accountCode')->unique()->all())->get()->keyBy('code'),
            GlBudgetLine::query()->whereIn('key', $lines->pluck('budgetLineKey')->filter()->unique()->all())->get()->keyBy('key'),
        );
    }

    public function account(string $code): ?GlAccount
    {
        return $this->accounts->get($code);
    }

    public function budgetLine(string $key): ?GlBudgetLine
    {
        return $this->budgetLines->get($key);
    }

    public function legacyIdExists(int $legacyActionId): bool
    {
        return Voucher::withTrashed()->where('legacy_action_id', $legacyActionId)->exists();
    }
}
