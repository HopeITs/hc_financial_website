<?php

namespace App\Livewire;

use App\Enums\ScreenAction;
use App\Models\AssetCategory;
use App\Models\AssetCount;
use App\Models\AssetCountLine;
use App\Models\FixedAsset;
use App\Models\GlProject;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Assets\FixedAssets;
use App\Services\Entries\CashVouchers;
use App\Services\Entries\EntryException;
use App\Services\Projects\ProjectAccess;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The warehouses and fixed assets (finance request of 2026-11-28), in tabs:
 *
 * - the register: every asset with its place, holder, state and book value, and an asset's card with its history and
 *   what can be done with it (hand over, return, move, repair, change its details, write off, sell);
 * - receiving: from a purchase file's goods, as a gift in kind, or as an asset already owned;
 * - counts: a warehouse's assets ticked found or missing, then closed;
 * - depreciation: the month's depreciation of every asset, booked as one journal;
 * - the warehouses and the kinds of asset.
 */
#[Layout('components.layouts.app')]
class FixedAssetsScreen extends Component
{
    public const TABS = ['register', 'receive', 'counts', 'depreciation', 'warehouses', 'categories'];

    #[Url]
    public string $tab = 'register';

    #[Url(as: 'asset')]
    public ?int $assetId = null;

    #[Url(as: 'count')]
    public ?int $countId = null;

    public string $search = '';

    public string $status = 'owned';

    public string $warehouse = '';

    public string $category = '';

    /** How an asset comes in: `purchase`, `donation` or `opening`. */
    public string $source = 'purchase';

    /** @var array<string, string> */
    public array $form = [];

    public string $purchaseItem = '';

    /** What is being done with the open asset: '', handover, return, transfer, repair, edit, write_off, sell. */
    public string $action = '';

    /** @var array<string, string> */
    public array $actionForm = [];

    public string $month = '';

    /** @var array<string, string> */
    public array $countForm = [];

    public string $warehouseEditing = '';

    /** @var array<string, string> */
    public array $warehouseForm = [];

    public string $categoryEditing = '';

    /** @var array<string, string> */
    public array $categoryForm = [];

    public ?string $notice = null;

    /** @var list<string> */
    public array $problems = [];

    public function mount(): void
    {
        $this->tab = in_array($this->tab, self::TABS, true) ? $this->tab : 'register';
        $this->month = CarbonImmutable::today()->subMonth()->format('Y-m');
        $this->blankForm();
        $this->countForm = ['warehouse' => '', 'date' => CarbonImmutable::today()->toDateString(), 'by' => ''];
    }

    public function updatedTab(): void
    {
        $this->clearFeedback();
        $this->action = '';
    }

    public function openAsset(int $id): void
    {
        $this->clearFeedback();
        $this->assetId = $this->assetOrFail($id)->id;
        $this->action = '';
    }

    public function closeAsset(): void
    {
        $this->assetId = null;
        $this->action = '';
    }

    public function startAction(string $action): void
    {
        $this->clearFeedback();
        $asset = $this->assetOrFail((int) $this->assetId);
        $this->guard(in_array($action, ['handover', 'return', 'transfer', 'repair'], true) ? ScreenAction::Create : ScreenAction::Edit);
        $this->action = $action;
        $this->actionForm = $action === 'edit'
            ? ['name' => $asset->name, 'serial' => (string) $asset->serial, 'description' => (string) $asset->description, 'center' => (string) $asset->center,
                'salvage' => (string) $asset->salvage_usd, 'life_months' => (string) $asset->life_months, 'condition' => $asset->condition, 'notes' => (string) $asset->notes]
            : ['date' => CarbonImmutable::today()->toDateString(), 'warehouse' => (string) ($asset->warehouse_id ?? ''), 'custodian' => '', 'condition' => $asset->condition,
                'reference' => '', 'notes' => '', 'reason' => '', 'price' => '', 'cash_account' => '', 'rate' => '', 'buyer' => ''];
    }

    public function runAction(FixedAssets $assets): void
    {
        $asset = $this->assetOrFail((int) $this->assetId);
        $this->attempt(function () use ($assets, $asset): void {
            $user = $this->user();

            match ($this->action) {
                'handover', 'return', 'transfer', 'repair' => $this->guarded(ScreenAction::Create, fn () => $assets->move($user, $asset, $this->action, $this->actionForm)),
                'edit' => $this->guarded(ScreenAction::Edit, fn () => $assets->update($asset, $this->actionForm)),
                'write_off' => $this->notice = __('assets.voucher_made', ['voucher' => $this->guarded(ScreenAction::Edit, fn () => $assets->writeOff($user, $asset, $this->actionForm))->id]),
                'sell' => $this->notice = __('assets.voucher_made', ['voucher' => $this->guarded(ScreenAction::Edit, fn () => $assets->sell($user, $asset, $this->actionForm))->id]),
                default => null,
            };

            $this->notice ??= __('assets.saved');
            $this->action = '';
        });
    }

    public function register(FixedAssets $assets): void
    {
        $this->attempt(function () use ($assets): void {
            $this->guard(ScreenAction::Create);

            if ($this->source === 'purchase') {
                $item = PurchaseItem::query()->find((int) $this->purchaseItem) ?? throw new EntryException([__('assets.problem.purchase_item')]);
                $this->mayUseProject((string) Purchase::query()->whereKey($item->purchase_id)->value('project_code'));
                $made = $assets->registerFromPurchase($this->user(), $item, $this->form);
                $this->notice = __('assets.registered_many', ['count' => $made->count(), 'tags' => $made->pluck('tag')->implode('، ')]);
            } else {
                $this->mayUseProject((string) ($this->form['project'] ?? ''));
                $asset = $assets->register($this->user(), [...$this->form, 'source' => $this->source]);
                $this->notice = __('assets.registered', ['tag' => $asset->tag]);
            }

            $this->blankForm();
            $this->purchaseItem = '';
        });
    }

    /** Choosing a purchased item brings its name and the kind its budget line suggests. */
    public function updatedPurchaseItem(): void
    {
        $item = PurchaseItem::query()->find((int) $this->purchaseItem);
        $this->form['name'] = (string) $item?->description;
    }

    public function depreciate(FixedAssets $assets): void
    {
        $this->attempt(function () use ($assets): void {
            $this->guard(ScreenAction::Edit);
            $voucher = $assets->depreciate($this->user(), $this->month);
            $this->notice = __('assets.voucher_made', ['voucher' => $voucher->id]);
        });
    }

    public function openCount(FixedAssets $assets): void
    {
        $this->attempt(function () use ($assets): void {
            $this->guard(ScreenAction::Create);
            $warehouse = $this->countForm['warehouse'] === '' ? null : Warehouse::query()->findOrFail((int) $this->countForm['warehouse']);
            $this->countId = $assets->openCount($this->user(), $warehouse, $this->countForm['date'], $this->countForm['by'])->id;
        });
    }

    public function showCount(int $id): void
    {
        $this->clearFeedback();
        $this->countId = AssetCount::query()->findOrFail($id)->id;
    }

    public function markLine(FixedAssets $assets, int $lineId, string $found): void
    {
        $this->attempt(function () use ($assets, $lineId, $found): void {
            $this->guard(ScreenAction::Create);
            $line = AssetCountLine::query()->where('count_id', (int) $this->countId)->findOrFail($lineId);
            $assets->markCount($line, $found === '' ? null : $found === '1', (string) $line->condition, (string) $line->note);
        });
    }

    public function lineCondition(FixedAssets $assets, int $lineId, string $condition): void
    {
        $this->attempt(function () use ($assets, $lineId, $condition): void {
            $this->guard(ScreenAction::Create);
            $line = AssetCountLine::query()->where('count_id', (int) $this->countId)->findOrFail($lineId);
            $assets->markCount($line, $line->found, $condition, (string) $line->note);
        });
    }

    public function lineNote(FixedAssets $assets, int $lineId, string $note): void
    {
        $this->attempt(function () use ($assets, $lineId, $note): void {
            $this->guard(ScreenAction::Create);
            $line = AssetCountLine::query()->where('count_id', (int) $this->countId)->findOrFail($lineId);
            $assets->markCount($line, $line->found, (string) $line->condition, $note);
        });
    }

    public function markAllFound(FixedAssets $assets): void
    {
        $this->attempt(function () use ($assets): void {
            $this->guard(ScreenAction::Create);
            foreach (AssetCountLine::query()->where('count_id', (int) $this->countId)->whereNull('found')->get() as $line) {
                $assets->markCount($line, true, (string) $line->condition, (string) $line->note);
            }
        });
    }

    public function closeCount(FixedAssets $assets): void
    {
        $this->attempt(function () use ($assets): void {
            $this->guard(ScreenAction::Create);
            $assets->closeCount($this->user(), AssetCount::query()->findOrFail((int) $this->countId));
            $this->notice = __('assets.count.closed');
        });
    }

    public function editWarehouse(string $id): void
    {
        $this->guard(ScreenAction::Edit);
        $found = $id === 'new' ? null : Warehouse::query()->findOrFail((int) $id);
        $this->warehouseEditing = $id;
        $this->warehouseForm = ['name' => (string) $found?->name, 'city' => (string) $found?->city, 'center' => (string) $found?->center, 'keeper' => (string) $found?->keeper, 'active' => $found === null || $found->active ? '1' : '0'];
    }

    public function saveWarehouse(FixedAssets $assets): void
    {
        $this->attempt(function () use ($assets): void {
            $this->guard(ScreenAction::Edit);
            $assets->saveWarehouse($this->warehouseEditing === 'new' ? null : Warehouse::query()->findOrFail((int) $this->warehouseEditing), $this->warehouseForm);
            $this->warehouseEditing = '';
            $this->notice = __('assets.saved');
        });
    }

    public function editCategory(string $id): void
    {
        $this->guard(ScreenAction::Edit);
        $found = $id === 'new' ? null : AssetCategory::query()->findOrFail((int) $id);
        $this->categoryEditing = $id;
        $this->categoryForm = ['name_ar' => (string) $found?->name_ar, 'name_en' => (string) $found?->name_en, 'account_code' => (string) ($found?->account_code ?? '1403'), 'life_months' => (string) ($found?->life_months ?? 60), 'active' => $found === null || $found->active ? '1' : '0'];
    }

    public function saveCategory(FixedAssets $assets): void
    {
        $this->attempt(function () use ($assets): void {
            $this->guard(ScreenAction::Edit);
            $assets->saveCategory($this->categoryEditing === 'new' ? null : AssetCategory::query()->findOrFail((int) $this->categoryEditing), $this->categoryForm);
            $this->categoryEditing = '';
            $this->notice = __('assets.saved');
        });
    }

    public function render(FixedAssets $assets, CashVouchers $vouchers): View
    {
        $user = $this->user();
        abort_unless($user->canOnScreen('assets', ScreenAction::View), 403);
        $ready = FixedAssets::ready();
        $limit = app(ProjectAccess::class)->limitedTo($user);
        $arabic = app()->getLocale() === 'ar';
        $asset = $ready && $this->assetId !== null ? $this->visibleAssets($limit)->with(['category', 'warehouse', 'movements'])->find($this->assetId) : null;
        $list = $ready && $this->tab === 'register' && $asset === null ? $this->visibleAssets($limit)->with(['category', 'warehouse'])
            ->when($this->status === 'owned', fn ($query) => $query->whereIn('status', FixedAsset::OWNED))
            ->when(! in_array($this->status, ['owned', ''], true), fn ($query) => $query->where('status', $this->status))
            ->when($this->warehouse !== '', fn ($query) => $query->where('warehouse_id', (int) $this->warehouse))
            ->when($this->category !== '', fn ($query) => $query->where('category_id', (int) $this->category))
            ->when(trim($this->search) !== '', function ($query) {
                $term = '%'.trim($this->search).'%';
                $query->where(fn ($inner) => $inner->where('tag', 'like', $term)->orWhere('name', 'like', $term)->orWhere('serial', 'like', $term)->orWhere('custodian', 'like', $term));
            })
            ->orderBy('tag')->limit(500)->get() : collect();
        $count = $ready && $this->tab === 'counts' && $this->countId !== null ? AssetCount::query()->with(['warehouse', 'lines.asset'])->find($this->countId) : null;
        $depreciation = $ready && $this->tab === 'depreciation' && preg_match('/^\d{4}-\d{2}$/', $this->month) === 1 ? $assets->depreciationFor($this->month) : [];

        return view('livewire.fixed-assets', [
            'ready' => $ready,
            'asset' => $asset,
            'assetBook' => $asset !== null ? ['accumulated' => $assets->accumulated($asset), 'book' => $assets->bookValue($asset)] : null,
            'list' => $list,
            'books' => $list->mapWithKeys(fn (FixedAsset $row): array => [$row->id => $assets->bookValue($row)])->all(),
            'totals' => $ready ? $this->totals($limit) : ['count' => 0, 'cost' => '0.00', 'in_use' => 0, 'in_store' => 0],
            'warehouses' => $ready ? Warehouse::query()->orderByDesc('active')->orderBy('name')->get() : collect(),
            'categories' => $ready ? AssetCategory::query()->orderBy('account_code')->get() : collect(),
            'projects' => GlProject::query()->where('is_general_account', false)->when($limit !== null, fn ($query) => $query->whereIn('code', $limit ?? []))
                ->orderBy('code')->get()->mapWithKeys(fn (GlProject $project): array => [$project->code => $project->code.' · '.$project->name])->all(),
            'cashAccounts' => $vouchers->cashAccounts()->map(fn ($account): string => $account->code.' · '.($arabic ? $account->name_ar : $account->name_en).' ('.$account->currency.')')->all(),
            'toRegister' => $ready && $this->tab === 'receive' && $this->source === 'purchase' ? $assets->purchasesToRegister($limit) : [],
            'counts' => $ready && $this->tab === 'counts' ? AssetCount::query()->with('warehouse')->orderByDesc('id')->limit(50)->get() : collect(),
            'count' => $count,
            'countLines' => $count?->lines->filter(fn ($line): bool => $line->asset !== null && ($limit === null || in_array($line->asset->project_code, $limit, true)))->values() ?? collect(),
            'depreciation' => $depreciation,
            'depreciationTotal' => array_reduce($depreciation, fn (string $sum, array $row): string => bcadd($sum, $row['amount'], 2), Money::zero()),
            'canCreate' => $user->canOnScreen('assets', ScreenAction::Create),
            'canEdit' => $user->canOnScreen('assets', ScreenAction::Edit),
            // The key figures' tiles show an amount as text, with thousands.
            'figure' => fn (mixed $amount, int $decimals = 2): string => number_format((float) $amount, $decimals),
        ]);
    }

    /**
     * @param  list<string>|null  $limit
     * @return \Illuminate\Database\Eloquent\Builder<FixedAsset>
     */
    private function visibleAssets(?array $limit)
    {
        return FixedAsset::query()->when($limit !== null, fn ($query) => $query->whereIn('project_code', $limit ?? []));
    }

    /**
     * @param  list<string>|null  $limit
     * @return array{count: int, cost: string, in_use: int, in_store: int}
     */
    private function totals(?array $limit): array
    {
        $owned = $this->visibleAssets($limit)->whereIn('status', FixedAsset::OWNED);

        return [
            'count' => (clone $owned)->count(),
            'cost' => Money::normalize((clone $owned)->sum('cost_usd')),
            'in_use' => (clone $owned)->where('status', FixedAsset::IN_USE)->count(),
            'in_store' => (clone $owned)->where('status', FixedAsset::IN_STORE)->count(),
        ];
    }

    private function assetOrFail(int $id): FixedAsset
    {
        return $this->visibleAssets(app(ProjectAccess::class)->limitedTo($this->user()))->findOrFail($id);
    }

    /** @throws EntryException */
    private function mayUseProject(string $project): void
    {
        if ($project !== '' && ! app(ProjectAccess::class)->canOpen($this->user(), $project)) {
            throw new EntryException([__('assets.problem.project')]);
        }
    }

    private function blankForm(): void
    {
        $this->form = [
            'name' => '', 'category' => '', 'project' => '', 'center' => '', 'warehouse' => '', 'custodian' => '', 'serial' => '',
            'description' => '', 'acquired_on' => CarbonImmutable::today()->toDateString(), 'cost' => '', 'currency' => 'USD', 'rate' => '',
            'salvage' => '', 'life_months' => '', 'condition' => 'new', 'notes' => '', 'count' => '1', 'serials' => '',
        ];
    }

    /** Runs an action, showing what the service refused instead of failing. */
    private function attempt(callable $action): void
    {
        $this->clearFeedback();

        try {
            $action();
        } catch (EntryException $refused) {
            $this->problems = $refused->problems;
        }
    }

    private function guarded(ScreenAction $action, callable $then): mixed
    {
        $this->guard($action);

        return $then();
    }

    private function guard(ScreenAction $action): void
    {
        abort_unless($this->user()->canOnScreen('assets', $action), 403);
    }

    private function clearFeedback(): void
    {
        $this->notice = null;
        $this->problems = [];
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
