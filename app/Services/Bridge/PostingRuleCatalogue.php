<?php

namespace App\Services\Bridge;

use App\Services\Ledger\AmendApprovedVoucher;
use App\Services\Ledger\Rules\ApproverIsNotPreparer;
use App\Services\Ledger\Rules\BalancedInUsd;
use App\Services\Ledger\Rules\BudgetLineMatchesProjectAndAccount;
use App\Services\Ledger\Rules\CashAccountInOwnCurrency;
use App\Services\Ledger\Rules\DebitOrCreditNeverBoth;
use App\Services\Ledger\Rules\HawalaReceiptIsConsistent;
use App\Services\Ledger\Rules\MigratedVoucherKeepsLegacyId;
use App\Services\Ledger\Rules\MonthClosesOnlyWhenClearingIsZero;
use App\Services\Ledger\Rules\MonthIsOpen;
use App\Services\Ledger\Rules\NetAssetsAreComputed;
use App\Services\Ledger\Rules\OnlyActivePostingAccounts;
use App\Services\Ledger\Rules\RequestRequiredAboveLimit;
use App\Services\Ledger\Rules\RequiredTagsFilled;
use App\Services\Ledger\Rules\UsedAccountKeepsIdentity;
use App\Services\Ledger\VoucherNumberAllocator;
use App\Support\Navigation;
use Illuminate\Routing\ViewController;
use Illuminate\Support\Facades\Route;

/**
 * The posting rules of the plan (1 to 13) and the three the Financial Manager added (14, 15, and 16 from request 03), with the class that
 * enforces each and the screen it runs from. A rule is live when its class exists and its screen is built; it is
 * code only when nothing on screen calls it yet; it is described when only the plan has it.
 */
class PostingRuleCatalogue
{
    public const LIVE = 'live';

    public const CODE_ONLY = 'code';

    public const DESCRIBED = 'described';

    /** Rule number => [enforcing class, screen slug that runs it, or null when no screen calls it yet]. */
    public const RULES = [
        1 => [OnlyActivePostingAccounts::class, 'vouchers'],
        2 => [CashAccountInOwnCurrency::class, 'vouchers'],
        3 => [DebitOrCreditNeverBoth::class, 'vouchers'],
        4 => [BalancedInUsd::class, 'vouchers'],
        5 => [RequiredTagsFilled::class, 'vouchers'],
        6 => [BudgetLineMatchesProjectAndAccount::class, 'vouchers'],
        7 => [ApproverIsNotPreparer::class, 'approval'],
        8 => [MonthIsOpen::class, 'vouchers'],
        // The service exists, but no screen edits an approved voucher yet.
        9 => [AmendApprovedVoucher::class, null],
        10 => [VoucherNumberAllocator::class, 'vouchers'],
        // The chart screen locks the code, class and currency of a used account itself.
        11 => [UsedAccountKeepsIdentity::class, 'chart'],
        12 => [MonthClosesOnlyWhenClearingIsZero::class, 'opening'],
        13 => [MigratedVoucherKeepsLegacyId::class, 'dbload'],
        14 => [RequestRequiredAboveLimit::class, 'vouchers'],
        15 => [HawalaReceiptIsConsistent::class, 'vouchers'],
        16 => [NetAssetsAreComputed::class, 'vouchers'],
    ];

    /** @return list<array{number: int, class: string, screen: string|null, state: string}> */
    public function rules(): array
    {
        $rules = [];

        foreach (self::RULES as $number => [$class, $screen]) {
            $state = match (true) {
                ! class_exists($class) => self::DESCRIBED,
                $screen !== null && self::screenIsBuilt($screen) => self::LIVE,
                default => self::CODE_ONLY,
            };

            $rules[] = ['number' => $number, 'class' => class_basename($class), 'screen' => $screen, 'state' => $state];
        }

        return $rules;
    }

    /** A screen is built when its route runs a component, not the placeholder page. */
    public static function screenIsBuilt(string $slug): bool
    {
        $route = Route::getRoutes()->getByName(Navigation::routeName($slug));

        return $route !== null && ltrim($route->getActionName(), '\\') !== ViewController::class;
    }
}
