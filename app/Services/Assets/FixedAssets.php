<?php

namespace App\Services\Assets;

use App\Enums\VoucherType;
use App\Models\AssetCategory;
use App\Models\AssetCount;
use App\Models\AssetCountLine;
use App\Models\AssetMovement;
use App\Models\FixedAsset;
use App\Models\GlAccount;
use App\Models\GlBudgetLine;
use App\Models\GlProject;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\User;
use App\Models\Voucher;
use App\Models\Warehouse;
use App\Services\Entries\CashVouchers;
use App\Services\Entries\EntryException;
use App\Services\Ledger\LedgerRuleException;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\PostVoucher;
use App\Services\Ledger\VoucherDraft;
use App\Services\Ledger\Violation;
use App\Services\Purchasing\PurchaseFiles;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The warehouses and the fixed-asset register (finance request of 2026-11-28).
 *
 * - An asset comes in from a purchase file (its received goods, one asset per unit), as a gift in kind, or as one
 *   already owned when the register started. A purchase booked on an expense account is moved to the asset's
 *   fixed-asset account by a journal; a gift is booked as income (4001); an asset already owned needs no voucher.
 * - It is handed over to a person or a place, returned, moved between warehouses, sent for repair, and counted.
 * - Every month its cost (less what it will be worth at the end) is spread over its useful life: one journal for all
 *   assets, debit 5703 and credit 1490 on each project. Its depreciation so far is the sum of the months whose voucher
 *   still stands.
 * - It ends written off (lost, broken, stolen: debit 1490 and the loss 5704, credit its account) or sold (the cash,
 *   1490, its account and the gain 4003 or the loss 5704). A rejected or deleted voucher puts it back.
 *
 * Every amount of the register is in dollars; the vouchers are journals in dollars, waiting for approval like any other.
 */
class FixedAssets
{
    public const DEPRECIATION = '5703';

    public const ACCUMULATED = '1490';

    public const LOSS = '5704';

    public const GAIN = '4003';

    public const DONATIONS = '4001';

    public function __construct(
        private readonly PostVoucher $posting,
        private readonly CashVouchers $cash,
    ) {}

    /** Whether IT has run the register's migration (2026_12_03_090000). */
    public static function ready(): bool
    {
        return once(static fn (): bool => Schema::hasTable('gl_assets') && Schema::hasTable('gl_asset_counts'));
    }

    /**
     * Adds a warehouse, or changes one.
     *
     * @param  array<string, string>  $form  name, city, center, keeper, active
     *
     * @throws EntryException
     */
    public function saveWarehouse(?Warehouse $warehouse, array $form): Warehouse
    {
        if (trim((string) ($form['name'] ?? '')) === '') {
            throw new EntryException([__('assets.problem.warehouse_name')]);
        }

        $values = [
            'name' => $this->text($form['name']), 'city' => $this->text($form['city'] ?? null), 'center' => $this->text($form['center'] ?? null),
            'keeper' => $this->text($form['keeper'] ?? null), 'active' => ($form['active'] ?? '1') !== '0',
        ];

        if ($warehouse === null) {
            return Warehouse::query()->create($values);
        }

        $warehouse->update($values);

        return $warehouse->refresh();
    }

    /**
     * Adds a kind of asset, or changes one: its fixed-asset account (14xx) and useful life in months.
     *
     * @param  array<string, string>  $form  name_en, name_ar, account_code, life_months, active
     *
     * @throws EntryException
     */
    public function saveCategory(?AssetCategory $category, array $form): AssetCategory
    {
        $problems = [];
        $account = GlAccount::query()->where('code', (string) ($form['account_code'] ?? ''))->where('level', 'posting')->first();
        $life = ctype_digit(trim((string) ($form['life_months'] ?? ''))) ? (int) $form['life_months'] : 0;

        if (trim((string) ($form['name_ar'] ?? '')) === '' && trim((string) ($form['name_en'] ?? '')) === '') {
            $problems[] = __('assets.problem.category_name');
        }

        if ($account === null || ! str_starts_with($account->code, '14') || $account->code === self::ACCUMULATED) {
            $problems[] = __('assets.problem.category_account');
        }

        if ($life < 1 || $life > 600) {
            $problems[] = __('assets.problem.life');
        }

        if ($problems !== []) {
            throw new EntryException($problems);
        }

        $values = [
            'name_en' => $this->text($form['name_en'] ?? null) ?? (string) $this->text($form['name_ar'] ?? null),
            'name_ar' => $this->text($form['name_ar'] ?? null) ?? (string) $this->text($form['name_en'] ?? null),
            'account_code' => (string) $form['account_code'], 'life_months' => $life, 'active' => ($form['active'] ?? '1') !== '0',
        ];

        if ($category === null) {
            return AssetCategory::query()->create($values);
        }

        $category->update($values);

        return $category->refresh();
    }

    /**
     * Registers an asset given in kind or already owned. A gift is booked at once: debit its fixed-asset account,
     * credit the project's donation income.
     *
     * @param  array<string, string>  $form  source (donation, opening), name, category, project, center, warehouse, custodian, serial, description, acquired_on, cost, currency, rate, salvage, life_months, notes
     *
     * @throws EntryException
     */
    public function register(User $by, array $form): FixedAsset
    {
        $source = in_array($form['source'] ?? '', ['donation', 'opening'], true) ? (string) $form['source'] : 'opening';
        $values = $this->assetValues($form);

        return DB::transaction(function () use ($by, $values, $source, $form): FixedAsset {
            $asset = FixedAsset::query()->create([...$values, 'tag' => $this->nextTag(), 'source' => $source, 'created_by' => (int) $by->getAuthIdentifier()]);
            $this->movement($by, $asset, 'receive', $asset->acquired_on->toDateString(), ['to_warehouse_id' => $asset->warehouse_id, 'to_custodian' => $asset->custodian, 'condition' => $asset->condition, 'notes' => $this->text($form['notes'] ?? null)]);

            if ($source === 'donation') {
                $voucher = $this->journal($by, CarbonImmutable::instance($asset->acquired_on), __('assets.voucher.donation', ['tag' => $asset->tag, 'name' => $asset->name]), [
                    $this->usdLine($asset->account_code, Money::normalize($asset->cost_usd), true, $asset->project_code, $asset->center),
                    $this->usdLine(self::DONATIONS, Money::normalize($asset->cost_usd), false, $asset->project_code),
                ]);
                $asset->update(['voucher_id' => $voucher->id]);
            }

            return $asset->refresh();
        });
    }

    /**
     * The goods of received purchase files not yet in the register, each with how many units are left to register.
     *
     * @return list<array{item: PurchaseItem, purchase: Purchase, left: int, unit_price: string}>
     */
    public function purchasesToRegister(?array $projects = null): array
    {
        if (! Schema::hasTable('gl_purchases')) {
            return [];
        }

        $files = app(PurchaseFiles::class);
        $rows = [];
        $purchases = Purchase::query()->with('items')->where('kind', Purchase::GOODS)->whereIn('stage', [Purchase::RECEIVED, Purchase::PAID])
            ->when($projects !== null, fn ($query) => $query->whereIn('project_code', $projects ?? []))->orderByDesc('id')->limit(200)->get();

        foreach ($purchases as $purchase) {
            foreach ($files->orderLines($purchase) as $line) {
                $item = $line['item'];
                $received = (int) floor((float) ($item->received_quantity ?? $item->quantity));
                $left = $received - FixedAsset::query()->where('purchase_item_id', $item->id)->count();

                if ($left > 0) {
                    $rows[] = ['item' => $item, 'purchase' => $purchase, 'left' => $left, 'unit_price' => $line['price']];
                }
            }
        }

        return $rows;
    }

    /**
     * Registers the units of a purchased item, one asset each. When the purchase was booked on an expense account, one
     * journal moves their cost to the asset account (debit 14xx, credit the budget line's account).
     *
     * @param  array<string, string>  $form  count, name, category, center, warehouse, custodian, salvage, life_months, serials (one per line)
     *
     * @return Collection<int, FixedAsset>
     *
     * @throws EntryException
     */
    public function registerFromPurchase(User $by, PurchaseItem $item, array $form): Collection
    {
        $purchase = Purchase::query()->findOrFail($item->purchase_id);
        $row = collect($this->purchasesToRegister())->first(fn (array $candidate): bool => $candidate['item']->id === $item->id);
        $count = ctype_digit(trim((string) ($form['count'] ?? '1'))) ? (int) $form['count'] : 0;

        if ($row === null || $count < 1 || $count > $row['left']) {
            throw new EntryException([__('assets.problem.purchase_count', ['left' => $row['left'] ?? 0])]);
        }

        $unitUsd = app(PurchaseFiles::class)->usd($purchase, $row['unit_price']);
        $rate = bccomp($unitUsd, '0', 2) === 1 ? bcdiv($row['unit_price'], $unitUsd, 6) : '1';
        $values = $this->assetValues([
            ...$form, 'project' => $purchase->project_code, 'acquired_on' => ($purchase->received_on ?? now())->toDateString(),
            'cost' => $row['unit_price'], 'currency' => $purchase->currency, 'rate' => $rate,
            'name' => trim((string) ($form['name'] ?? '')) !== '' ? (string) $form['name'] : $item->description,
        ]);
        $serials = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($form['serials'] ?? '')) ?: [])));
        $budgetAccount = (string) GlBudgetLine::query()->whereKey($item->budget_line_key)->value('account_code');

        return DB::transaction(function () use ($by, $item, $purchase, $count, $values, $serials, $budgetAccount, $unitUsd): Collection {
            $assets = collect();

            for ($index = 0; $index < $count; $index++) {
                $asset = FixedAsset::query()->create([
                    ...$values, 'tag' => $this->nextTag(), 'serial' => $serials[$index] ?? null, 'source' => 'purchase',
                    'purchase_id' => $purchase->id, 'purchase_item_id' => $item->id, 'created_by' => (int) $by->getAuthIdentifier(),
                ]);
                $this->movement($by, $asset, 'receive', $asset->acquired_on->toDateString(), ['to_warehouse_id' => $asset->warehouse_id, 'to_custodian' => $asset->custodian, 'condition' => $asset->condition, 'reference' => $purchase->number('PO')]);
                $assets->push($asset);
            }

            // Bought on an expense account: move the cost to the fixed assets.
            if ($budgetAccount !== '' && ! str_starts_with($budgetAccount, '14')) {
                $total = Money::normalize(bcmul($unitUsd, (string) $count, 4));
                $first = $assets->first();
                $voucher = $this->journal($by, CarbonImmutable::instance($first->acquired_on), __('assets.voucher.capitalise', ['order' => $purchase->number('PO'), 'name' => $first->name]), [
                    $this->usdLine($first->account_code, $total, true, $first->project_code, $first->center),
                    new LineDraft(accountCode: $budgetAccount, currency: 'USD', credit: $total, projectCode: $first->project_code, budgetLineKey: $item->budget_line_key, center: $first->center),
                ]);
                FixedAsset::query()->whereIn('id', $assets->pluck('id'))->update(['voucher_id' => $voucher->id]);
            }

            return $assets->map(fn (FixedAsset $asset): FixedAsset => $asset->refresh());
        });
    }

    /**
     * Changes an asset's details that make no voucher: its name, serial, description, center, salvage and life.
     *
     * @param  array<string, string>  $form
     *
     * @throws EntryException
     */
    public function update(FixedAsset $asset, array $form): FixedAsset
    {
        $life = ctype_digit(trim((string) ($form['life_months'] ?? ''))) ? (int) $form['life_months'] : 0;
        $salvage = $this->number((string) ($form['salvage'] ?? '0')) ?? null;

        if (trim((string) ($form['name'] ?? '')) === '' || $life < 1 || $salvage === null || bccomp($salvage, Money::normalize($asset->cost_usd), 2) === 1) {
            throw new EntryException([__('assets.problem.details')]);
        }

        $asset->update([
            'name' => $this->text($form['name']), 'serial' => $this->text($form['serial'] ?? null), 'description' => $this->text($form['description'] ?? null),
            'center' => $this->text($form['center'] ?? null) ?? $asset->center, 'salvage_usd' => $salvage, 'life_months' => $life,
            'condition' => in_array($form['condition'] ?? '', FixedAsset::CONDITIONS, true) ? (string) $form['condition'] : $asset->condition,
            'notes' => $this->text($form['notes'] ?? null, 2000),
        ]);

        return $asset->refresh();
    }

    /**
     * Moves an asset: hands it over to a person or place, takes it back into a warehouse, moves it to another
     * warehouse, or sends it for repair.
     *
     * @param  array<string, string>  $form  date, warehouse, custodian, condition, reference, notes
     *
     * @throws EntryException
     */
    public function move(User $by, FixedAsset $asset, string $kind, array $form): FixedAsset
    {
        if (! $asset->isOwned() || ! in_array($kind, ['handover', 'return', 'transfer', 'repair'], true)) {
            throw new EntryException([__('assets.problem.not_owned')]);
        }

        $date = $this->day((string) ($form['date'] ?? '')) ?? throw new EntryException([__('assets.problem.date')]);
        $warehouse = trim((string) ($form['warehouse'] ?? '')) === '' ? null : Warehouse::query()->find((int) $form['warehouse']);
        $custodian = $this->text($form['custodian'] ?? null);

        if ($kind === 'handover' && $custodian === null) {
            throw new EntryException([__('assets.problem.custodian')]);
        }

        if (in_array($kind, ['return', 'transfer'], true) && $warehouse === null) {
            throw new EntryException([__('assets.problem.warehouse')]);
        }

        return DB::transaction(function () use ($by, $asset, $kind, $form, $date, $warehouse, $custodian): FixedAsset {
            $condition = in_array($form['condition'] ?? '', FixedAsset::CONDITIONS, true) ? (string) $form['condition'] : $asset->condition;
            $this->movement($by, $asset, $kind, $date->toDateString(), [
                'from_warehouse_id' => $asset->warehouse_id, 'to_warehouse_id' => $warehouse?->id ?? ($kind === 'handover' ? null : $asset->warehouse_id),
                'from_custodian' => $asset->custodian, 'to_custodian' => $kind === 'handover' ? $custodian : null, 'condition' => $condition,
                'reference' => $this->text($form['reference'] ?? null), 'notes' => $this->text($form['notes'] ?? null, 2000),
            ]);

            $asset->update(match ($kind) {
                'handover' => ['custodian' => $custodian, 'warehouse_id' => $warehouse?->id, 'status' => FixedAsset::IN_USE, 'condition' => $condition],
                'return' => ['custodian' => null, 'warehouse_id' => $warehouse?->id, 'status' => FixedAsset::IN_STORE, 'condition' => $condition],
                'transfer' => ['warehouse_id' => $warehouse?->id, 'condition' => $condition],
                default => ['status' => FixedAsset::REPAIR, 'condition' => $condition],
            });

            return $asset->refresh();
        });
    }

    /** What the asset's depreciation comes to so far: its depreciation months whose voucher still stands. */
    public function accumulated(FixedAsset $asset): string
    {
        return Money::normalize(AssetMovement::query()->where('asset_id', $asset->id)->where('kind', 'depreciate')
            ->whereIn('voucher_id', Voucher::query()->select('id'))->sum('amount_usd'));
    }

    /** @return numeric-string what the asset is worth in the books: its cost less its depreciation so far */
    public function bookValue(FixedAsset $asset): string
    {
        return bcsub(Money::normalize($asset->cost_usd), $this->accumulated($asset), 2);
    }

    /**
     * Each owned asset's depreciation for a month, by straight line: its cost less its salvage, spread over its life,
     * never more than is left. An asset already depreciated for that month, or not yet started, has none.
     *
     * @return list<array{asset: FixedAsset, amount: string}>
     */
    public function depreciationFor(string $month): array
    {
        $booked = AssetMovement::query()->where('kind', 'depreciate')->where('month', $month)
            ->whereIn('voucher_id', Voucher::query()->select('id'))->pluck('asset_id')->flip();
        $rows = [];

        foreach (FixedAsset::query()->whereIn('status', FixedAsset::OWNED)->where('depreciation_from', '<=', $month)->orderBy('project_code')->orderBy('tag')->get() as $asset) {
            if ($booked->has($asset->id)) {
                continue;
            }

            $base = bcsub(Money::normalize($asset->cost_usd), Money::normalize($asset->salvage_usd), 2);
            $left = bcsub($base, $this->accumulated($asset), 2);
            $monthly = Money::normalize(bcdiv($base, (string) max(1, $asset->life_months), 6));
            $amount = bccomp($monthly, $left, 2) === 1 ? $left : $monthly;

            if (bccomp($amount, '0', 2) === 1) {
                $rows[] = ['asset' => $asset, 'amount' => $amount];
            }
        }

        return $rows;
    }

    /**
     * Books a month's depreciation: one journal, debit 5703 and credit 1490 for each project, on the month's last day.
     *
     * @throws EntryException
     */
    public function depreciate(User $by, string $month): Voucher
    {
        if (preg_match('/^\d{4}-\d{2}$/', $month) !== 1) {
            throw new EntryException([__('assets.problem.month')]);
        }

        $rows = $this->depreciationFor($month);

        if ($rows === []) {
            throw new EntryException([__('assets.problem.nothing_to_depreciate')]);
        }

        $byProject = [];
        foreach ($rows as $row) {
            $byProject[$row['asset']->project_code] = bcadd($byProject[$row['asset']->project_code] ?? '0.00', $row['amount'], 2);
        }

        $lines = [];
        foreach ($byProject as $project => $amount) {
            $lines[] = $this->usdLine(self::DEPRECIATION, $amount, true, (string) $project);
            $lines[] = $this->usdLine(self::ACCUMULATED, $amount, false, (string) $project);
        }

        $date = CarbonImmutable::createFromFormat('!Y-m', $month)->endOfMonth()->startOfDay();

        return DB::transaction(function () use ($by, $rows, $lines, $date, $month): Voucher {
            $this->ensureAccounts();
            $voucher = $this->journal($by, $date, __('assets.voucher.depreciation', ['month' => $date->format('m/Y')]), $lines);

            foreach ($rows as $row) {
                $this->movement($by, $row['asset'], 'depreciate', $date->toDateString(), ['month' => $month, 'amount_usd' => $row['amount'], 'voucher_id' => $voucher->id]);
            }

            return $voucher;
        });
    }

    /**
     * Writes an asset off (lost, stolen, broken beyond repair, destroyed): its depreciation so far and the loss of
     * what was left of it take its cost out of the books.
     *
     * @param  array<string, string>  $form  date, reason, reference
     *
     * @throws EntryException
     */
    public function writeOff(User $by, FixedAsset $asset, array $form): Voucher
    {
        $date = $this->endable($asset, $form);

        if (trim((string) ($form['reason'] ?? '')) === '') {
            throw new EntryException([__('assets.problem.reason')]);
        }

        $accumulated = $this->accumulated($asset);
        $book = bcsub(Money::normalize($asset->cost_usd), $accumulated, 2);
        $lines = array_values(array_filter([
            bccomp($accumulated, '0', 2) === 1 ? $this->usdLine(self::ACCUMULATED, $accumulated, true, $asset->project_code) : null,
            bccomp($book, '0', 2) === 1 ? $this->usdLine(self::LOSS, $book, true, $asset->project_code) : null,
            $this->usdLine($asset->account_code, Money::normalize($asset->cost_usd), false, $asset->project_code, $asset->center),
        ]));

        return $this->end($by, $asset, $date, 'write_off', FixedAsset::WRITTEN_OFF, $lines, __('assets.voucher.write_off', ['tag' => $asset->tag, 'name' => $asset->name, 'reason' => trim((string) $form['reason'])]), $form, $book);
    }

    /**
     * Sells an asset: the cash received, its depreciation so far and its cost leave the books, and the difference with
     * what was left of it is a gain (4003) or a loss (5704).
     *
     * @param  array<string, string>  $form  date, price, cash_account, rate, buyer, reference
     *
     * @throws EntryException
     */
    public function sell(User $by, FixedAsset $asset, array $form): Voucher
    {
        $date = $this->endable($asset, $form);
        $account = $this->cash->cashAccounts()->get((string) ($form['cash_account'] ?? ''));
        $price = $this->number((string) ($form['price'] ?? ''));

        if ($account === null || $price === null || bccomp($price, '0', 2) !== 1) {
            throw new EntryException([__('assets.problem.sale')]);
        }

        $rate = $account->currency === 'USD' ? '1' : ($this->number((string) ($form['rate'] ?? ''), 6) ?? (string) ($this->cash->defaultRate($account->currency, $date)['rate'] ?? ''));

        if ($rate === '' || bccomp($rate, '0', 6) !== 1) {
            throw new EntryException([__('assets.problem.rate')]);
        }

        $priceUsd = Money::normalize(bcdiv($price, $rate, 6));
        $accumulated = $this->accumulated($asset);
        $book = bcsub(Money::normalize($asset->cost_usd), $accumulated, 2);
        $difference = bcsub($priceUsd, $book, 2);
        $lines = array_values(array_filter([
            new LineDraft(accountCode: $account->code, currency: $account->currency, debit: $price, rate: $rate, debitUsd: $priceUsd),
            bccomp($accumulated, '0', 2) === 1 ? $this->usdLine(self::ACCUMULATED, $accumulated, true, $asset->project_code) : null,
            $this->usdLine($asset->account_code, Money::normalize($asset->cost_usd), false, $asset->project_code, $asset->center),
            bccomp($difference, '0', 2) === 1 ? $this->usdLine(self::GAIN, $difference, false, $asset->project_code) : null,
            bccomp($difference, '0', 2) === -1 ? $this->usdLine(self::LOSS, bcmul($difference, '-1', 2), true, $asset->project_code) : null,
        ]));
        $buyer = $this->text($form['buyer'] ?? null);

        return $this->end($by, $asset, $date, 'sell', FixedAsset::SOLD, $lines, __('assets.voucher.sale', ['tag' => $asset->tag, 'name' => $asset->name, 'buyer' => $buyer ?? '-']), [...$form, 'notes' => $buyer], $priceUsd);
    }

    /**
     * Opens a count of a warehouse's assets (or of every owned asset), each one to tick found or missing.
     *
     * @throws EntryException
     */
    public function openCount(User $by, ?Warehouse $warehouse, string $date, string $countedBy = ''): AssetCount
    {
        $day = $this->day($date) ?? throw new EntryException([__('assets.problem.date')]);

        return DB::transaction(function () use ($by, $warehouse, $day, $countedBy): AssetCount {
            $count = AssetCount::query()->create([
                'warehouse_id' => $warehouse?->id, 'counted_on' => $day->toDateString(), 'status' => AssetCount::OPEN,
                'counted_by_name' => $this->text($countedBy), 'created_by' => (int) $by->getAuthIdentifier(),
            ]);

            $assets = FixedAsset::query()->whereIn('status', FixedAsset::OWNED)->when($warehouse !== null, fn ($query) => $query->where('warehouse_id', $warehouse?->id))->orderBy('tag')->get();
            foreach ($assets as $asset) {
                AssetCountLine::query()->create(['count_id' => $count->id, 'asset_id' => $asset->id, 'condition' => $asset->condition]);
            }

            return $count;
        });
    }

    /** @throws EntryException */
    public function markCount(AssetCountLine $line, ?bool $found, string $condition = '', string $note = ''): void
    {
        $count = AssetCount::query()->findOrFail($line->count_id);

        if ($count->status !== AssetCount::OPEN) {
            throw new EntryException([__('assets.problem.count_closed')]);
        }

        $line->update([
            'found' => $found, 'condition' => in_array($condition, FixedAsset::CONDITIONS, true) ? $condition : $line->condition, 'note' => $this->text($note),
        ]);
    }

    /**
     * Closes a count: each asset checked gets a count movement and its condition; the missing ones stay listed for a
     * write-off. Every asset must be checked first.
     *
     * @throws EntryException
     */
    public function closeCount(User $by, AssetCount $count): AssetCount
    {
        if ($count->status !== AssetCount::OPEN) {
            throw new EntryException([__('assets.problem.count_closed')]);
        }

        if (AssetCountLine::query()->where('count_id', $count->id)->whereNull('found')->exists()) {
            throw new EntryException([__('assets.problem.count_unchecked')]);
        }

        return DB::transaction(function () use ($by, $count): AssetCount {
            foreach ($count->lines()->with('asset')->get() as $line) {
                $asset = $line->asset;
                if (! $asset instanceof FixedAsset) {
                    continue;
                }

                $this->movement($by, $asset, 'count', $count->counted_on->toDateString(), [
                    'to_warehouse_id' => $asset->warehouse_id, 'condition' => $line->condition,
                    'notes' => ($line->found ? __('assets.count.found') : __('assets.count.missing')).($line->note ? ' · '.$line->note : ''),
                    'reference' => __('assets.count.reference', ['number' => $count->id]),
                ]);

                if ($line->found && $line->condition !== null) {
                    $asset->update(['condition' => $line->condition]);
                }
            }

            $count->update(['status' => AssetCount::CLOSED]);

            return $count->refresh();
        });
    }

    /** A rejected or deleted voucher: a write-off or sale is undone, and the voucher of a registration is forgotten. */
    public function forgetVoucher(Voucher $voucher): void
    {
        foreach (AssetMovement::query()->where('voucher_id', $voucher->id)->whereIn('kind', ['write_off', 'sell'])->get() as $movement) {
            FixedAsset::query()->whereKey($movement->asset_id)->update(['status' => $movement->previous_status ?? FixedAsset::IN_STORE, 'ended_on' => null]);
            $movement->delete();
        }

        FixedAsset::query()->where('voucher_id', $voucher->id)->update(['voucher_id' => null]);
    }

    /** Adds the loss and gain accounts when the chart does not have them yet. */
    public function ensureAccounts(): void
    {
        $now = now();
        $accounts = [
            [self::LOSS, '5700', 'expenses', 'debit', 'Loss on fixed assets written off or sold', 'خسائر الأصول الثابتة المتلفة أو المباعة', 'What was left of a fixed asset written off, or sold for less. Posted by the fixed-asset register.'],
            [self::GAIN, '4000', 'income', 'credit', 'Gain on sale of fixed assets', 'أرباح بيع الأصول الثابتة', 'A fixed asset sold for more than what was left of it. Posted by the fixed-asset register.'],
        ];

        foreach ($accounts as [$code, $parent, $class, $side, $nameEn, $nameAr, $note]) {
            if (! GlAccount::withTrashed()->whereKey($code)->exists() && GlAccount::query()->whereKey($parent)->exists()) {
                GlAccount::query()->create([
                    'code' => $code, 'parent_code' => $parent, 'level' => 'posting', 'class' => $class, 'name_en' => $nameEn, 'name_ar' => $nameAr,
                    'statement_line' => $nameEn, 'normal_side' => $side, 'currency' => 'Any', 'city' => null, 'is_cash' => false,
                    'required_tags' => ['Project'], 'usage_note' => $note, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * Ends an asset (written off or sold) and books its voucher.
     *
     * @param  list<LineDraft>  $lines
     * @param  array<string, string|null>  $form
     */
    private function end(User $by, FixedAsset $asset, CarbonImmutable $date, string $kind, string $status, array $lines, string $description, array $form, string $amount): Voucher
    {
        return DB::transaction(function () use ($by, $asset, $date, $kind, $status, $lines, $description, $form, $amount): Voucher {
            $this->ensureAccounts();
            $voucher = $this->journal($by, $date, $description, $lines);
            $this->movement($by, $asset, $kind, $date->toDateString(), [
                'from_warehouse_id' => $asset->warehouse_id, 'from_custodian' => $asset->custodian, 'amount_usd' => $amount,
                'previous_status' => $asset->status, 'voucher_id' => $voucher->id,
                'reference' => $this->text($form['reference'] ?? null), 'notes' => $this->text($form['reason'] ?? $form['notes'] ?? null, 2000),
            ]);
            $asset->update(['status' => $status, 'ended_on' => $date->toDateString()]);

            return $voucher;
        });
    }

    /**
     * @param  array<string, string>  $form
     *
     * @throws EntryException
     */
    private function endable(FixedAsset $asset, array $form): CarbonImmutable
    {
        if (! $asset->isOwned()) {
            throw new EntryException([__('assets.problem.not_owned')]);
        }

        return $this->day((string) ($form['date'] ?? '')) ?? throw new EntryException([__('assets.problem.date')]);
    }

    /**
     * Reads and checks an asset's form.
     *
     * @param  array<string, string>  $form
     * @return array<string, mixed>
     *
     * @throws EntryException
     */
    private function assetValues(array $form): array
    {
        $problems = [];
        $category = AssetCategory::query()->find((int) ($form['category'] ?? 0));
        $project = GlProject::query()->find((string) ($form['project'] ?? ''));
        $warehouse = trim((string) ($form['warehouse'] ?? '')) === '' ? null : Warehouse::query()->find((int) $form['warehouse']);
        $acquired = $this->day((string) ($form['acquired_on'] ?? ''));
        $cost = $this->number((string) ($form['cost'] ?? ''));
        $currency = in_array($form['currency'] ?? 'USD', ['USD', 'SYP', 'EUR'], true) ? (string) ($form['currency'] ?? 'USD') : 'USD';
        $rate = $currency === 'USD' ? '1' : ($this->number((string) ($form['rate'] ?? ''), 6)
            ?? ($acquired !== null ? (string) ($this->cash->defaultRate($currency, $acquired)['rate'] ?? '') : ''));
        $salvage = trim((string) ($form['salvage'] ?? '')) === '' ? Money::zero() : $this->number((string) $form['salvage']);
        $life = trim((string) ($form['life_months'] ?? '')) === '' ? $category?->life_months : (ctype_digit(trim((string) $form['life_months'])) ? (int) $form['life_months'] : 0);
        $center = $this->text($form['center'] ?? null) ?? $warehouse?->center;

        if (trim((string) ($form['name'] ?? '')) === '') {
            $problems[] = __('assets.problem.name');
        }

        if ($category === null) {
            $problems[] = __('assets.problem.category');
        }

        if ($project === null) {
            $problems[] = __('assets.problem.project');
        }

        if ($center === null) {
            $problems[] = __('assets.problem.center');
        }

        if (trim((string) ($form['warehouse'] ?? '')) !== '' && $warehouse === null) {
            $problems[] = __('assets.problem.warehouse');
        }

        if ($acquired === null) {
            $problems[] = __('assets.problem.date');
        }

        if ($cost === null || bccomp($cost, '0', 2) !== 1) {
            $problems[] = __('assets.problem.cost');
        }

        if ($rate === '' || $rate === null || bccomp($rate, '0', 6) !== 1) {
            $problems[] = __('assets.problem.rate');
        }

        if ($life === null || $life < 1) {
            $problems[] = __('assets.problem.life');
        }

        $costUsd = $cost !== null && $rate !== '' && $rate !== null && bccomp($rate, '0', 6) === 1 ? Money::normalize(bcdiv($cost, $rate, 6)) : null;

        if ($salvage === null || ($costUsd !== null && bccomp($salvage, $costUsd, 2) === 1)) {
            $problems[] = __('assets.problem.salvage');
        }

        if ($problems !== [] || $category === null || $project === null || $acquired === null || $costUsd === null) {
            throw new EntryException($problems);
        }

        $custodian = $this->text($form['custodian'] ?? null);

        return [
            'name' => $this->text($form['name']), 'category_id' => $category->id, 'account_code' => $category->account_code,
            'project_code' => $project->code, 'center' => $center, 'warehouse_id' => $warehouse?->id, 'custodian' => $custodian,
            'serial' => $this->text($form['serial'] ?? null), 'description' => $this->text($form['description'] ?? null),
            'acquired_on' => $acquired->toDateString(), 'cost' => $cost, 'currency' => $currency, 'rate' => $rate, 'cost_usd' => $costUsd,
            'salvage_usd' => $salvage, 'life_months' => $life,
            // Depreciated from the month after it came in.
            'depreciation_from' => $acquired->startOfMonth()->addMonth()->format('Y-m'),
            'status' => $custodian !== null ? FixedAsset::IN_USE : FixedAsset::IN_STORE,
            'condition' => in_array($form['condition'] ?? '', FixedAsset::CONDITIONS, true) ? (string) $form['condition'] : 'new',
            'notes' => $this->text($form['notes'] ?? null, 2000),
        ];
    }

    /** @param  array<string, mixed>  $values */
    private function movement(User $by, FixedAsset $asset, string $kind, string $date, array $values): AssetMovement
    {
        return AssetMovement::query()->create([...$values, 'asset_id' => $asset->id, 'kind' => $kind, 'happened_on' => $date, 'created_by' => (int) $by->getAuthIdentifier()]);
    }

    /** The next free tag, `FA-000001`. */
    private function nextTag(): string
    {
        $last = (int) FixedAsset::query()->max('id');

        do {
            $tag = sprintf('FA-%06d', ++$last);
        } while (FixedAsset::query()->where('tag', $tag)->exists());

        return $tag;
    }

    private function usdLine(string $account, string $amount, bool $debit, string $project, ?string $center = null): LineDraft
    {
        return new LineDraft(accountCode: $account, currency: 'USD', debit: $debit ? $amount : null, credit: $debit ? null : $amount, projectCode: $project, center: $center);
    }

    /**
     * @param  list<LineDraft>  $lines
     *
     * @throws EntryException
     */
    private function journal(User $by, CarbonImmutable $date, string $description, array $lines): Voucher
    {
        try {
            return $this->posting->post(new VoucherDraft(type: VoucherType::Journal, date: $date, preparedBy: (int) $by->getAuthIdentifier(), lines: $lines, description: $description));
        } catch (LedgerRuleException $refused) {
            throw new EntryException(array_map(fn (Violation $violation): string => $violation->message, $refused->violations));
        }
    }

    private function day(string $text): ?CarbonImmutable
    {
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) === 1 ? CarbonImmutable::createFromFormat('!Y-m-d', $text) : false;

        return $day instanceof CarbonImmutable ? $day : null;
    }

    private function text(mixed $value, int $length = 255): ?string
    {
        $text = mb_substr(trim((string) $value), 0, $length);

        return $text !== '' ? $text : null;
    }

    /** @return numeric-string|null */
    private function number(string $text, int $scale = 2): ?string
    {
        $clean = str_replace([',', ' '], '', trim($text));

        return preg_match('/^\d+(\.\d+)?$/', $clean) === 1 ? Money::normalize($clean, $scale) : null;
    }
}
