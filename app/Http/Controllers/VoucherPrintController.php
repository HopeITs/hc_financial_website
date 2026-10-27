<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Voucher;
use Illuminate\Contracts\View\View;

/**
 * A voucher laid out for paper: header, lines in both currencies, total, and the places to sign.
 * The route's `screen` middleware checks that the person may view vouchers.
 */
class VoucherPrintController extends Controller
{
    public function __invoke(string $voucher): View
    {
        $found = Voucher::withTrashed()->with(['lines.account', 'lines.project'])->findOrFail($voucher);

        $names = [];
        foreach (User::query()->whereIn('ID', array_filter([$found->prepared_by, $found->approved_by]))->get() as $person) {
            $names[(int) $person->getAuthIdentifier()] = $person->name;
        }

        return view('vouchers.print', ['voucher' => $found, 'names' => $names]);
    }
}
