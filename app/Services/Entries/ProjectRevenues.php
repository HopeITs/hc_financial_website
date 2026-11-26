<?php

namespace App\Services\Entries;

use App\Models\GlAccount;
use App\Models\GlProject;
use App\Models\RevenueItem;
use App\Models\RevenueLine;
use App\Models\RevenueSheet;
use App\Models\User;
use App\Models\Voucher;
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
            $values['code'] = mb_substr(trim((string) ($form['code'] ?? '')), 0, 32) ?: null;
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

    /** Whether IT has run the point-of-sale migrations (2026_12_01_090000 and 2026_12_02_090000); the sales themselves are in PointOfSale. */
    public static function pointOfSaleReady(): bool
    {
        return once(static fn (): bool => Schema::hasTable('gl_revenue_tickets') && Schema::hasColumn('gl_revenue_items', 'category')
            && Schema::hasColumn('gl_revenue_tickets', 'status') && Schema::hasColumn('gl_revenue_items', 'code'));
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
    public function ensureAccount(): void
    {
        if (! GlAccount::withTrashed()->whereKey(self::ACCOUNT)->exists() && GlAccount::query()->whereKey('4000')->exists()) {
            GlAccount::query()->create(array_merge(self::account(), ['required_tags' => ['Project']]));
        }
    }

    /**
     * The receipt voucher of a day's takings or a closed point-of-sale shift: one line on 4002 for each kind of revenue.
     *
     * @param  array<string, string>  $byKind  kind => amount
     */
    public function receipt(User $by, GlProject $project, string $cashAccount, string $currency, CarbonImmutable $day, array $byKind, string $rate, string $center): Voucher
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

    /** @return numeric-string|null */
    private function number(string $text): ?string
    {
        $clean = str_replace([',', ' '], '', trim($text));

        return preg_match('/^\d+(\.\d+)?$/', $clean) === 1 ? Money::normalize($clean) : null;
    }
}
