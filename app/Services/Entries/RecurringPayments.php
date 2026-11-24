<?php

namespace App\Services\Entries;

use App\Enums\RequestStatus;
use App\Models\GlBudgetLine;
use App\Models\LedgerRequest;
use App\Models\RecurringPayment;
use App\Models\User;
use App\Models\Voucher;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Recurring payments (finance decision of 2026-11-24), such as rent: each pays itself on its day. On the due day its
 * payment voucher is made from its cash account, on its budget line, at the month's rate for pounds (CashVouchers), and
 * waits for approval like any other; the next due day moves on by its months. A payment that cannot be made keeps its
 * day and says why, to be paid by hand.
 */
class RecurringPayments
{
    public function __construct(private readonly CashVouchers $cash) {}

    /**
     * Adds a recurring payment, or changes one.
     *
     * @param  array<string, string>  $form  budget_line, payee, description, currency, amount, every_months, next_due, ends_on, cash_account, center, request
     *
     * @throws EntryException
     */
    public function save(User $by, ?RecurringPayment $payment, array $form): RecurringPayment
    {
        $problems = [];
        $line = GlBudgetLine::query()->find((string) ($form['budget_line'] ?? ''));
        $amount = $this->number((string) ($form['amount'] ?? ''));
        $every = (int) ($form['every_months'] ?? 0);
        $nextDue = $this->day((string) ($form['next_due'] ?? ''));
        $endsText = trim((string) ($form['ends_on'] ?? ''));
        $endsOn = $endsText === '' ? null : $this->day($endsText);
        $currency = (string) ($form['currency'] ?? '');
        $account = $this->cash->cashAccounts()->get((string) ($form['cash_account'] ?? ''));
        $requestText = trim((string) ($form['request'] ?? ''));
        $request = $requestText === '' ? null : LedgerRequest::query()->find((int) $requestText);

        if ($line === null) {
            $problems[] = __('recurring.problem.budget_line');
        }

        if (trim((string) ($form['payee'] ?? '')) === '') {
            $problems[] = __('recurring.problem.payee');
        }

        if (! in_array($currency, ['USD', 'SYP'], true)) {
            $problems[] = __('recurring.problem.currency');
        }

        if ($amount === null || bccomp($amount, '0', 2) !== 1) {
            $problems[] = __('recurring.problem.amount');
        }

        if (! in_array($every, RecurringPayment::EVERY, true)) {
            $problems[] = __('recurring.problem.every');
        }

        if ($nextDue === null) {
            $problems[] = __('recurring.problem.next_due');
        }

        if ($endsText !== '' && ($endsOn === null || ($nextDue !== null && $endsOn->lt($nextDue)))) {
            $problems[] = __('recurring.problem.ends_on');
        }

        if ($account === null || $account->currency !== $currency) {
            $problems[] = __('recurring.problem.cash_account', ['currency' => $currency]);
        }

        if ($requestText !== '' && ($request === null || $request->state() !== RequestStatus::Approved || $request->project_code !== $line?->project_code)) {
            $problems[] = __('recurring.problem.request');
        }

        if ($problems !== []) {
            throw new EntryException($problems);
        }

        $values = [
            'project_code' => (string) $line?->project_code, 'budget_line_key' => (string) $line?->key,
            'payee' => mb_substr(trim((string) $form['payee']), 0, 255), 'description' => $this->text($form['description'] ?? null),
            'currency' => $currency, 'amount' => $amount, 'every_months' => $every, 'next_due' => $nextDue?->toDateString(),
            'ends_on' => $endsOn?->toDateString(), 'cash_account' => (string) $account?->code, 'center' => $this->text($form['center'] ?? null),
            'request_id' => $request?->id, 'active' => true, 'last_problem' => null,
        ];

        if ($payment === null) {
            return RecurringPayment::query()->create($values + ['created_by' => (int) $by->getAuthIdentifier()]);
        }

        $payment->update($values);

        return $payment->refresh();
    }

    /**
     * The active payments due on or before the day.
     *
     * @return Collection<int, RecurringPayment>
     */
    public function due(CarbonImmutable $today): Collection
    {
        return RecurringPayment::query()
            ->where('active', true)
            ->whereDate('next_due', '<=', $today->toDateString())
            ->orderBy('next_due')->orderBy('id')
            ->get();
    }

    /**
     * Makes the payment voucher of its due day, then moves the day on. A refusal stays on the payment, its day unmoved.
     *
     * @throws EntryException
     */
    public function pay(RecurringPayment $payment, ?User $by = null): Voucher
    {
        $preparer = $by ?? User::query()->find($payment->created_by);

        if (! $preparer instanceof User) {
            throw new EntryException([__('recurring.problem.preparer')]);
        }

        $day = CarbonImmutable::parse($payment->next_due->toDateString());
        $rate = $this->cash->defaultRate($payment->currency, $day)['rate'] ?? '';
        $description = trim((string) $payment->description) !== '' ? (string) $payment->description : __('recurring.default_description', ['payee' => $payment->payee]);

        try {
            $voucher = $this->cash->save(CashVouchers::PAYMENT, [
                'date' => $day->toDateString(), 'account' => $payment->cash_account, 'rate' => (string) $rate, 'party' => $payment->payee,
                'reference' => '', 'center' => (string) $payment->center, 'request' => (string) ($payment->request_id ?? ''),
                'description' => __('recurring.voucher_description', ['description' => $description, 'period' => $day->format('m/Y')]),
            ], [[
                'project' => $payment->project_code, 'budget_line' => $payment->budget_line_key, 'account' => '',
                'description' => $description, 'amount' => Money::normalize($payment->amount),
            ]], [], $preparer);
        } catch (EntryException $refused) {
            $payment->update(['last_problem' => implode(' ', $refused->problems)]);

            throw $refused;
        }

        $next = $day->addMonthsNoOverflow($payment->every_months);
        $ended = $payment->ends_on !== null && $next->gt(CarbonImmutable::parse($payment->ends_on->toDateString()));

        $payment->update([
            'last_voucher_id' => $voucher->id, 'last_problem' => null, 'next_due' => $next->toDateString(), 'active' => ! $ended,
        ]);

        return $voucher;
    }

    /**
     * Pays every payment due on or before the day, once each (the daily run catches up a day at a time).
     *
     * @return array{paid: int, refused: int}
     */
    public function payDue(CarbonImmutable $today): array
    {
        $paid = 0;
        $refused = 0;

        foreach ($this->due($today) as $payment) {
            try {
                $this->pay($payment);
                $paid++;
            } catch (EntryException) {
                $refused++;
            }
        }

        return ['paid' => $paid, 'refused' => $refused];
    }

    /** @return numeric-string|null */
    private function number(string $text): ?string
    {
        $clean = str_replace([',', ' '], '', trim($text));

        return preg_match('/^\d+(\.\d+)?$/', $clean) === 1 ? Money::normalize($clean) : null;
    }

    private function text(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : mb_substr($text, 0, 255);
    }

    private function day(string $text): ?CarbonImmutable
    {
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) === 1 ? CarbonImmutable::createFromFormat('!Y-m-d', $text) : false;

        return $day instanceof CarbonImmutable && $day->format('Y-m-d') === $text ? $day : null;
    }
}
