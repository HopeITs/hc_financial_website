<?php

namespace App\Services\Entries;

use App\Models\GlAccount;
use App\Models\GlProject;
use App\Models\RevenueItem;
use App\Models\RevenueLine;
use App\Models\RevenueSheet;
use App\Models\RevenueTicket;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Projects\ProjectAccess;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Project revenues (finance request of 2026-11-24), in place of the separate sales program: each project's price list,
 * and a day's takings into a cash account, line by line (what was sold, how many, at what price). A day's takings are
 * one receipt voucher crediting 4002 "Income from project activities" on the project, one line per kind of revenue,
 * which waits for approval like any other.
 */
class ProjectRevenues
{
    public const ACCOUNT = '4002';

    public function __construct(private readonly CashVouchers $cash) {}

    /** @return array<string, mixed> the income account, as a row of gl_accounts */
    public static function account(): array
    {
        return [
            'code' => self::ACCOUNT, 'parent_code' => '4000', 'level' => 'posting', 'class' => 'income',
            'name_en' => 'Income from project activities', 'name_ar' => 'إيرادات من أنشطة المشاريع',
            'statement_line' => 'Income from project activities', 'normal_side' => 'credit', 'currency' => 'Any', 'city' => null,
            'is_cash' => false, 'required_tags' => json_encode(['Project']),
            'usage_note' => 'What a project earns itself: sales, subscriptions, internet, paid courses. Credited by the project revenues screen.',
            'is_active' => true,
        ];
    }

    /**
     * Adds a product or service to a project's price list, or changes one.
     *
     * @param  array<string, string>  $form  project, name, kind, unit_price, currency, active
     *
     * @throws EntryException
     */
    public function saveItem(?RevenueItem $item, array $form): RevenueItem
    {
        $problems = [];
        $price = $this->number((string) ($form['unit_price'] ?? ''));

        if (! GlProject::query()->whereKey((string) ($form['project'] ?? ''))->exists()) {
            $problems[] = __('revenues.problem.project');
        }

        if (trim((string) ($form['name'] ?? '')) === '') {
            $problems[] = __('revenues.problem.name');
        }

        if (! in_array($form['kind'] ?? '', RevenueItem::KINDS, true)) {
            $problems[] = __('revenues.problem.kind');
        }

        if ($price === null) {
            $problems[] = __('revenues.problem.price');
        }

        if (! in_array($form['currency'] ?? '', ['USD', 'SYP'], true)) {
            $problems[] = __('revenues.problem.currency');
        }

        if ($problems !== []) {
            throw new EntryException($problems);
        }

        $values = [
            'project_code' => (string) $form['project'], 'name' => mb_substr(trim((string) $form['name']), 0, 255), 'kind' => (string) $form['kind'],
            'unit_price' => $price, 'currency' => (string) $form['currency'], 'active' => ($form['active'] ?? '1') !== '0',
        ];

        if (self::pointOfSaleReady()) {
            $values['category'] = mb_substr(trim((string) ($form['category'] ?? '')), 0, 64) ?: null;
        }

        if ($item === null) {
            return RevenueItem::query()->create($values);
        }

        $item->update($values);

        return $item->refresh();
    }

    /**
     * Records a day's takings of a project into a cash account, and makes its receipt voucher.
     *
     * @param  array<string, string>  $form  project, day, cash_account, rate, center, notes
     * @param  list<array<string, string>>  $rows  item, description, kind, quantity, unit_price
     *
     * @throws EntryException
     */
    public function record(User $by, array $form, array $rows): RevenueSheet
    {
        $this->ensureAccount();
        $problems = [];
        $project = GlProject::query()->find((string) ($form['project'] ?? ''));
        $account = $this->cash->cashAccounts()->get((string) ($form['cash_account'] ?? ''));
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($form['day'] ?? '')) === 1 ? CarbonImmutable::createFromFormat('!Y-m-d', (string) $form['day']) : false;
        $items = RevenueItem::query()->whereIn('id', array_filter(array_map(fn (array $row): int => (int) ($row['item'] ?? 0), $rows)))->get()->keyBy('id');

        if ($project === null) {
            $problems[] = __('revenues.problem.project');
        }

        if ($account === null) {
            $problems[] = __('revenues.problem.cash_account');
        }

        if (! $day instanceof CarbonImmutable) {
            $problems[] = __('revenues.problem.day');
        }

        $lines = [];
        $number = 0;

        foreach ($rows as $row) {
            $item = $items->get((int) ($row['item'] ?? 0));
            $description = trim((string) ($row['description'] ?? '')) ?: (string) $item?->name;
            $quantityText = trim((string) ($row['quantity'] ?? ''));
            $priceText = trim((string) ($row['unit_price'] ?? ''));

            // A line with nothing chosen and no price is an empty row of the form, whatever its quantity.
            if ($description === '' && $priceText === '') {
                continue;
            }

            $number++;
            $quantity = $this->number($quantityText);
            $price = $priceText === '' && $item !== null ? Money::normalize($item->unit_price) : $this->number($priceText);
            $kind = $item?->kind ?? (string) ($row['kind'] ?? 'other');

            if ($description === '') {
                $problems[] = __('revenues.problem.line_description', ['line' => $number]);
            }

            if ($quantity === null || bccomp($quantity, '0', 2) !== 1) {
                $problems[] = __('revenues.problem.line_quantity', ['line' => $number]);
            }

            if ($price === null || bccomp($price, '0', 2) !== 1) {
                $problems[] = __('revenues.problem.line_price', ['line' => $number]);
            }

            if ($item !== null && $account !== null && $item->currency !== $account->currency) {
                $problems[] = __('revenues.problem.line_currency', ['line' => $number, 'currency' => $account->currency]);
            }

            if (! in_array($kind, RevenueItem::KINDS, true)) {
                $kind = 'other';
            }

            $lines[] = [
                'item_id' => $item?->id, 'kind' => $kind, 'description' => mb_substr($description, 0, 255), 'quantity' => $quantity ?? '0',
                'unit_price' => $price ?? '0', 'amount' => Money::normalize(bcmul($quantity ?? '0', $price ?? '0', 4)),
            ];
        }

        if ($lines === []) {
            $problems[] = __('revenues.problem.no_lines');
        }

        if ($problems !== [] || ! $day instanceof CarbonImmutable || $project === null || $account === null) {
            throw new EntryException($problems === [] ? [__('revenues.problem.no_lines')] : $problems);
        }

        $byKind = [];
        foreach ($lines as $line) {
            $byKind[$line['kind']] = bcadd($byKind[$line['kind']] ?? '0.00', $line['amount'], 2);
        }

        $total = array_reduce($lines, fn (string $sum, array $line): string => bcadd($sum, $line['amount'], 2), Money::zero());

        return DB::transaction(function () use ($by, $form, $project, $account, $day, $lines, $byKind, $total): RevenueSheet {
            $voucher = $this->receipt($by, $project, $account->code, $account->currency, $day, $byKind, (string) ($form['rate'] ?? ''), (string) ($form['center'] ?? ''));

            $sheet = RevenueSheet::query()->create([
                'project_code' => $project->code, 'day' => $day->toDateString(), 'cash_account' => $account->code, 'currency' => $account->currency,
                'total' => $total, 'status' => RevenueSheet::POSTED, 'voucher_id' => $voucher->id,
                'notes' => trim((string) ($form['notes'] ?? '')) ?: null, 'created_by' => (int) $by->getAuthIdentifier(),
            ]);
            $sheet->lines()->createMany($lines);

            return $sheet;
        });
    }

    /**
     * Opens a point-of-sale shift (finance request of 2026-11-26): the sales of one project into one cash account on a
     * day, made one by one and booked together when the shift closes. The person's own open shift for the same project,
     * account and day is carried on rather than a second one opened.
     *
     * @param  array<string, string>  $form  project, cash_account, day
     *
     * @throws EntryException
     */
    public function openShift(User $by, array $form): RevenueSheet
    {
        $project = GlProject::query()->where('is_general_account', false)->find((string) ($form['project'] ?? ''));
        $account = $this->cash->cashAccounts()->get((string) ($form['cash_account'] ?? ''));
        $day = $this->day((string) ($form['day'] ?? ''));
        $problems = [];

        if ($project === null || ! app(ProjectAccess::class)->canOpen($by, $project->code)) {
            $problems[] = __('revenues.problem.project');
        }

        if ($account === null) {
            $problems[] = __('revenues.problem.cash_account');
        }

        if ($day === null) {
            $problems[] = __('revenues.problem.day');
        }

        if ($problems !== [] || $project === null || $account === null || $day === null) {
            throw new EntryException($problems);
        }

        $values = ['project_code' => $project->code, 'cash_account' => $account->code, 'status' => RevenueSheet::OPEN, 'created_by' => (int) $by->getAuthIdentifier()];

        return RevenueSheet::query()->where($values)->whereDate('day', $day->toDateString())->first()
            ?? RevenueSheet::query()->create([...$values, 'day' => $day->toDateString(), 'currency' => $account->currency, 'total' => Money::zero()]);
    }

    /**
     * Records one sale of an open shift: the items in the basket, less a discount given as a percent or an amount.
     * Each line keeps its share of the sale after the discount, so the shift's voucher is what was really taken.
     *
     * @param  array<int|string, int|string>  $basket  item id => quantity
     * @param  array<string, string>  $form  discount_percent, discount, paid, note
     *
     * @throws EntryException
     */
    public function sell(User $by, RevenueSheet $shift, array $basket, array $form = []): RevenueTicket
    {
        $this->mustBeOpen($shift);
        $items = RevenueItem::query()->whereIn('id', array_map('intval', array_keys($basket)))->get()->keyBy('id');
        $problems = [];
        $lines = [];

        foreach ($basket as $id => $quantityText) {
            $item = $items->get((int) $id);
            $quantity = $this->number((string) $quantityText);

            if ($item === null || ! $item->active || $item->project_code !== $shift->project_code) {
                $problems[] = __('revenues.problem.pos_item');

                continue;
            }

            if ($item->currency !== $shift->currency) {
                $problems[] = __('revenues.problem.pos_currency', ['item' => $item->name, 'currency' => $shift->currency]);

                continue;
            }

            if ($quantity === null || bccomp($quantity, '0', 2) !== 1) {
                $problems[] = __('revenues.problem.pos_quantity', ['item' => $item->name]);

                continue;
            }

            $lines[] = ['item' => $item, 'quantity' => $quantity, 'gross' => Money::normalize(bcmul($quantity, Money::normalize($item->unit_price), 4))];
        }

        if ($lines === [] && $problems === []) {
            $problems[] = __('revenues.problem.pos_empty');
        }

        $gross = array_reduce($lines, fn (string $sum, array $line): string => bcadd($sum, $line['gross'], 2), Money::zero());
        $discount = $this->discount($gross, (string) ($form['discount_percent'] ?? ''), (string) ($form['discount'] ?? ''));
        $paidText = trim((string) ($form['paid'] ?? ''));
        $paid = $paidText === '' ? null : $this->number($paidText);

        if ($discount === null) {
            $problems[] = __('revenues.problem.pos_discount');
        }

        if ($paidText !== '' && $paid === null) {
            $problems[] = __('revenues.problem.pos_paid');
        }

        if ($problems !== [] || $discount === null) {
            throw new EntryException(array_values(array_unique($problems)));
        }

        $net = bcsub($gross, $discount, 2);

        if ($paid !== null && bccomp($paid, $net, 2) === -1) {
            throw new EntryException([__('revenues.problem.pos_paid_short')]);
        }

        return DB::transaction(function () use ($by, $shift, $lines, $gross, $discount, $net, $paid, $form): RevenueTicket {
            $locked = RevenueSheet::query()->lockForUpdate()->findOrFail($shift->id);
            $this->mustBeOpen($locked);

            $ticket = RevenueTicket::query()->create([
                'sheet_id' => $locked->id, 'number' => (int) RevenueTicket::query()->where('sheet_id', $locked->id)->max('number') + 1,
                'gross' => $gross, 'discount' => $discount, 'net' => $net, 'paid' => $paid,
                'note' => mb_substr(trim((string) ($form['note'] ?? '')), 0, 255) ?: null, 'created_by' => (int) $by->getAuthIdentifier(),
            ]);

            $left = $discount;
            foreach ($lines as $index => $line) {
                // The discount is shared by each line's part of the sale; the last line takes what rounding leaves.
                $share = $index === array_key_last($lines) || bccomp($gross, '0', 2) === 0
                    ? $left
                    : Money::normalize(bcdiv(bcmul($discount, $line['gross'], 6), $gross, 6));
                $share = bccomp($share, $line['gross'], 2) === 1 ? $line['gross'] : $share;
                $left = bcsub($left, $share, 2);

                RevenueLine::query()->create([
                    'sheet_id' => $locked->id, 'ticket_id' => $ticket->id, 'item_id' => $line['item']->id, 'kind' => $line['item']->kind,
                    'description' => mb_substr($line['item']->name, 0, 255), 'quantity' => $line['quantity'],
                    'unit_price' => Money::normalize($line['item']->unit_price), 'amount' => bcsub($line['gross'], $share, 2),
                ]);
            }

            $locked->update(['total' => bcadd(Money::normalize($locked->total), $net, 2)]);

            return $ticket;
        });
    }

    /**
     * Takes a wrong sale out of its shift, while the shift is still open.
     *
     * @throws EntryException
     */
    public function voidTicket(RevenueTicket $ticket): void
    {
        DB::transaction(function () use ($ticket): void {
            $shift = RevenueSheet::query()->lockForUpdate()->findOrFail($ticket->sheet_id);
            $this->mustBeOpen($shift);

            RevenueLine::query()->where('ticket_id', $ticket->id)->delete();
            $ticket->delete();
            $shift->update(['total' => bcsub(Money::normalize($shift->total), Money::normalize($ticket->net), 2)]);
        });
    }

    /**
     * Closes a shift: its sales become one receipt voucher on 4002 by kind, waiting for approval like any other. A
     * shift with no sale is simply dropped.
     *
     * @param  array<string, string>  $form  rate, center, notes
     *
     * @throws EntryException
     */
    public function closeShift(User $by, RevenueSheet $shift, array $form = []): ?RevenueSheet
    {
        $this->mustBeOpen($shift);
        $this->ensureAccount();

        return DB::transaction(function () use ($by, $shift, $form): ?RevenueSheet {
            $locked = RevenueSheet::query()->lockForUpdate()->findOrFail($shift->id);
            $this->mustBeOpen($locked);

            $byKind = RevenueLine::query()->where('sheet_id', $locked->id)->groupBy('kind')->orderBy('kind')
                ->selectRaw('kind, sum(amount) as total')->pluck('total', 'kind')
                ->map(fn ($total): string => Money::normalize($total))
                ->filter(fn (string $total): bool => bccomp($total, '0', 2) === 1)->all();

            if ($byKind === []) {
                $locked->delete();

                return null;
            }

            $project = GlProject::query()->findOrFail($locked->project_code);
            $voucher = $this->receipt($by, $project, $locked->cash_account, $locked->currency, CarbonImmutable::instance($locked->day), $byKind, (string) ($form['rate'] ?? ''), (string) ($form['center'] ?? ''));
            $notes = trim((string) ($form['notes'] ?? ''));

            $locked->update([
                'status' => RevenueSheet::POSTED, 'voucher_id' => $voucher->id,
                'total' => array_reduce(array_values($byKind), fn (string $sum, string $amount): string => bcadd($sum, $amount, 2), Money::zero()),
                'notes' => $notes !== '' ? mb_substr($notes, 0, 255) : $locked->notes,
            ]);

            return $locked->refresh();
        });
    }

    /** Whether IT has run the point-of-sale migration (2026_12_01_090000). */
    public static function pointOfSaleReady(): bool
    {
        return once(static fn (): bool => Schema::hasTable('gl_revenue_tickets') && Schema::hasColumn('gl_revenue_items', 'category'));
    }

    /**
     * The month's takings, by project and kind, in each currency.
     *
     * @return list<array{project: string, kind: string, currency: string, total: string}>
     */
    public function summary(CarbonImmutable $month): array
    {
        return RevenueLine::query()
            ->join('gl_revenue_sheets as s', 's.id', '=', 'gl_revenue_lines.sheet_id')
            ->where('s.status', RevenueSheet::POSTED)
            ->whereDate('s.day', '>=', $month->startOfMonth()->toDateString())
            ->whereDate('s.day', '<=', $month->endOfMonth()->toDateString())
            ->groupBy('s.project_code', 'gl_revenue_lines.kind', 's.currency')
            ->orderBy('s.project_code')->orderBy('gl_revenue_lines.kind')
            ->selectRaw('s.project_code as project, gl_revenue_lines.kind as kind, s.currency as currency, sum(gl_revenue_lines.amount) as total')
            ->get()
            ->map(fn ($row): array => ['project' => (string) $row->project, 'kind' => (string) $row->kind, 'currency' => (string) $row->currency, 'total' => Money::normalize($row->total)])
            ->all();
    }

    /** A rejected or deleted receipt voucher takes its takings out of the totals. */
    public function forgetVoucher(Voucher $voucher): void
    {
        RevenueSheet::query()->where('voucher_id', $voucher->id)->update(['status' => RevenueSheet::REJECTED]);
    }

    /** Adds the income account when the chart does not have it yet. */
    private function ensureAccount(): void
    {
        if (! GlAccount::withTrashed()->whereKey(self::ACCOUNT)->exists() && GlAccount::query()->whereKey('4000')->exists()) {
            GlAccount::query()->create(array_merge(self::account(), ['required_tags' => ['Project']]));
        }
    }

    /**
     * The receipt voucher of a day's takings or a closed shift: one line on 4002 for each kind of revenue.
     *
     * @param  array<string, string>  $byKind  kind => amount
     */
    private function receipt(User $by, GlProject $project, string $cashAccount, string $currency, CarbonImmutable $day, array $byKind, string $rate, string $center): Voucher
    {
        $rate = trim($rate) !== '' ? $rate : (string) ($this->cash->defaultRate($currency, $day)['rate'] ?? '');

        return $this->cash->save(CashVouchers::RECEIPT, [
            'date' => $day->toDateString(), 'account' => $cashAccount, 'rate' => $rate,
            'party' => __('revenues.party', ['project' => $project->name]), 'reference' => '',
            'center' => $center, 'request' => '',
            'description' => __('revenues.voucher_description', ['project' => $project->code, 'day' => $day->format('d/m/Y')]),
        ], array_map(fn (string $kind, string $amount): array => [
            'project' => $project->code, 'budget_line' => '', 'account' => self::ACCOUNT,
            'description' => __('revenues.kinds.'.$kind), 'amount' => $amount,
        ], array_keys($byKind), array_values($byKind)), [], $by);
    }

    /**
     * The discount of a sale: an amount when one is given, else a percent of the sale; never more than the sale.
     *
     * @return numeric-string|null null when it cannot be read or is more than the sale
     */
    private function discount(string $gross, string $percentText, string $amountText): ?string
    {
        $amount = trim($amountText) === '' ? Money::zero() : $this->number($amountText);
        $percent = trim($percentText) === '' ? Money::zero() : $this->number($percentText);

        if ($amount === null || $percent === null || bccomp($percent, '100', 2) === 1) {
            return null;
        }

        $discount = bccomp($amount, '0', 2) === 1 ? $amount : Money::normalize(bcdiv(bcmul($gross, $percent, 6), '100', 6));

        return bccomp($discount, $gross, 2) === 1 ? null : $discount;
    }

    /** @throws EntryException */
    private function mustBeOpen(RevenueSheet $shift): void
    {
        if (! $shift->isOpen()) {
            throw new EntryException([__('revenues.problem.shift_closed')]);
        }
    }

    private function day(string $text): ?CarbonImmutable
    {
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) === 1 ? CarbonImmutable::createFromFormat('!Y-m-d', $text) : false;

        return $day instanceof CarbonImmutable ? $day : null;
    }

    /** @return numeric-string|null */
    private function number(string $text): ?string
    {
        $clean = str_replace([',', ' '], '', trim($text));

        return preg_match('/^\d+(\.\d+)?$/', $clean) === 1 ? Money::normalize($clean) : null;
    }
}
