<?php

namespace App\Services\Purchasing;

use App\Enums\RequestStatus;
use App\Enums\RoleId;
use App\Models\GlBudgetLine;
use App\Models\LedgerRequest;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseQuote;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Approvals\ApprovalChain;
use App\Services\Entries\EntryException;
use App\Services\Ledger\Currencies;
use App\Services\Treasury\MonthlyRates;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The procurement cycle of a purchase file (finance request of 2026-11-24), on the organisation's forms:
 *
 * 1. Purchase request: an approved request of a project, and what this purchase buys from it (items on its budget lines
 *    with estimated prices).
 * 2. Quotes: the suppliers' prices for each item. Up to 500 USD one quote is enough, up to 2,000 USD two, above that
 *    three. The bids are compared and a supplier is awarded, with the reason.
 * 3. Purchase order: approved by the Financial Manager and, above 2,000 USD, the General Manager.
 * 4. Goods or service received, with the supplier's invoice: how much came and in what state, or when the service was
 *    done and how well, and whether it is accepted.
 * 5. Payment (PurchasePayments): a payment voucher to the supplier, or the purchasing officer's advance settled with the
 *    invoice.
 */
class PurchaseFiles
{
    public const CURRENCIES = ['USD', 'SYP'];

    /** One quote up to this value in USD, two up to the next, three above it (finance decision of 2026-11-24). */
    public const ONE_QUOTE_UP_TO_USD = '500.00';

    public const TWO_QUOTES_UP_TO_USD = '2000.00';

    /** The forms have room for twenty items, like the Word templates. */
    public const MAX_ITEMS = 20;

    public function __construct(private readonly ApprovalChain $chain, private readonly MonthlyRates $monthly) {}

    /**
     * The approved requests a purchase can be made from, newest first.
     *
     * @return Collection<int, LedgerRequest>
     */
    public function openRequests(): Collection
    {
        return LedgerRequest::query()->where('status', RequestStatus::Approved->value)->orderByDesc('id')->limit(200)->get();
    }

    /**
     * Opens a purchase file on an approved request.
     *
     * @param  array{request: string, kind: string, title: string, location?: string, delivery_place?: string, needed_by?: string, currency: string}  $header
     * @param  list<array{budget_line: string, description: string, unit?: string, quantity: string, estimated_price: string, remarks?: string}>  $items
     *
     * @throws EntryException
     */
    public function create(User $by, array $header, array $items): Purchase
    {
        $request = ctype_digit(trim($header['request'])) ? LedgerRequest::query()->find((int) $header['request']) : null;

        if ($request === null || $request->state() !== RequestStatus::Approved) {
            throw new EntryException([__('purchases.problem.request')]);
        }

        [$clean, $rows] = $this->validated($header, $items, $request->project_code);

        return DB::transaction(function () use ($by, $request, $clean, $rows): Purchase {
            $purchase = Purchase::query()->create($clean + [
                'request_id' => $request->id, 'project_code' => $request->project_code, 'stage' => Purchase::SOURCING,
                'created_by' => (int) $by->getAuthIdentifier(),
            ]);
            $purchase->items()->createMany($rows);

            return $purchase->refresh();
        });
    }

    /**
     * Changes the file's details while its quotes are gathered; the items change only until the first quote, as the
     * quotes price them.
     *
     * @param  array{kind: string, title: string, location?: string, delivery_place?: string, needed_by?: string, currency: string}  $header
     * @param  list<array{budget_line: string, description: string, unit?: string, quantity: string, estimated_price: string, remarks?: string}>  $items
     *
     * @throws EntryException
     */
    public function update(Purchase $purchase, array $header, array $items): Purchase
    {
        $this->assertStage($purchase, [Purchase::SOURCING]);
        [$clean, $rows] = $this->validated($header, $items, $purchase->project_code);
        $priced = $purchase->quotes()->exists();

        if ($priced && $clean['currency'] !== $purchase->currency) {
            throw new EntryException([__('purchases.problem.currency_quoted')]);
        }

        return DB::transaction(function () use ($purchase, $clean, $rows, $priced): Purchase {
            $purchase->update($clean);

            if (! $priced) {
                $purchase->items()->delete();
                $purchase->items()->createMany($rows);
            }

            return $purchase->refresh();
        });
    }

    /** How many quotes the file needs, by the value of its items in USD. */
    public function quotesNeeded(Purchase $purchase): int
    {
        $usd = $this->usd($purchase, $this->estimatedTotal($purchase));

        return match (true) {
            bccomp($usd, self::ONE_QUOTE_UP_TO_USD, 2) <= 0 => 1,
            bccomp($usd, self::TWO_QUOTES_UP_TO_USD, 2) <= 0 => 2,
            default => 3,
        };
    }

    /** @return numeric-string the estimated value of the items, in the file's currency */
    public function estimatedTotal(Purchase $purchase): string
    {
        return $purchase->items->reduce(fn (string $sum, PurchaseItem $item): string => bcadd($sum, $this->times($item->quantity, $item->estimated_price), 2), Money::zero());
    }

    /**
     * Adds a supplier's quote with its unit price for each item (item id => price and brand), and its scan.
     *
     * @param  array{supplier: string, address?: string, phone?: string, quoted_on?: string, valid_for?: string}  $quote
     * @param  array<int|string, array{price?: string, brand?: string}>  $prices
     *
     * @throws EntryException
     */
    public function addQuote(User $by, Purchase $purchase, array $quote, array $prices, ?UploadedFile $scan): PurchaseQuote
    {
        $this->assertStage($purchase, [Purchase::SOURCING]);
        $problems = [];
        $day = $this->day((string) ($quote['quoted_on'] ?? ''));

        if (trim($quote['supplier']) === '') {
            $problems[] = __('purchases.problem.supplier');
        }

        if (trim((string) ($quote['quoted_on'] ?? '')) !== '' && $day === null) {
            $problems[] = __('purchases.problem.date');
        }

        $rows = [];
        foreach ($purchase->items as $item) {
            $text = trim((string) ($prices[$item->id]['price'] ?? ''));
            $price = $this->number($text);

            if ($price === null) {
                $problems[] = __('purchases.problem.quote_price', ['line' => $item->line_no]);

                continue;
            }

            $rows[] = ['item_id' => $item->id, 'unit_price' => $price, 'brand' => $this->text($prices[$item->id]['brand'] ?? null)];
        }

        if ($problems !== []) {
            throw new EntryException($problems);
        }

        $stored = null;
        if ($scan !== null) {
            $stored = $scan->storeAs('purchase-quotes/'.$purchase->id, Str::uuid()->toString().'.'.($scan->guessExtension() ?: 'bin'), 'local');
            if ($stored === false) {
                throw new EntryException([__('purchases.problem.upload')]);
            }
        }

        return DB::transaction(function () use ($by, $purchase, $quote, $day, $rows, $scan, $stored): PurchaseQuote {
            $created = $purchase->quotes()->create([
                'supplier' => mb_substr(trim($quote['supplier']), 0, 255), 'address' => $this->text($quote['address'] ?? null),
                'phone' => $this->text($quote['phone'] ?? null, 40), 'quoted_on' => $day?->toDateString(),
                'valid_for' => $this->text($quote['valid_for'] ?? null, 60),
                'original_name' => $scan !== null ? mb_substr(basename($scan->getClientOriginalName()), 0, 250) : null,
                'stored_path' => $stored ?: null, 'created_by' => (int) $by->getAuthIdentifier(),
            ]);
            $created->prices()->createMany($rows);

            return $created;
        });
    }

    /** @throws EntryException */
    public function removeQuote(Purchase $purchase, int $quoteId): void
    {
        $this->assertStage($purchase, [Purchase::SOURCING]);
        $quote = $purchase->quotes()->whereKey($quoteId)->first();

        if ($quote === null) {
            return;
        }

        if ($quote->stored_path !== null) {
            Storage::disk('local')->delete($quote->stored_path);
        }

        if ($purchase->awarded_quote_id === $quote->id) {
            $purchase->update(['awarded_quote_id' => null]);
        }

        $quote->delete();
    }

    /**
     * The comparison of the bids: each quote's total and unit prices, and the lowest.
     *
     * @return array{quotes: list<array{quote: PurchaseQuote, prices: array<int, array{price: string, total: string, brand: string|null}>, total: string}>, lowest: int|null}
     */
    public function comparison(Purchase $purchase): array
    {
        $rows = [];
        $lowest = null;
        $lowestTotal = null;
        $items = $purchase->items->keyBy('id');

        foreach ($purchase->quotes()->with('prices')->get() as $quote) {
            $prices = [];
            $total = Money::zero();

            foreach ($quote->prices as $price) {
                $item = $items->get($price->item_id);
                if ($item === null) {
                    continue;
                }

                $line = $this->times($item->quantity, $price->unit_price);
                $prices[$price->item_id] = ['price' => Money::normalize($price->unit_price), 'total' => $line, 'brand' => $price->brand];
                $total = bcadd($total, $line, 2);
            }

            $rows[] = ['quote' => $quote, 'prices' => $prices, 'total' => $total];

            if (count($prices) === $items->count() && ($lowestTotal === null || bccomp($total, $lowestTotal, 2) < 0)) {
                $lowest = $quote->id;
                $lowestTotal = $total;
            }
        }

        return ['quotes' => $rows, 'lowest' => $lowest];
    }

    /**
     * Awards a supplier and sends the purchase order for approval.
     *
     * @throws EntryException
     */
    public function submitOrder(Purchase $purchase, int $quoteId, string $reason, string $otherCharges): Purchase
    {
        $this->assertStage($purchase, [Purchase::SOURCING]);
        $problems = [];
        $needed = $this->quotesNeeded($purchase);
        $count = $purchase->quotes()->count();
        $quote = $purchase->quotes()->with('prices')->whereKey($quoteId)->first();
        $charges = trim($otherCharges) === '' ? Money::zero() : $this->number($otherCharges);

        if (! $purchase->items()->exists()) {
            $problems[] = __('purchases.problem.no_items');
        }

        if ($count < $needed) {
            $problems[] = __('purchases.problem.quotes', ['count' => $needed]);
        }

        if ($quote === null) {
            $problems[] = __('purchases.problem.award');
        } elseif ($quote->prices->count() !== $purchase->items()->count()) {
            $problems[] = __('purchases.problem.award_prices');
        }

        if (trim($reason) === '') {
            $problems[] = __('purchases.problem.award_reason');
        }

        if ($charges === null) {
            $problems[] = __('purchases.problem.other_charges');
        }

        if ($problems !== []) {
            throw new EntryException($problems);
        }

        $purchase->update([
            'awarded_quote_id' => $quoteId, 'award_reason' => trim($reason), 'other_charges' => $charges, 'stage' => Purchase::SUBMITTED,
            'approvals' => [], 'return_reason' => null,
        ]);

        return $purchase->refresh();
    }

    /**
     * The purchase order's lines at the awarded supplier's prices.
     *
     * @return list<array{item: PurchaseItem, price: string, total: string, brand: string|null}>
     */
    public function orderLines(Purchase $purchase): array
    {
        $prices = $purchase->awarded_quote_id === null ? collect()
            : PurchaseQuote::query()->with('prices')->find($purchase->awarded_quote_id)?->prices->keyBy('item_id') ?? collect();
        $lines = [];

        foreach ($purchase->items as $item) {
            $price = $prices->get($item->id);
            $unit = $price !== null ? Money::normalize($price->unit_price) : Money::zero();
            $lines[] = ['item' => $item, 'price' => $unit, 'total' => $this->times($item->quantity, $unit), 'brand' => $price?->brand];
        }

        return $lines;
    }

    /** @return numeric-string the purchase order's total: its lines and the other charges */
    public function orderTotal(Purchase $purchase): string
    {
        $lines = array_reduce($this->orderLines($purchase), fn (string $sum, array $line): string => bcadd($sum, $line['total'], 2), Money::zero());

        return bcadd($lines, Money::normalize($purchase->other_charges), 2);
    }

    /** The role whose approval the purchase order waits for, or null. */
    public function nextRole(Purchase $purchase): ?RoleId
    {
        return $purchase->stage === Purchase::SUBMITTED ? $this->chain->nextStep($this->usd($purchase, $this->orderTotal($purchase)), $this->approvedRoles($purchase)) : null;
    }

    public function mayApprove(User $user, Purchase $purchase): bool
    {
        return $purchase->stage === Purchase::SUBMITTED
            && $this->chain->mayApproveNext($this->usd($purchase, $this->orderTotal($purchase)), $this->approvedRoles($purchase), $user->roleIds());
    }

    /**
     * One approval of the purchase order: the Financial Manager, then, above 2,000 USD, the General Manager. The last one
     * orders it.
     *
     * @throws EntryException
     */
    public function approve(User $by, Purchase $purchase): Purchase
    {
        return DB::transaction(function () use ($by, $purchase): Purchase {
            $purchase = Purchase::query()->lockForUpdate()->findOrFail($purchase->id);
            $role = $this->nextRole($purchase);

            if ($role === null || ! $this->mayApprove($by, $purchase)) {
                throw new EntryException([__('purchases.problem.not_your_step')]);
            }

            $approvals = [...($purchase->approvals ?? []), ['role' => $role->value, 'user' => (int) $by->getAuthIdentifier(), 'name' => $by->name, 'at' => now()->toDateTimeString()]];
            $done = $this->chain->nextStep($this->usd($purchase, $this->orderTotal($purchase)), [...$this->approvedRoles($purchase), $role]) === null;

            $purchase->update(['approvals' => $approvals] + ($done ? ['stage' => Purchase::ORDERED, 'approved_at' => now(), 'ordered_on' => CarbonImmutable::today()->toDateString()] : []));

            return $purchase->refresh();
        });
    }

    /** @throws EntryException */
    public function returnForChanges(User $by, Purchase $purchase, string $reason): Purchase
    {
        $this->assertStage($purchase, [Purchase::SUBMITTED]);

        if (! $this->mayApprove($by, $purchase)) {
            throw new EntryException([__('purchases.problem.not_your_step')]);
        }

        if (trim($reason) === '') {
            throw new EntryException([__('purchases.problem.return_reason')]);
        }

        $purchase->update(['stage' => Purchase::SOURCING, 'approvals' => [], 'return_reason' => trim($reason)]);

        return $purchase->refresh();
    }

    /**
     * Records the goods or the service received and the supplier's invoice (GRN or SRN).
     *
     * @param  array{received_on: string, receiving_location?: string, invoice_number: string, invoice_date?: string, invoice_amount: string, accepted: bool, remarks?: string}  $receipt
     * @param  array<int|string, array{received_quantity?: string, condition?: string, expected_on?: string, completed_on?: string}>  $items
     *
     * @throws EntryException
     */
    public function receive(Purchase $purchase, array $receipt, array $items): Purchase
    {
        $this->assertStage($purchase, [Purchase::ORDERED, Purchase::RECEIVED]);
        $problems = [];
        $receivedOn = $this->day($receipt['received_on']);
        $invoiceDate = trim((string) ($receipt['invoice_date'] ?? '')) === '' ? null : $this->day((string) $receipt['invoice_date']);
        $amount = $this->number($receipt['invoice_amount']);

        if ($receivedOn === null) {
            $problems[] = __('purchases.problem.received_on');
        }

        if (trim($receipt['invoice_number']) === '') {
            $problems[] = __('purchases.problem.invoice_number');
        }

        if (trim((string) ($receipt['invoice_date'] ?? '')) !== '' && $invoiceDate === null) {
            $problems[] = __('purchases.problem.date');
        }

        if ($amount === null || bccomp($amount, '0', 2) !== 1) {
            $problems[] = __('purchases.problem.invoice_amount');
        }

        $updates = [];
        foreach ($purchase->items as $item) {
            $row = $items[$item->id] ?? [];

            if ($purchase->kind === Purchase::GOODS) {
                $quantity = $this->number((string) ($row['received_quantity'] ?? ''));
                if ($quantity === null) {
                    $problems[] = __('purchases.problem.received_quantity', ['line' => $item->line_no]);

                    continue;
                }

                $updates[$item->id] = ['received_quantity' => $quantity, 'condition' => $this->text($row['condition'] ?? null)];
            } else {
                $expected = trim((string) ($row['expected_on'] ?? '')) === '' ? null : $this->day((string) $row['expected_on']);
                $completed = trim((string) ($row['completed_on'] ?? '')) === '' ? null : $this->day((string) $row['completed_on']);

                if ($completed === null) {
                    $problems[] = __('purchases.problem.completed_on', ['line' => $item->line_no]);

                    continue;
                }

                $updates[$item->id] = ['expected_on' => $expected?->toDateString(), 'completed_on' => $completed->toDateString(), 'condition' => $this->text($row['condition'] ?? null)];
            }
        }

        if ($problems !== []) {
            throw new EntryException($problems);
        }

        return DB::transaction(function () use ($purchase, $receipt, $receivedOn, $invoiceDate, $amount, $updates): Purchase {
            foreach ($updates as $id => $values) {
                PurchaseItem::query()->whereKey($id)->update($values);
            }

            $purchase->update([
                'received_on' => $receivedOn?->toDateString(), 'receiving_location' => $this->text($receipt['receiving_location'] ?? null),
                'invoice_number' => mb_substr(trim($receipt['invoice_number']), 0, 60), 'invoice_date' => $invoiceDate?->toDateString(),
                'invoice_amount' => $amount, 'accepted' => $receipt['accepted'], 'receipt_remarks' => $this->text($receipt['remarks'] ?? null, 2000),
                'stage' => Purchase::RECEIVED,
            ]);

            return $purchase->refresh();
        });
    }

    /** @throws EntryException */
    public function cancel(Purchase $purchase): Purchase
    {
        $this->assertStage($purchase, [Purchase::SOURCING, Purchase::SUBMITTED, Purchase::ORDERED, Purchase::RECEIVED]);
        $purchase->update(['stage' => Purchase::CANCELLED]);

        return $purchase->refresh();
    }

    /** A rejected or deleted payment voucher puts its purchase back to "received", to be paid again. */
    public function forgetVoucher(Voucher $voucher): void
    {
        Purchase::query()->where('voucher_id', $voucher->id)->update(['voucher_id' => null, 'payment_method' => null, 'stage' => Purchase::RECEIVED]);
    }

    /** @return numeric-string an amount of the file's currency in USD, at the month's rate */
    public function usd(Purchase $purchase, string $amount): string
    {
        if ($purchase->currency === Currencies::BASE) {
            return Money::normalize($amount);
        }

        $rate = $this->monthly->forDay($purchase->currency, CarbonImmutable::today())['rate'] ?? null;

        return $rate !== null && bccomp(Money::normalize($rate, 6), '0', 6) === 1 ? Money::normalize(bcdiv($amount, Money::normalize($rate, 6), 6)) : Money::normalize($amount);
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  list<array<string, string>>  $items
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>}
     *
     * @throws EntryException
     */
    private function validated(array $header, array $items, string $projectCode): array
    {
        $problems = [];
        $kind = (string) ($header['kind'] ?? '');
        $currency = (string) ($header['currency'] ?? '');
        $neededText = trim((string) ($header['needed_by'] ?? ''));
        $needed = $neededText === '' ? null : $this->day($neededText);

        if (! in_array($kind, [Purchase::GOODS, Purchase::SERVICE], true)) {
            $problems[] = __('purchases.problem.kind');
        }

        if (trim((string) ($header['title'] ?? '')) === '') {
            $problems[] = __('purchases.problem.title');
        }

        if (! in_array($currency, self::CURRENCIES, true)) {
            $problems[] = __('purchases.problem.currency');
        }

        if ($neededText !== '' && $needed === null) {
            $problems[] = __('purchases.problem.date');
        }

        $lines = GlBudgetLine::query()->where('project_code', $projectCode)->pluck('key')->all();
        $rows = [];
        $number = 0;

        foreach ($items as $item) {
            $description = trim((string) ($item['description'] ?? ''));
            $line = (string) ($item['budget_line'] ?? '');

            if ($description === '' && $line === '' && trim((string) ($item['quantity'] ?? '')) === '' && trim((string) ($item['estimated_price'] ?? '')) === '') {
                continue;
            }

            $number++;
            $quantity = $this->number((string) ($item['quantity'] ?? ''));
            $price = trim((string) ($item['estimated_price'] ?? '')) === '' ? Money::zero() : $this->number((string) $item['estimated_price']);

            if ($description === '') {
                $problems[] = __('purchases.problem.item_description', ['line' => $number]);
            }

            if (! in_array($line, $lines, true)) {
                $problems[] = __('purchases.problem.item_budget_line', ['line' => $number]);
            }

            if ($quantity === null || bccomp($quantity, '0', 2) !== 1) {
                $problems[] = __('purchases.problem.item_quantity', ['line' => $number]);
            }

            if ($price === null) {
                $problems[] = __('purchases.problem.item_price', ['line' => $number]);
            }

            $rows[] = [
                'line_no' => $number, 'budget_line_key' => $line, 'description' => mb_substr($description, 0, 255),
                'unit' => $this->text($item['unit'] ?? null, 40), 'quantity' => $quantity, 'estimated_price' => $price,
                'remarks' => $this->text($item['remarks'] ?? null),
            ];
        }

        if ($rows === []) {
            $problems[] = __('purchases.problem.no_items');
        }

        if (count($rows) > self::MAX_ITEMS) {
            $problems[] = __('purchases.problem.too_many_items', ['max' => self::MAX_ITEMS]);
        }

        if ($problems !== []) {
            throw new EntryException($problems);
        }

        return [[
            'kind' => $kind, 'title' => mb_substr(trim((string) $header['title']), 0, 255), 'currency' => $currency,
            'location' => $this->text($header['location'] ?? null), 'delivery_place' => $this->text($header['delivery_place'] ?? null),
            'needed_by' => $needed?->toDateString(),
        ], $rows];
    }

    /**
     * @param  list<string>  $stages
     *
     * @throws EntryException
     */
    private function assertStage(Purchase $purchase, array $stages): void
    {
        if (! in_array($purchase->stage, $stages, true)) {
            throw new EntryException([__('purchases.problem.stage')]);
        }
    }

    /** @return list<RoleId> */
    private function approvedRoles(Purchase $purchase): array
    {
        return array_values(array_filter(array_map(fn (array $step): ?RoleId => RoleId::tryFrom((int) $step['role']), $purchase->approvals ?? [])));
    }

    /** @return numeric-string */
    private function times(string $quantity, string $price): string
    {
        return Money::normalize(bcmul(Money::normalize($quantity), Money::normalize($price), 4));
    }

    /** @return numeric-string|null */
    private function number(string $text): ?string
    {
        $clean = str_replace([',', ' '], '', trim($text));

        return preg_match('/^\d+(\.\d+)?$/', $clean) === 1 ? Money::normalize($clean) : null;
    }

    private function text(mixed $value, int $length = 255): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : mb_substr($text, 0, $length);
    }

    private function day(string $text): ?CarbonImmutable
    {
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) === 1 ? CarbonImmutable::createFromFormat('!Y-m-d', $text) : false;

        return $day instanceof CarbonImmutable && $day->format('Y-m-d') === $text ? $day : null;
    }
}
