<?php

namespace App\Services\Purchasing;

use App\Models\Purchase;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Entries\Advances;
use App\Services\Entries\CashVouchers;
use App\Services\Entries\EntryException;
use App\Services\Ledger\VoucherDocuments;
use App\Support\Money;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * The last step of a purchase file (finance decision of 2026-11-24): its invoice paid to the supplier, in one of two ways.
 *
 * - Directly, in cash or from the bank: a payment voucher to the supplier, one line per item on its budget line (and the
 *   other charges on the first one), linked to the file's request (CashVouchers).
 * - Through the purchasing officer's advance: the officer's open advance on the project is settled with the invoice, in
 *   one journal voucher (Advances); what the invoice costs above the advance is paid in cash.
 *
 * Either voucher waits for approval like any other; when it is rejected or deleted the file is back to "received".
 */
class PurchasePayments
{
    public function __construct(
        private readonly PurchaseFiles $files,
        private readonly CashVouchers $cash,
        private readonly Advances $advances,
    ) {}

    /**
     * The purchasing officers' open advances the file can be paid from: on its project, in its currency.
     *
     * @return list<array<string, mixed>>
     */
    public function advancesFor(Purchase $purchase): array
    {
        return array_values(array_filter($this->advances->open(), fn (array $advance): bool => $advance['project'] === $purchase->project_code && $advance['currency'] === $purchase->currency));
    }

    /**
     * What the file's payment voucher holds, one line per item at the awarded price and the other charges.
     *
     * @return list<array{project: string, budget_line: string, account: string, description: string, amount: string}>
     */
    public function lines(Purchase $purchase): array
    {
        $rows = [];

        foreach ($this->files->orderLines($purchase) as $line) {
            $rows[] = [
                'project' => $purchase->project_code, 'budget_line' => $line['item']->budget_line_key, 'account' => '',
                'description' => $line['item']->description, 'amount' => $line['total'],
            ];
        }

        if ($rows !== [] && bccomp(Money::normalize($purchase->other_charges), '0', 2) === 1) {
            $rows[] = [...$rows[0], 'description' => __('purchases.other_charges'), 'amount' => Money::normalize($purchase->other_charges)];
        }

        return $rows;
    }

    /**
     * Pays the file.
     *
     * @param  array{method: string, date: string, account?: string, rate?: string, center?: string, advance?: string}  $form
     * @param  iterable<TemporaryUploadedFile>  $attachments
     *
     * @throws EntryException
     */
    public function pay(User $by, Purchase $purchase, array $form, iterable $attachments): Voucher
    {
        $problems = [];

        if ($purchase->stage !== Purchase::RECEIVED) {
            $problems[] = __('purchases.problem.stage');
        } elseif ($purchase->accepted !== true) {
            $problems[] = __('purchases.problem.not_accepted');
        } elseif (bccomp(Money::normalize($purchase->invoice_amount), $this->files->orderTotal($purchase), 2) !== 0) {
            $problems[] = __('purchases.problem.invoice_differs', ['invoice' => Money::normalize($purchase->invoice_amount), 'order' => $this->files->orderTotal($purchase)]);
        }

        if (! in_array($form['method'], [Purchase::CASH, Purchase::ADVANCE], true)) {
            $problems[] = __('purchases.problem.method');
        }

        if ($problems !== []) {
            throw new EntryException($problems);
        }

        $supplier = (string) $purchase->awardedQuote?->supplier;
        $description = __('purchases.payment_description', ['order' => $purchase->number('PO'), 'title' => $purchase->title, 'supplier' => $supplier]);

        if ($form['method'] === Purchase::CASH) {
            $account = $this->cash->cashAccounts()->get((string) ($form['account'] ?? ''));

            if ($account !== null && $account->currency !== $purchase->currency) {
                throw new EntryException([__('purchases.problem.cash_currency', ['currency' => $purchase->currency])]);
            }

            $voucher = $this->cash->save(CashVouchers::PAYMENT, [
                'date' => $form['date'], 'account' => (string) ($form['account'] ?? ''), 'rate' => (string) ($form['rate'] ?? ''),
                'party' => $supplier, 'reference' => (string) $purchase->invoice_number, 'center' => (string) ($form['center'] ?? ''),
                'description' => $description, 'request' => (string) $purchase->request_id,
            ], $this->lines($purchase), $attachments, $by);
        } else {
            $key = (string) ($form['advance'] ?? '');

            if (! collect($this->advancesFor($purchase))->contains('key', $key)) {
                throw new EntryException([__('purchases.problem.advance')]);
            }

            $voucher = $this->advances->settle($key, $form['date'], $this->lines($purchase), false, (string) ($form['account'] ?? ''), (string) ($form['center'] ?? ''), $description, $attachments, $by);
        }

        $purchase->update(['voucher_id' => $voucher->id, 'payment_method' => $form['method'], 'stage' => Purchase::PAID]);
        // The supplier's invoice is the document the donor's report lists (finance request of 2026-11-26).
        app(VoucherDocuments::class)->record($voucher, 'invoice', (string) $purchase->invoice_number, null);

        return $voucher;
    }
}
