<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Models\User;
use App\Services\Payroll\Payrolls;
use App\Support\AmountInWords;
use Illuminate\Contracts\View\View;

/**
 * A payroll's papers, on the organisation's letterhead (finance request of 2026-11-24): the payroll summary of the
 * month, and the payslips, one page per employee, like the payslip template. The route's `screen` middleware checks
 * that the person may view payrolls.
 */
class PayrollPrintController extends Controller
{
    public const FORMS = ['summary', 'payslips'];

    public function __invoke(int $payroll, string $form, Payrolls $payrolls, AmountInWords $words): View
    {
        abort_unless(in_array($form, self::FORMS, true), 404);
        $found = Payroll::query()->with(['lines', 'project'])->findOrFail($payroll);

        return view('payroll.print', [
            'payroll' => $found,
            'form' => $form,
            'totals' => $payrolls->totals($found),
            'words' => fn (string $amount): string => $words->english($amount, $found->pay_currency),
            'preparer' => User::query()->find($found->prepared_by)?->name ?? '',
        ]);
    }
}
