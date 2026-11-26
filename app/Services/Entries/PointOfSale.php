<?php

namespace App\Services\Entries;

use App\Models\GlProject;
use App\Models\RevenueItem;
use App\Models\RevenueLine;
use App\Models\RevenueSheet;
use App\Models\RevenueTicket;
use App\Models\User;
use App\Services\Projects\ProjectAccess;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The point of sale of the project revenues, worked like a restaurant's (finance requests of 2026-11-26 and 2026-11-27).
 *
 * - A shift is the sales of one project into one cash box on a day (a revenue sheet whose status is `open`).
 * - An order is opened on a table, as take-away or as delivery; items are added to it over time, its lines changed,
 *   and it is paid when the table closes, with a discount as a percent or an amount and the change counted.
 * - Ending the shift books every paid order as one receipt voucher on 4002 by kind, waiting for approval like any
 *   other; a shift cannot end while a table is still open.
 */
class PointOfSale
{
    public function __construct(private readonly ProjectRevenues $revenues, private readonly CashVouchers $cash) {}

    /**
     * Opens a shift, or carries on with the person's open shift of the same project, cash box and day.
     *
     * @param  array<string, string>  $form  project, cash_account, day
     *
     * @throws EntryException
     */
    public function openShift(User $by, array $form): RevenueSheet
    {
        $project = GlProject::query()->where('is_general_account', false)->find((string) ($form['project'] ?? ''));
        $account = $this->cash->cashAccounts()->get((string) ($form['cash_account'] ?? ''));
        $dayText = (string) ($form['day'] ?? '');
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dayText) === 1 ? CarbonImmutable::createFromFormat('!Y-m-d', $dayText) : false;
        $problems = [];

        if ($project === null || ! app(ProjectAccess::class)->canOpen($by, $project->code)) {
            $problems[] = __('revenues.problem.project');
        }

        if ($account === null) {
            $problems[] = __('revenues.problem.cash_account');
        }

        if (! $day instanceof CarbonImmutable) {
            $problems[] = __('revenues.problem.day');
        }

        if ($problems !== [] || $project === null || $account === null || ! $day instanceof CarbonImmutable) {
            throw new EntryException($problems);
        }

        $values = ['project_code' => $project->code, 'cash_account' => $account->code, 'status' => RevenueSheet::OPEN, 'created_by' => (int) $by->getAuthIdentifier()];

        return RevenueSheet::query()->where($values)->whereDate('day', $day->toDateString())->first()
            ?? RevenueSheet::query()->create([...$values, 'day' => $day->toDateString(), 'currency' => $account->currency, 'total' => Money::zero()]);
    }

    /**
     * Opens an order: on a table (its number), or as take-away or delivery. A table that already has an open order gives
     * that order back.
     *
     * @param  array<string, string>  $form  table, service, persons, waiter
     *
     * @throws EntryException
     */
    public function openOrder(User $by, RevenueSheet $shift, array $form): RevenueTicket
    {
        $this->shiftMustBeOpen($shift);
        $service = in_array($form['service'] ?? '', RevenueTicket::SERVICES, true) ? (string) $form['service'] : 'hall';
        $table = mb_substr(trim((string) ($form['table'] ?? '')), 0, 32);

        if ($service === 'hall' && $table === '') {
            throw new EntryException([__('revenues.problem.pos_table')]);
        }

        if ($table !== '' && ($open = RevenueTicket::query()->where('sheet_id', $shift->id)->where('status', RevenueTicket::OPEN)->where('table_label', $table)->first()) !== null) {
            return $open;
        }

        return DB::transaction(function () use ($by, $shift, $form, $service, $table): RevenueTicket {
            $locked = RevenueSheet::query()->lockForUpdate()->findOrFail($shift->id);

            return RevenueTicket::query()->create([
                'sheet_id' => $locked->id, 'number' => (int) RevenueTicket::query()->where('sheet_id', $locked->id)->max('number') + 1,
                'status' => RevenueTicket::OPEN, 'table_label' => $table !== '' ? $table : null, 'service' => $service,
                'persons' => $this->persons((string) ($form['persons'] ?? '')), 'waiter' => $this->text($form['waiter'] ?? null),
                'payment_method' => 'cash', 'gross' => Money::zero(), 'discount' => Money::zero(), 'net' => Money::zero(),
                'created_by' => (int) $by->getAuthIdentifier(),
            ]);
        });
    }

    /**
     * Adds items to an open order: the same item again (without a note) adds to its line.
     *
     * @param  array<int|string, int|string>  $basket  item id => quantity
     *
     * @throws EntryException
     */
    public function addItems(RevenueTicket $order, array $basket): RevenueTicket
    {
        $this->orderMustBeOpen($order);
        $shift = $order->sheet()->firstOrFail();
        $items = RevenueItem::query()->whereIn('id', array_map('intval', array_keys($basket)))->get()->keyBy('id');
        $problems = [];
        $adding = [];

        foreach ($basket as $id => $quantityText) {
            $item = $items->get((int) $id);
            $quantity = $this->number((string) $quantityText);

            if ($item === null || ! $item->active || $item->project_code !== $shift->project_code) {
                $problems[] = __('revenues.problem.pos_item');
            } elseif ($item->currency !== $shift->currency) {
                $problems[] = __('revenues.problem.pos_currency', ['item' => $item->name, 'currency' => $shift->currency]);
            } elseif ($quantity === null || bccomp($quantity, '0', 2) !== 1) {
                $problems[] = __('revenues.problem.pos_quantity', ['item' => $item->name]);
            } else {
                $adding[] = [$item, $quantity];
            }
        }

        if ($adding === [] && $problems === []) {
            $problems[] = __('revenues.problem.pos_empty');
        }

        if ($problems !== []) {
            throw new EntryException(array_values(array_unique($problems)));
        }

        DB::transaction(function () use ($order, $adding): void {
            foreach ($adding as [$item, $quantity]) {
                $line = RevenueLine::query()->where('ticket_id', $order->id)->where('item_id', $item->id)->whereNull('note')->first();

                if ($line instanceof RevenueLine) {
                    $this->changeQuantity($line, bcadd(Money::normalize($line->quantity), $quantity, 2));

                    continue;
                }

                RevenueLine::query()->create([
                    'sheet_id' => $order->sheet_id, 'ticket_id' => $order->id, 'item_id' => $item->id, 'kind' => $item->kind,
                    'description' => mb_substr($item->name, 0, 255), 'quantity' => $quantity, 'unit_price' => Money::normalize($item->unit_price),
                    'amount' => Money::normalize(bcmul($quantity, Money::normalize($item->unit_price), 4)),
                ]);
            }

            $this->total($order);
        });

        return $order->refresh();
    }

    /**
     * Reads "quantity*number" (or just "number") as typed on the add-items window: the item of that number, or of that
     * id, on the shift's price list.
     *
     * @return array{0: RevenueItem, 1: string}|null the item and the quantity
     */
    public function readQuickEntry(RevenueSheet $shift, string $typed): ?array
    {
        $parts = array_map('trim', explode('*', str_replace(['×', 'x', 'X'], '*', trim($typed)), 2));
        [$quantityText, $code] = count($parts) === 2 ? $parts : ['1', $parts[0]];
        $quantity = $this->number($quantityText);

        if ($code === '' || $quantity === null || bccomp($quantity, '0', 2) !== 1) {
            return null;
        }

        $catalog = RevenueItem::query()->where('project_code', $shift->project_code)->where('active', true)->where('currency', $shift->currency);
        $item = (clone $catalog)->where('code', $code)->first() ?? (ctype_digit($code) ? (clone $catalog)->whereKey((int) $code)->first() : null);

        return $item instanceof RevenueItem ? [$item, $quantity] : null;
    }

    /**
     * Changes how many of a line an open order has; zero or less takes the line off.
     *
     * @throws EntryException
     */
    public function setQuantity(RevenueLine $line, string $quantityText): void
    {
        $order = RevenueTicket::query()->findOrFail($line->ticket_id);
        $this->orderMustBeOpen($order);
        $quantity = $this->number(ltrim(trim($quantityText), '-')) ?? Money::zero();

        DB::transaction(function () use ($line, $order, $quantity, $quantityText): void {
            if (str_starts_with(trim($quantityText), '-') || bccomp($quantity, '0', 2) !== 1) {
                $line->delete();
            } else {
                $this->changeQuantity($line, $quantity);
            }

            $this->total($order);
        });
    }

    /** @throws EntryException */
    public function setLineNote(RevenueLine $line, string $note): void
    {
        $this->orderMustBeOpen(RevenueTicket::query()->findOrFail($line->ticket_id));
        $line->update(['note' => $this->text($note)]);
    }

    /**
     * Changes an open order's details: table, service, customer, persons, payment method, waiter, note and discount.
     *
     * @param  array<string, string>  $form
     *
     * @throws EntryException
     */
    public function updateOrder(RevenueTicket $order, array $form): RevenueTicket
    {
        $this->orderMustBeOpen($order);
        $percent = trim((string) ($form['discount_percent'] ?? '')) === '' ? null : $this->number((string) $form['discount_percent']);
        $amount = trim((string) ($form['discount'] ?? '')) === '' ? Money::zero() : $this->number((string) $form['discount']);
        $payment = in_array($form['payment_method'] ?? '', RevenueTicket::PAYMENTS, true) ? (string) $form['payment_method'] : 'cash';

        if ((trim((string) ($form['discount_percent'] ?? '')) !== '' && ($percent === null || bccomp($percent, '100', 2) === 1))
            || $amount === null || ($percent === null && bccomp($amount, Money::normalize($order->gross), 2) === 1)) {
            throw new EntryException([__('revenues.problem.pos_discount')]);
        }

        DB::transaction(function () use ($order, $form, $percent, $amount, $payment): void {
            $order->update([
                'table_label' => $this->text($form['table'] ?? null, 32), 'service' => in_array($form['service'] ?? '', RevenueTicket::SERVICES, true) ? (string) $form['service'] : $order->service,
                'customer' => $this->text($form['customer'] ?? null), 'persons' => $this->persons((string) ($form['persons'] ?? '')),
                'waiter' => $this->text($form['waiter'] ?? null), 'note' => $this->text($form['note'] ?? null), 'payment_method' => $payment,
                // A guest of the house pays nothing: the whole order is the discount.
                'discount_percent' => $payment === 'free' ? '100' : $percent,
                'discount' => $payment === 'free' || $percent !== null ? Money::zero() : $amount,
            ]);
            $this->total($order);
        });

        return $order->refresh();
    }

    /**
     * Closes a table: the order is paid into the shift. Each line then carries its share after the discount (the last
     * line takes what rounding leaves), so the voucher is what was really taken.
     *
     * @throws EntryException
     */
    public function pay(RevenueTicket $order, string $paidText = ''): RevenueTicket
    {
        $this->orderMustBeOpen($order);
        $this->total($order);
        $order->refresh();
        $lines = $order->lines()->get();
        $paid = trim($paidText) === '' ? null : $this->number($paidText);

        if ($lines->isEmpty()) {
            throw new EntryException([__('revenues.problem.pos_empty')]);
        }

        if (trim($paidText) !== '' && $paid === null) {
            throw new EntryException([__('revenues.problem.pos_paid')]);
        }

        if ($paid !== null && bccomp($paid, Money::normalize($order->net), 2) === -1) {
            throw new EntryException([__('revenues.problem.pos_paid_short')]);
        }

        return DB::transaction(function () use ($order, $lines, $paid): RevenueTicket {
            $shift = RevenueSheet::query()->lockForUpdate()->findOrFail($order->sheet_id);
            $this->shiftMustBeOpen($shift);
            $gross = Money::normalize($order->gross);
            $discount = Money::normalize($order->discount);
            $left = $discount;

            foreach ($lines->values() as $index => $line) {
                $lineGross = Money::normalize($line->amount);
                $share = $index === $lines->count() - 1 || bccomp($gross, '0', 2) === 0
                    ? $left
                    : Money::normalize(bcdiv(bcmul($discount, $lineGross, 6), $gross, 6));
                $share = bccomp($share, $lineGross, 2) === 1 ? $lineGross : $share;
                $left = bcsub($left, $share, 2);
                $line->update(['amount' => bcsub($lineGross, $share, 2)]);
            }

            $order->update(['status' => RevenueTicket::PAID, 'paid' => $paid, 'closed_at' => now()]);
            $shift->update(['total' => bcadd(Money::normalize($shift->total), Money::normalize($order->net), 2)]);

            return $order->refresh();
        });
    }

    /**
     * Cancels an order: an open one, or a paid one while its shift is still open (it leaves the shift's total).
     *
     * @throws EntryException
     */
    public function cancel(RevenueTicket $order): void
    {
        DB::transaction(function () use ($order): void {
            $shift = RevenueSheet::query()->lockForUpdate()->findOrFail($order->sheet_id);
            $this->shiftMustBeOpen($shift);

            if (! $order->isOpen()) {
                $shift->update(['total' => bcsub(Money::normalize($shift->total), Money::normalize($order->net), 2)]);
            }

            RevenueLine::query()->where('ticket_id', $order->id)->delete();
            $order->delete();
        });
    }

    /**
     * Ends a shift: its paid orders become one receipt voucher on 4002 by kind. A shift with no sale is dropped.
     *
     * @param  array<string, string>  $form  rate, center, notes
     *
     * @throws EntryException
     */
    public function closeShift(User $by, RevenueSheet $shift, array $form = []): ?RevenueSheet
    {
        $this->shiftMustBeOpen($shift);

        if (RevenueTicket::query()->where('sheet_id', $shift->id)->where('status', RevenueTicket::OPEN)->exists()) {
            throw new EntryException([__('revenues.problem.pos_tables_open')]);
        }

        $this->revenues->ensureAccount();

        return DB::transaction(function () use ($by, $shift, $form): ?RevenueSheet {
            $locked = RevenueSheet::query()->lockForUpdate()->findOrFail($shift->id);
            $this->shiftMustBeOpen($locked);

            $byKind = RevenueLine::query()->where('sheet_id', $locked->id)->groupBy('kind')->orderBy('kind')
                ->selectRaw('kind, sum(amount) as total')->pluck('total', 'kind')
                ->map(fn ($total): string => Money::normalize($total))
                ->filter(fn (string $total): bool => bccomp($total, '0', 2) === 1)->all();

            if ($byKind === []) {
                RevenueLine::query()->where('sheet_id', $locked->id)->delete();
                RevenueTicket::query()->where('sheet_id', $locked->id)->delete();
                $locked->delete();

                return null;
            }

            $project = GlProject::query()->findOrFail($locked->project_code);
            $voucher = $this->revenues->receipt($by, $project, $locked->cash_account, $locked->currency, CarbonImmutable::instance($locked->day), $byKind, (string) ($form['rate'] ?? ''), (string) ($form['center'] ?? ''));
            $notes = trim((string) ($form['notes'] ?? ''));

            $locked->update([
                'status' => RevenueSheet::POSTED, 'voucher_id' => $voucher->id,
                'total' => array_reduce(array_values($byKind), fn (string $sum, string $amount): string => bcadd($sum, $amount, 2), Money::zero()),
                'notes' => $notes !== '' ? mb_substr($notes, 0, 255) : $locked->notes,
            ]);

            return $locked->refresh();
        });
    }

    /**
     * The shift's report, for the end of the day: its orders, what was sold of each item, the takings by waiter and
     * by service, and the discounts.
     *
     * @return array{orders: int, open: int, gross: string, discount: string, net: string, items: Collection<int, array{name: string, quantity: string, amount: string}>, waiters: Collection<string, string>, services: Collection<string, string>}
     */
    public function report(RevenueSheet $shift): array
    {
        $paid = RevenueTicket::query()->where('sheet_id', $shift->id)->where('status', RevenueTicket::PAID)->get();
        $sum = fn (Collection $rows, string $field): string => $rows->reduce(fn (string $total, RevenueTicket $order): string => bcadd($total, Money::normalize($order->{$field}), 2), Money::zero());

        return [
            'orders' => $paid->count(),
            'open' => RevenueTicket::query()->where('sheet_id', $shift->id)->where('status', RevenueTicket::OPEN)->count(),
            'gross' => $sum($paid, 'gross'),
            'discount' => $sum($paid, 'discount'),
            'net' => $sum($paid, 'net'),
            'items' => RevenueLine::query()->whereIn('ticket_id', $paid->pluck('id'))->groupBy('description')->orderBy('description')
                ->selectRaw('description, sum(quantity) as quantity, sum(amount) as amount')->get()
                ->map(fn ($row): array => ['name' => (string) $row->description, 'quantity' => Money::normalize($row->quantity), 'amount' => Money::normalize($row->amount)]),
            'waiters' => $paid->groupBy(fn (RevenueTicket $order): string => (string) ($order->waiter ?? ''))->map(fn (Collection $rows): string => $sum($rows, 'net')),
            'services' => $paid->groupBy('service')->map(fn (Collection $rows): string => $sum($rows, 'net')),
        ];
    }

    /** Puts the order's totals right after its lines or discount changed: what the lines come to, less the discount. */
    private function total(RevenueTicket $order): void
    {
        $order->refresh();
        $gross = Money::normalize(RevenueLine::query()->where('ticket_id', $order->id)->sum('amount'));
        $discount = $order->discount_percent !== null
            ? Money::normalize(bcdiv(bcmul($gross, Money::normalize($order->discount_percent), 6), '100', 6))
            : Money::normalize($order->discount);
        $discount = bccomp($discount, $gross, 2) === 1 ? $gross : $discount;

        $order->update(['gross' => $gross, 'discount' => $discount, 'net' => bcsub($gross, $discount, 2)]);
    }

    private function changeQuantity(RevenueLine $line, string $quantity): void
    {
        $line->update(['quantity' => $quantity, 'amount' => Money::normalize(bcmul($quantity, Money::normalize($line->unit_price), 4))]);
    }

    /** @throws EntryException */
    private function shiftMustBeOpen(RevenueSheet $shift): void
    {
        if (! $shift->isOpen()) {
            throw new EntryException([__('revenues.problem.shift_closed')]);
        }
    }

    /** @throws EntryException */
    private function orderMustBeOpen(RevenueTicket $order): void
    {
        if (! $order->isOpen()) {
            throw new EntryException([__('revenues.problem.pos_order_closed')]);
        }
    }

    private function persons(string $text): ?int
    {
        return ctype_digit(trim($text)) && (int) $text > 0 ? min((int) $text, 999) : null;
    }

    private function text(mixed $value, int $length = 255): ?string
    {
        $text = mb_substr(trim((string) $value), 0, $length);

        return $text !== '' ? $text : null;
    }

    /** @return numeric-string|null */
    private function number(string $text): ?string
    {
        $clean = str_replace([',', ' '], '', trim($text));

        return preg_match('/^\d+(\.\d+)?$/', $clean) === 1 ? Money::normalize($clean) : null;
    }
}
