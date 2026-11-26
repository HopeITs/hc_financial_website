<?php

namespace App\Livewire;

use App\Enums\ScreenAction;
use App\Models\GlProject;
use App\Models\RevenueItem;
use App\Models\RevenueSheet;
use App\Models\User;
use App\Services\Entries\CashVouchers;
use App\Services\Entries\EntryException;
use App\Services\Entries\ProjectRevenues;
use App\Services\Projects\ProjectAccess;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Project revenues (finance request of 2026-11-24), like a simple sales register: a day's takings of a project, line
 * by line from its price list; the month's takings by project and kind; and the price lists (with each item's number
 * and category for the point of sale). The point of sale itself is its own full-screen page (PointOfSale, finance
 * request of 2026-11-27), opened from here.
 */
#[Layout('components.layouts.app')]
class ProjectRevenuesScreen extends Component
{
    /** `record`, `list` or `items`. */
    #[Url]
    public string $tab = 'record';

    /** The month the list shows, `2026-11`. */
    #[Url]
    public string $month = '';

    /** @var array<string, string> */
    public array $sheet = [];

    /** @var list<array<string, string>> */
    public array $lines = [];

    /** The price-list item being changed, `new`, or ''. */
    public string $itemEditing = '';

    /** @var array<string, string> */
    public array $item = [];

    /** The category the price list shows, or '' for all. */
    public string $itemCategory = '';

    public ?string $notice = null;

    /** @var list<string> */
    public array $problems = [];

    public function mount(): void
    {
        $this->month = preg_match('/^\d{4}-\d{2}$/', $this->month) === 1 ? $this->month : CarbonImmutable::today()->format('Y-m');
        $this->tab = in_array($this->tab, ['record', 'list', 'items'], true) ? $this->tab : 'record';
        $this->blankSheet();
    }

    public function addLine(): void
    {
        $this->lines[] = $this->blankLine();
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines) ?: [$this->blankLine()];
    }

    /** Choosing an item of the price list brings its price. */
    public function updatedLines(mixed $value, string $key): void
    {
        [$index, $field] = array_pad(explode('.', $key, 2), 2, '');

        if ($field === 'item' && ($found = RevenueItem::query()->find((int) $value)) !== null) {
            $this->lines[(int) $index]['unit_price'] = (string) $found->unit_price;
            $this->lines[(int) $index]['description'] = $found->name;
        }
    }

    public function record(ProjectRevenues $revenues): void
    {
        $this->guard(ScreenAction::Create);
        $this->clearFeedback();

        try {
            $saved = $revenues->record($this->user(), $this->sheet, $this->lines);
        } catch (EntryException $refused) {
            $this->problems = $refused->problems;

            return;
        }

        $this->notice = __('revenues.recorded', ['voucher' => $saved->voucher_id, 'total' => $saved->total, 'currency' => $saved->currency]);
        $project = $this->sheet['project'];
        $account = $this->sheet['cash_account'];
        $this->blankSheet();
        $this->sheet['project'] = $project;
        $this->sheet['cash_account'] = $account;
    }

    public function newItem(): void
    {
        $this->guard(ScreenAction::Edit);
        $this->itemEditing = 'new';
        $this->item = ['project' => '', 'code' => '', 'name' => '', 'kind' => 'sales', 'category' => $this->itemCategory, 'unit_price' => '', 'currency' => 'SYP', 'active' => '1'];
        $this->clearFeedback();
    }

    public function editItem(int $id): void
    {
        $this->guard(ScreenAction::Edit);
        $found = RevenueItem::query()->findOrFail($id);
        $this->itemEditing = (string) $found->id;
        $this->item = [
            'project' => $found->project_code, 'code' => (string) ($found->code ?? ''), 'name' => $found->name, 'kind' => $found->kind,
            'category' => (string) ($found->category ?? ''), 'unit_price' => (string) $found->unit_price,
            'currency' => $found->currency, 'active' => $found->active ? '1' : '0',
        ];
        $this->clearFeedback();
    }

    public function saveItem(ProjectRevenues $revenues): void
    {
        $this->guard(ScreenAction::Edit);
        $this->clearFeedback();

        try {
            $revenues->saveItem($this->itemEditing === 'new' ? null : RevenueItem::query()->findOrFail((int) $this->itemEditing), $this->item);
        } catch (EntryException $refused) {
            $this->problems = $refused->problems;

            return;
        }

        $this->itemEditing = '';
        $this->notice = __('revenues.item_saved');
    }

    public function cancelItem(): void
    {
        $this->itemEditing = '';
    }

    public function render(ProjectRevenues $revenues, CashVouchers $vouchers): View
    {
        $user = $this->user();
        abort_unless($user->canOnScreen('revenues', ScreenAction::View), 403);
        $arabic = app()->getLocale() === 'ar';
        $month = CarbonImmutable::createFromFormat('!Y-m', $this->month) ?: CarbonImmutable::today();
        $limit = app(ProjectAccess::class)->limitedTo($user);
        $posReady = ProjectRevenues::pointOfSaleReady();
        $allItems = $this->tab === 'items' ? RevenueItem::query()->orderBy('project_code')->orderByDesc('active')->orderBy('name')->get() : collect();

        return view('livewire.project-revenues', [
            'posReady' => $posReady,
            'projects' => GlProject::query()->where('is_general_account', false)
                ->when($limit !== null, fn ($query) => $query->whereIn('code', $limit ?? []))
                ->orderBy('code')->get()->mapWithKeys(fn (GlProject $project): array => [$project->code => $project->code.' · '.$project->name])->all(),
            'cashAccounts' => $vouchers->cashAccounts()->map(fn ($account): string => $account->code.' · '.($arabic ? $account->name_ar : $account->name_en).' ('.$account->currency.')')->all(),
            'priceList' => ($this->sheet['project'] ?? '') === '' ? collect() : RevenueItem::query()->where('project_code', $this->sheet['project'])->where('active', true)->orderBy('kind')->orderBy('name')->get(),
            'itemCategories' => $posReady ? $allItems->map(fn (RevenueItem $entry): string => (string) ($entry->category ?? ''))->filter()->unique()->sort()->values() : collect(),
            'items' => $this->itemCategory === '' ? $allItems : $allItems->filter(fn (RevenueItem $entry): bool => $entry->category === $this->itemCategory)->values(),
            'sheets' => $this->tab === 'list' ? RevenueSheet::query()->where('status', '!=', RevenueSheet::OPEN)->whereDate('day', '>=', $month->startOfMonth()->toDateString())->whereDate('day', '<=', $month->endOfMonth()->toDateString())->orderByDesc('day')->orderByDesc('id')->get() : collect(),
            'summary' => $this->tab === 'list' ? $revenues->summary($month) : [],
            'canCreate' => $user->canOnScreen('revenues', ScreenAction::Create),
            'canEdit' => $user->canOnScreen('revenues', ScreenAction::Edit),
        ]);
    }

    private function blankSheet(): void
    {
        $this->sheet = ['project' => '', 'day' => CarbonImmutable::today()->toDateString(), 'cash_account' => '', 'rate' => '', 'center' => '', 'notes' => ''];
        $this->lines = [$this->blankLine(), $this->blankLine(), $this->blankLine()];
    }

    /** @return array<string, string> */
    private function blankLine(): array
    {
        return ['item' => '', 'description' => '', 'kind' => 'other', 'quantity' => '1', 'unit_price' => ''];
    }

    private function clearFeedback(): void
    {
        $this->notice = null;
        $this->problems = [];
    }

    private function guard(ScreenAction $action): void
    {
        abort_unless($this->user()->canOnScreen('revenues', $action), 403);
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
