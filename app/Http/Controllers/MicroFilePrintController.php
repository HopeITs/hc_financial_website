<?php

namespace App\Http\Controllers;

use App\Models\Donor;
use App\Models\MicroLoan;
use App\Models\MicroPurchase;
use App\Models\User;
use App\Services\Loans\MicroFiles;
use App\Services\Loans\MicroPurchases;
use App\Support\AmountInWords;
use App\Support\Money;
use Illuminate\Contracts\View\View;

/**
 * The paper forms of a micro-project file, filled from the program (finance request of 2026-11-23): the purchase
 * request, the quote list for a merchant, the payment order, the trust bond, the receipt protocol, the equipment
 * receipt undertaking and the payment card. The route's `screen` middleware checks that the person may view the files.
 */
class MicroFilePrintController extends Controller
{
    public const FORMS = ['purchase', 'quote', 'order', 'bond', 'protocol', 'undertaking', 'card'];

    public function __invoke(int $file, string $form, MicroFiles $files, MicroPurchases $purchases, AmountInWords $words): View
    {
        abort_unless(in_array($form, self::FORMS, true), 404);
        $user = auth()->user();
        abort_unless($user instanceof User, 403);
        $found = MicroLoan::query()->visibleTo($user)->with(['items', 'quotes', 'purchases', 'instalments', 'project'])->findOrFail($file);
        $invoices = $found->purchases->where('kind', MicroPurchase::INVOICE)->values();
        $totals = $purchases->totals($found);
        $total = $found->stage === MicroLoan::ACTIVE ? (string) $found->trust_bond_amount : ($totals['invoiced'] !== '0.00' ? $totals['invoiced'] : $totals['approved']);

        $names = [];
        foreach (User::query()->whereIn('ID', array_column($found->approvals ?? [], 'user'))->get() as $person) {
            $names[(int) $person->getAuthIdentifier()] = $person->name;
        }

        return view('micro-files.print', [
            'file' => $found,
            'form' => $form,
            'invoices' => $invoices,
            'totals' => $totals,
            'total' => $total,
            'words' => ['ar' => $words->arabic($total, $found->currency), 'en' => $words->english($total, $found->currency)],
            'usdValue' => $files->usdValue($found),
            'names' => $names,
            'donor' => Donor::forProject($found->project, $found->funder),
            'delivered' => $this->delivered($totals['invoiced'], $found->currency, $words),
        ]);
    }

    /**
     * The total the delivery protocol declares received (finance form of 2026-11-28): in the file's currency, and for
     * pounds also in new pounds, each in figures and in words. The items' costs show in new pounds.
     *
     * @param  numeric-string  $invoiced
     * @return list<array{amount: string, currency: string, ar: string, en: string}>
     */
    private function delivered(string $invoiced, string $currency, AmountInWords $words): array
    {
        $rows = [['amount' => $invoiced, 'currency' => $currency, 'ar' => $words->arabic($invoiced, $currency), 'en' => $words->english($invoiced, $currency)]];

        if ($currency === 'SYP') {
            $new = Money::normalize(bcdiv($invoiced, Money::NEW_SYP_DIVISOR, 4));
            $rows[] = ['amount' => $new, 'currency' => 'SYP_NEW', 'ar' => $words->arabic($new, 'SYP_NEW'), 'en' => $words->english($new, 'SYP_NEW')];
        }

        return $rows;
    }
}
