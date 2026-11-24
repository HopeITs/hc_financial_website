<?php

namespace App\Services\Home;

use App\Enums\RequestStatus;
use App\Enums\ScreenAction;
use App\Enums\VoucherStatus;
use App\Models\GlProject;
use App\Models\LedgerRequest;
use App\Models\MicroLoan;
use App\Models\Payroll;
use App\Models\Purchase;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Ledger\VoucherApprovals;
use App\Services\Loans\MicroFiles;
use App\Services\Loans\MicroOverview;
use App\Services\Payroll\Payrolls;
use App\Services\Projects\ProjectAccess;
use App\Services\Purchasing\PurchaseFiles;
use App\Services\Requests\RequestWorkflow;
use App\Support\Money;
use App\Support\Navigation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Everything waiting for the person's approval, in one place (finance decision of 2026-11-25): vouchers, project
 * requests, purchase orders, payrolls and micro-project payment orders. Each item approves through its own rules, the
 * same as on its own screen; to send one back or reject it, the person opens it.
 */
class ApprovalsInbox
{
    public const KINDS = ['vouchers', 'requests', 'purchases', 'payrolls', 'micro'];

    public function __construct(private readonly ProjectAccess $access) {}

    /**
     * The items waiting for the person, by kind.
     *
     * @return array<string, list<array{id: string, title: string, detail: string, amount: string, currency: string, url: string}>>
     */
    public function for(User $user): array
    {
        $inbox = array_fill_keys(self::KINDS, []);
        $id = (int) $user->getAuthIdentifier();

        if ($user->canOnScreen('vouchers', ScreenAction::Approve)) {
            $vouchers = Voucher::query()->where('status', VoucherStatus::PendingApproval->value)->where('prepared_by', '!=', $id)->orderBy('date')->limit(200)->get()
                ->filter(fn (Voucher $voucher): bool => Gate::forUser($user)->allows('approve', $voucher));
            $totals = DB::table('gl_voucher_lines')->whereIn('voucher_id', $vouchers->pluck('id')->all())->groupBy('voucher_id')->selectRaw('voucher_id, sum(debit_usd) as total')->pluck('total', 'voucher_id');

            foreach ($vouchers as $voucher) {
                $inbox['vouchers'][] = [
                    'id' => (string) $voucher->id, 'title' => (string) $voucher->id, 'detail' => $voucher->date->format('d/m/Y').' · '.$voucher->description,
                    'amount' => Money::normalize($totals[$voucher->id] ?? '0'), 'currency' => 'USD',
                    'url' => route(Navigation::routeName('vouchers'), ['voucher' => $voucher->id]),
                ];
            }
        }

        if ($user->canOnScreen('requests', ScreenAction::Approve) && Schema::hasTable('gl_requests')) {
            $limit = $this->access->limitedTo($user);
            $requests = LedgerRequest::query()->where('status', RequestStatus::Submitted->value)
                ->when($limit !== null, fn ($query) => $query->whereIn('project_code', GlProject::query()->whereIn('legacy_account_id', $limit ?? [])->select('code')))
                ->orderBy('id')->get()->filter(fn (LedgerRequest $request): bool => Gate::forUser($user)->allows('decide', $request));

            foreach ($requests as $request) {
                $inbox['requests'][] = [
                    'id' => (string) $request->id, 'title' => '#'.$request->id.' · '.$request->project_code, 'detail' => (string) ($request->description ?? __('requests.type.'.$request->type)),
                    'amount' => Money::normalize($request->amount), 'currency' => 'USD',
                    'url' => route(Navigation::routeName('requests'), ['request' => $request->id]),
                ];
            }
        }

        if ($user->canOnScreen('purchases', ScreenAction::Approve) && Schema::hasTable('gl_purchases')) {
            $files = app(PurchaseFiles::class);

            foreach (Purchase::query()->with('awardedQuote')->where('stage', Purchase::SUBMITTED)->orderBy('id')->get() as $purchase) {
                if ($files->mayApprove($user, $purchase)) {
                    $inbox['purchases'][] = [
                        'id' => (string) $purchase->id, 'title' => $purchase->number('PO').' · '.$purchase->project_code,
                        'detail' => $purchase->title.' · '.($purchase->awardedQuote?->supplier ?? ''),
                        'amount' => $files->orderTotal($purchase), 'currency' => $purchase->currency,
                        'url' => route(Navigation::routeName('purchases'), ['file' => $purchase->id]),
                    ];
                }
            }
        }

        if ($user->canOnScreen('payroll', ScreenAction::Approve) && Schema::hasTable('gl_payrolls')) {
            $payrolls = app(Payrolls::class);

            foreach (Payroll::query()->with('lines')->where('status', Payroll::SUBMITTED)->orderBy('month')->get() as $payroll) {
                if ($payrolls->mayApprove($user, $payroll)) {
                    $inbox['payrolls'][] = [
                        'id' => (string) $payroll->id, 'title' => $payroll->project_code.' · '.$payroll->month->format('m/Y'),
                        'detail' => trans_choice('approvals.employees', $payroll->lines->count(), ['count' => $payroll->lines->count()]),
                        'amount' => $payrolls->totals($payroll)['paid'], 'currency' => $payroll->pay_currency,
                        'url' => route(Navigation::routeName('payroll'), ['payroll' => $payroll->id]),
                    ];
                }
            }
        }

        if ($user->canOnScreen('microfiles', ScreenAction::Approve) && Schema::hasColumn('gl_micro_loans', 'stage')) {
            foreach (app(MicroOverview::class)->waitingFor($user)['approve'] as $file) {
                $inbox['micro'][] = [
                    'id' => (string) $file->id, 'title' => $file->beneficiary, 'detail' => $file->label(),
                    'amount' => Money::normalize($file->requested_amount ?? '0'), 'currency' => $file->currency,
                    'url' => route(Navigation::routeName('microfiles'), ['file' => $file->id]),
                ];
            }
        }

        return $inbox;
    }

    /** How many items wait for the person. */
    public function count(User $user): int
    {
        return array_sum(array_map('count', $this->for($user)));
    }

    /**
     * Approves one item through its own rules.
     *
     * @return list<string> why it could not be approved; empty when it was
     */
    public function approve(User $user, string $kind, string $id): array
    {
        try {
            match ($kind) {
                'vouchers' => app(VoucherApprovals::class)->approve(Voucher::query()->findOrFail($id), $user),
                'requests' => app(RequestWorkflow::class)->approve($user, LedgerRequest::query()->findOrFail((int) $id)),
                'purchases' => app(PurchaseFiles::class)->approve($user, Purchase::query()->findOrFail((int) $id)),
                'payrolls' => app(Payrolls::class)->approve($user, Payroll::query()->findOrFail((int) $id)),
                'micro' => app(MicroFiles::class)->approve($user, MicroLoan::query()->findOrFail((int) $id)),
                default => throw new RuntimeException('Unknown kind'),
            };
        } catch (Throwable $refused) {
            return $this->reasons($refused);
        }

        return [];
    }

    /** @return list<string> */
    private function reasons(Throwable $refused): array
    {
        if (property_exists($refused, 'problems') && is_array($refused->problems)) {
            return array_values(array_map('strval', $refused->problems));
        }

        if (property_exists($refused, 'violations') && is_array($refused->violations)) {
            return array_values(array_map(fn ($violation): string => (string) $violation->message, $refused->violations));
        }

        if ($refused instanceof AuthorizationException || $refused instanceof HttpException) {
            return [__('approvals.not_yours')];
        }

        throw $refused;
    }
}
