<?php

namespace App\Http\Controllers;

use App\Models\MicroLoan;
use App\Models\MicroPurchase;
use App\Models\User;
use App\Services\Loans\MicroFiles;
use App\Services\Loans\MicroPurchases;
use App\Support\AmountInWords;
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
        $found = MicroLoan::query()->with(['items', 'quotes', 'purchases', 'instalments', 'project'])->findOrFail($file);
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
        ]);
    }
}
