<?php

namespace App\Livewire;

use App\Enums\ScreenAction;
use App\Models\GlProject;
use App\Models\RevenueItem;
use App\Models\RevenueSheet;
use App\Models\RevenueTicket;
use App\Models\User;
use App\Services\Projects\ProjectAccess;
use App\Services\Entries\CashVouchers;
use App\Services\Entries\EntryException;
use App\Services\Entries\ProjectRevenues;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Project revenues (finance request of 2026-11-24), like a simple sales register: a day's takings of a project, line
 * by line from its price list; the month's takings by project and kind; and the price lists. Since 2026-11-26 it opens
 * on a point of sale: a shift of a project and cash account, items tapped from coloured tiles by category into a basket,
 * each sale with its discount and change, and the shift closed into one receipt voucher.
 */
#[Layout('components.layouts.app')]
class ProjectRevenuesScreen extends Component
{
    /** `pos`, `record`, `list` or `items`. */
    #[Url]
    public string $tab = '';

    /** The point-of-sale shift being worked on (finance request of 2026-11-26). */
    #[Url(as: 'shift')]
    public ?int $shiftId = null;

    /** @var array<string, string> project, cash_account, day of the shift to open */
    public array $opening = [];

    /** @var array<int|string, string> the basket: item id => quantity */
    public array $basket = [];

    public string $category = '';

    public string $find = '';

    /** @var array<string, string> discount_percent, discount, paid, note */
    public array $payment = [];

    public bool $closing = false;

    /** @var array<string, string> rate, center, notes */
    public array $close = [];

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

    public ?string $notice = null;

    /** @var list<string> */
    public array $problems = [];

    public function mount(): void
    {
        $this->month = preg_match('/^\d{4}-\d{2}$/', $this->month) === 1 ? $this->month : CarbonImmutable::today()->format('Y-m');
        $this->tab = in_array($this->tab, ['pos', 'record', 'list', 'items'], true) ? $this->tab : (ProjectRevenues::pointOfSaleReady() ? 'pos' : 'record');
        $this->blankSheet();
        $this->opening = ['project' => '', 'cash_account' => '', 'day' => CarbonImmutable::today()->toDateString()];
        $this->blankPayment();
        $this->close = ['rate' => '', 'center' => '', 'notes' => ''];
    }

    public function openShift(ProjectRevenues $revenues): void
    {
        $this->guard(ScreenAction::Create);
        $this->clearFeedback();

        try {
            $this->shiftId = $revenues->openShift($this->user(), $this->opening)->id;
        } catch (EntryException $refused) {
            $this->problems = $refused->problems;

            return;
        }

        $this->basket = [];
        $this->category = '';
    }

    public function resumeShift(int $id): void
    {
        $this->clearFeedback();
        $this->shiftId = $this->ownShift($id)->id;
        $this->basket = [];
        $this->closing = false;
    }

    public function leaveShift(): void
    {
        $this->shiftId = null;
        $this->basket = [];
        $this->closing = false;
    }

    /** A tap on an item's tile puts one more of it in the basket. */
    public function addItem(int $id): void
    {
        $this->basket[$id] = (string) (((float) ($this->basket[$id] ?? 0)) + 1);
    }

    public function lessItem(int $id): void
    {
        $left = ((float) ($this->basket[$id] ?? 0)) - 1;

        if ($left <= 0) {
            unset($this->basket[$id]);

            return;
        }

        $this->basket[$id] = (string) $left;
    }

    public function dropItem(int $id): void
    {
        unset($this->basket[$id]);
    }

    public function clearBasket(): void
    {
        $this->basket = [];
        $this->blankPayment();
    }

    public function sell(ProjectRevenues $revenues): void
    {
        $this->guard(ScreenAction::Create);
        $this->clearFeedback();

        try {
            $ticket = $revenues->sell($this->user(), $this->ownShift((int) $this->shiftId), $this->basket, $this->payment);
        } catch (EntryException $refused) {
            $this->problems = $refused->problems;

            return;
        }

        $this->notice = __('revenues.pos.sold', ['number' => $ticket->number, 'total' => $ticket->net]);
        $this->basket = [];
        $this->blankPayment();
    }

    public function voidTicket(ProjectRevenues $revenues, int $id): void
    {
        $this->guard(ScreenAction::Create);
        $this->clearFeedback();
        $ticket = RevenueTicket::query()->where('sheet_id', $this->ownShift((int) $this->shiftId)->id)->findOrFail($id);

        try {
            $revenues->voidTicket($ticket);
        } catch (EntryException $refused) {
            $this->problems = $refused->problems;

            return;
        }

        $this->notice = __('revenues.pos.voided', ['number' => $ticket->number]);
    }

    public function closeShift(ProjectRevenues $revenues): void
    {
        $this->guard(ScreenAction::Create);
        $this->clearFeedback();

        try {
            $closed = $revenues->closeShift($this->user(), $this->ownShift((int) $this->shiftId), $this->close);
        } catch (EntryException $refused) {
            $this->problems = $refused->problems;

            return;
        }

        $this->notice = $closed === null
            ? __('revenues.pos.closed_empty')
            : __('revenues.pos.closed', ['voucher' => $closed->voucher_id, 'total' => $closed->total, 'currency' => $closed->currency]);
        $this->leaveShift();
        $this->close = ['rate' => '', 'center' => '', 'notes' => ''];
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
        $this->item = ['project' => '', 'name' => '', 'kind' => 'sales', 'category' => '', 'unit_price' => '', 'currency' => 'SYP', 'active' => '1'];
        $this->clearFeedback();
    }

    public function editItem(int $id): void
    {
        $this->guard(ScreenAction::Edit);
        $found = RevenueItem::query()->findOrFail($id);
        $this->itemEditing = (string) $found->id;
        $this->item = [
            'project' => $found->project_code, 'name' => $found->name, 'kind' => $found->kind, 'category' => (string) ($found->category ?? ''), 'unit_price' => (string) $found->unit_price,
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

        return view('livewire.project-revenues', [
            ...($this->tab === 'pos' && ProjectRevenues::pointOfSaleReady() ? $this->pointOfSale() : []),
            'posReady' => ProjectRevenues::pointOfSaleReady(),
            'projects' => GlProject::query()->where('is_general_account', false)
                ->when(($limit = auth()->user() instanceof User ? app(ProjectAccess::class)->limitedTo(auth()->user()) : null) !== null, fn ($query) => $query->whereIn('code', $limit ?? []))
                ->orderBy('code')->get()->mapWithKeys(fn (GlProject $project): array => [$project->code => $project->code.' · '.$project->name])->all(),
            'cashAccounts' => $vouchers->cashAccounts()->map(fn ($account): string => $account->code.' · '.($arabic ? $account->name_ar : $account->name_en).' ('.$account->currency.')')->all(),
            'priceList' => ($this->sheet['project'] ?? '') === '' ? collect() : RevenueItem::query()->where('project_code', $this->sheet['project'])->where('active', true)->orderBy('kind')->orderBy('name')->get(),
            'items' => $this->tab === 'items' ? RevenueItem::query()->orderBy('project_code')->orderByDesc('active')->orderBy('name')->get() : collect(),
            'sheets' => $this->tab === 'list' ? RevenueSheet::query()->whereDate('day', '>=', $month->startOfMonth()->toDateString())->whereDate('day', '<=', $month->endOfMonth()->toDateString())->orderByDesc('day')->orderByDesc('id')->get() : collect(),
            'summary' => $this->tab === 'list' ? $revenues->summary($month) : [],
            'canCreate' => $user->canOnScreen('revenues', ScreenAction::Create),
            'canEdit' => $user->canOnScreen('revenues', ScreenAction::Edit),
        ]);
    }

    /**
     * What the point-of-sale tab shows: the shift, its price list by category, the basket with its totals, and its sales.
     *
     * @return array<string, mixed>
     */
    private function pointOfSale(): array
    {
        $shift = $this->shiftId !== null ? RevenueSheet::query()->where('status', RevenueSheet::OPEN)->find($this->shiftId) : null;

        if ($shift !== null && ! $this->mayWorkOn($shift)) {
            $shift = null;
        }

        $catalog = $shift === null ? collect() : RevenueItem::query()->where('project_code', $shift->project_code)->where('active', true)
            ->where('currency', $shift->currency)->orderBy('category')->orderBy('name')->get();
        $categories = $catalog->map(fn (RevenueItem $item): string => (string) ($item->category ?: __('revenues.kinds.'.$item->kind)))->unique()->values();
        $shown = $catalog
            ->when($this->category !== '', fn ($items) => $items->filter(fn (RevenueItem $item): bool => ($item->category ?: __('revenues.kinds.'.$item->kind)) === $this->category))
            ->when(trim($this->find) !== '', fn ($items) => $items->filter(fn (RevenueItem $item): bool => mb_stripos($item->name, trim($this->find)) !== false));

        $rows = [];
        $gross = '0.00';
        foreach ($this->basket as $id => $quantity) {
            $item = $catalog->firstWhere('id', (int) $id);
            if (! $item instanceof RevenueItem) {
                continue;
            }

            $amount = is_numeric($quantity) ? bcmul((string) $quantity, (string) $item->unit_price, 2) : '0.00';
            $gross = bcadd($gross, $amount, 2);
            $rows[] = ['id' => $item->id, 'name' => $item->name, 'price' => (string) $item->unit_price, 'quantity' => (string) $quantity, 'amount' => $amount];
        }

        $discountText = str_replace(',', '', trim($this->payment['discount'] ?? ''));
        $percentText = str_replace(',', '', trim($this->payment['discount_percent'] ?? ''));
        $discount = is_numeric($discountText) && (float) $discountText > 0 ? $discountText : (is_numeric($percentText) ? bcdiv(bcmul($gross, $percentText, 4), '100', 2) : '0');
        $net = bcsub($gross, $discount, 2);
        $paidText = str_replace(',', '', trim($this->payment['paid'] ?? ''));

        return [
            'shift' => $shift,
            'openShifts' => RevenueSheet::query()->where('status', RevenueSheet::OPEN)
                ->when(! $this->user()->canOnScreen('revenues', ScreenAction::Edit), fn ($query) => $query->where('created_by', (int) $this->user()->getAuthIdentifier()))
                ->orderByDesc('day')->get(),
            'categories' => $categories,
            'tiles' => $shown->values(),
            'tileTones' => $categories->flip()->map(fn (int $index): int => $index % 6 + 1)->all(),
            'basketRows' => $rows,
            'gross' => $gross,
            'discount' => $discount,
            'net' => $net,
            'change' => is_numeric($paidText) ? bcsub($paidText, $net, 2) : null,
            'tickets' => $shift?->tickets()->with('lines')->get() ?? collect(),
        ];
    }

    /** An open shift the person may sell on: their own, or anyone's for those who may edit revenues. */
    private function ownShift(int $id): RevenueSheet
    {
        $shift = RevenueSheet::query()->where('status', RevenueSheet::OPEN)->findOrFail($id);
        abort_unless($this->mayWorkOn($shift), 403);

        return $shift;
    }

    private function mayWorkOn(RevenueSheet $shift): bool
    {
        return $shift->created_by === (int) $this->user()->getAuthIdentifier() || $this->user()->canOnScreen('revenues', ScreenAction::Edit);
    }

    private function blankPayment(): void
    {
        $this->payment = ['discount_percent' => '', 'discount' => '', 'paid' => '', 'note' => ''];
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
