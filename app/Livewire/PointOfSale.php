<?php

namespace App\Livewire;

use App\Enums\ScreenAction;
use App\Models\GlProject;
use App\Models\RevenueItem;
use App\Models\RevenueLine;
use App\Models\RevenueSheet;
use App\Models\RevenueTicket;
use App\Models\User;
use App\Services\Entries\CashVouchers;
use App\Services\Entries\EntryException;
use App\Services\Entries\PointOfSale as PointOfSaleService;
use App\Services\Entries\ProjectRevenues;
use App\Services\Projects\ProjectAccess;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The point of sale of the project revenues, full screen and worked like a restaurant's (finance request of
 * 2026-11-27):
 *
 * - the tables: every open order of the shift as a card (its table, total, waiter, printed or not), with open a
 *   table, the shift's archive, end of day (its report) and end the shift;
 * - an order: big action buttons (add items, service, print preview, close the table, back), its lines with their
 *   quantity and note, its details (invoice number, date, table, customer, persons, payment, waiter, note) and the
 *   totals bar with the discount and the amount to pay;
 * - the add-items window: categories, coloured item tiles, "quantity*number" typed in, the chosen items with their
 *   quantities, and save.
 */
#[Layout('components.layouts.pos')]
class PointOfSale extends Component
{
    #[Url(as: 'shift')]
    public ?int $shiftId = null;

    #[Url(as: 'order')]
    public ?int $orderId = null;

    /** @var array<string, string> project, cash_account, day */
    public array $opening = [];

    public bool $newTable = false;

    /** @var array<string, string> table, service, persons, waiter */
    public array $table = [];

    /** Which orders the tables screen shows: '', or one of RevenueTicket::SERVICES. */
    public string $service = '';

    public bool $archive = false;

    public bool $adding = false;

    /** @var array<int|string, string> the items chosen on the add-items window: item id => quantity */
    public array $pending = [];

    public string $category = '';

    public string $find = '';

    public string $quick = '';

    /** @var array<string, string> the open order's details */
    public array $details = [];

    public bool $paying = false;

    public string $paid = '';

    public bool $closing = false;

    /** @var array<string, string> rate, center, notes */
    public array $close = [];

    public ?string $notice = null;

    /** @var list<string> */
    public array $problems = [];

    public function mount(): void
    {
        abort_unless($this->user()->canOnScreen('revenues', ScreenAction::View), 403);
        $this->opening = ['project' => '', 'cash_account' => '', 'day' => CarbonImmutable::today()->toDateString()];
        $this->table = ['table' => '', 'service' => 'hall', 'persons' => '', 'waiter' => ''];
        $this->close = ['rate' => '', 'center' => '', 'notes' => ''];

        if ($this->shiftId !== null && $this->workingShift() === null) {
            $this->shiftId = null;
        }

        $order = $this->workingOrder();
        $this->orderId = $order?->id;
        $this->loadDetails($order);
    }

    public function openShift(PointOfSaleService $pos): void
    {
        $this->attempt(function () use ($pos): void {
            $this->guard();
            $this->shiftId = $pos->openShift($this->user(), $this->opening)->id;
            $this->orderId = null;
        });
    }

    public function resumeShift(int $id): void
    {
        $this->clearFeedback();
        $this->shiftId = $this->shiftOrFail($id)->id;
        $this->orderId = null;
    }

    public function leaveShift(): void
    {
        $this->shiftId = null;
        $this->orderId = null;
        $this->closing = false;
        $this->archive = false;
    }

    public function openTable(PointOfSaleService $pos): void
    {
        $this->attempt(function () use ($pos): void {
            $this->guard();
            $order = $pos->openOrder($this->user(), $this->shiftOrFail((int) $this->shiftId), $this->table);
            $this->newTable = false;
            $this->table = ['table' => '', 'service' => 'hall', 'persons' => '', 'waiter' => ''];
            $this->showOrder($order->id);
            // A new order goes straight to its items, as on a till.
            if ($order->lines()->doesntExist()) {
                $this->startAdding();
            }
        });
    }

    public function showOrder(int $id): void
    {
        $this->clearFeedback();
        $order = $this->orderOfShift($id);
        $this->orderId = $order->id;
        $this->paying = false;
        $this->loadDetails($order);
    }

    public function backToTables(): void
    {
        $this->orderId = null;
        $this->adding = false;
        $this->paying = false;
    }

    public function startAdding(): void
    {
        $this->adding = true;
        $this->pending = [];
        $this->quick = '';
        $this->find = '';
    }

    public function tapItem(int $id): void
    {
        $this->pending[$id] = $this->plus((string) ($this->pending[$id] ?? '0'), '1');
    }

    public function pendingLess(int $id): void
    {
        $left = bcsub($this->numeric((string) ($this->pending[$id] ?? '0')), '1', 2);

        if (bccomp($left, '0', 2) !== 1) {
            unset($this->pending[$id]);

            return;
        }

        $this->pending[$id] = $this->trimmed($left);
    }

    public function dropPending(int $id): void
    {
        unset($this->pending[$id]);
    }

    public function clearPending(): void
    {
        $this->pending = [];
    }

    /** "3*12" then Enter: three of item 12 into the chosen items. */
    public function quickAdd(PointOfSaleService $pos): void
    {
        $this->clearFeedback();
        $found = $pos->readQuickEntry($this->shiftOrFail((int) $this->shiftId), $this->quick);

        if ($found === null) {
            $this->problems = [__('revenues.problem.pos_quick', ['typed' => $this->quick])];

            return;
        }

        [$item, $quantity] = $found;
        $this->pending[$item->id] = $this->plus((string) ($this->pending[$item->id] ?? '0'), $quantity);
        $this->quick = '';
    }

    public function savePending(PointOfSaleService $pos): void
    {
        $this->attempt(function () use ($pos): void {
            $this->guard();
            $pos->addItems($this->orderOfShift((int) $this->orderId), $this->pending);
            $this->pending = [];
            $this->adding = false;
        });
    }

    public function closeAdding(): void
    {
        $this->adding = false;
        $this->pending = [];
    }

    public function lineQuantity(PointOfSaleService $pos, int $lineId, string $quantity): void
    {
        $this->attempt(function () use ($pos, $lineId, $quantity): void {
            $this->guard();
            $pos->setQuantity($this->lineOfOrder($lineId), $quantity);
        });
    }

    public function lineStep(PointOfSaleService $pos, int $lineId, int $step): void
    {
        $line = $this->lineOfOrder($lineId);
        $this->lineQuantity($pos, $lineId, $this->trimmed(bcadd($this->numeric((string) $line->quantity), (string) $step, 2)));
    }

    public function lineNote(PointOfSaleService $pos, int $lineId, string $note): void
    {
        $this->attempt(function () use ($pos, $lineId, $note): void {
            $this->guard();
            $pos->setLineNote($this->lineOfOrder($lineId), $note);
        });
    }

    public function lineRemove(PointOfSaleService $pos, int $lineId): void
    {
        $this->lineQuantity($pos, $lineId, '0');
    }

    /** The order's details save as they are changed. */
    public function updatedDetails(): void
    {
        $this->attempt(function (): void {
            $this->guard();
            app(PointOfSaleService::class)->updateOrder($this->orderOfShift((int) $this->orderId), $this->details);
        });
    }

    public function setService(string $service): void
    {
        $this->details['service'] = in_array($service, RevenueTicket::SERVICES, true) ? $service : 'hall';
        $this->updatedDetails();
    }

    public function printOrder(): void
    {
        $order = $this->orderOfShift((int) $this->orderId);
        $this->js('window.open('.json_encode(route('operations.revenues.pos.receipt', ['order' => $order->id])).', "_blank")');
    }

    public function startPaying(): void
    {
        $this->clearFeedback();
        $this->paying = true;
        $this->paid = '';
    }

    public function pay(PointOfSaleService $pos): void
    {
        $this->attempt(function () use ($pos): void {
            $this->guard();
            $order = $pos->pay($this->orderOfShift((int) $this->orderId), $this->paid);
            $this->notice = __('revenues.pos.paid_notice', ['number' => $order->number, 'total' => $order->net]);
            $this->paying = false;
            $this->paid = '';
            $this->orderId = null;
        });
    }

    public function cancelOrder(PointOfSaleService $pos, int $id): void
    {
        $this->attempt(function () use ($pos, $id): void {
            $this->guard();
            $order = $this->orderOfShift($id);
            $pos->cancel($order);
            $this->notice = __('revenues.pos.voided', ['number' => $order->number]);

            if ($this->orderId === $id) {
                $this->orderId = null;
            }
        });
    }

    public function closeShift(PointOfSaleService $pos): void
    {
        $this->attempt(function () use ($pos): void {
            $this->guard();
            $closed = $pos->closeShift($this->user(), $this->shiftOrFail((int) $this->shiftId), $this->close);
            $this->notice = $closed === null
                ? __('revenues.pos.closed_empty')
                : __('revenues.pos.closed', ['voucher' => $closed->voucher_id, 'total' => $closed->total, 'currency' => $closed->currency]);
            $this->leaveShift();
        });
    }

    public function render(CashVouchers $vouchers): View
    {
        $user = $this->user();
        $shift = $this->workingShift();
        $order = $shift !== null ? $this->workingOrder() : null;
        $limit = app(ProjectAccess::class)->limitedTo($user);
        $arabic = app()->getLocale() === 'ar';

        return view('livewire.point-of-sale', [
            'ready' => ProjectRevenues::pointOfSaleReady(),
            'shift' => $shift,
            'project' => $shift !== null ? GlProject::query()->find($shift->project_code) : null,
            'order' => $order,
            'orderLines' => $order?->lines()->get() ?? collect(),
            'orders' => $shift === null ? collect() : RevenueTicket::query()->where('sheet_id', $shift->id)->where('status', RevenueTicket::OPEN)
                ->when($this->service !== '', fn ($query) => $query->where('service', $this->service))->orderBy('number')->get(),
            'archived' => $shift !== null && $this->archive ? RevenueTicket::query()->where('sheet_id', $shift->id)->where('status', RevenueTicket::PAID)->orderByDesc('number')->get() : collect(),
            'openShifts' => $shift !== null ? collect() : RevenueSheet::query()->where('status', RevenueSheet::OPEN)
                ->when(! $this->mayEditOthers(), fn ($query) => $query->where('created_by', (int) $user->getAuthIdentifier()))
                ->when($limit !== null, fn ($query) => $query->whereIn('project_code', $limit ?? []))
                ->orderByDesc('day')->get(),
            'projects' => $shift !== null ? [] : GlProject::query()->where('is_general_account', false)
                ->when($limit !== null, fn ($query) => $query->whereIn('code', $limit ?? []))
                ->orderBy('code')->get()->mapWithKeys(fn (GlProject $project): array => [$project->code => $project->code.' · '.$project->name])->all(),
            'cashAccounts' => $shift !== null ? [] : $vouchers->cashAccounts()->map(fn ($account): string => $account->code.' · '.($arabic ? $account->name_ar : $account->name_en).' ('.$account->currency.')')->all(),
            ...$this->catalog($shift),
            'change' => $order !== null && is_numeric(str_replace(',', '', $this->paid)) ? bcsub($this->numeric($this->paid), $this->numeric((string) $order->net), 2) : null,
            'canSell' => $user->canOnScreen('revenues', ScreenAction::Create),
            'cashier' => $user->name,
        ])->title(__('revenues.pos.title'));
    }

    /**
     * The add-items window: the shift's price list by category, the tiles shown, and the chosen items.
     *
     * @return array<string, mixed>
     */
    private function catalog(?RevenueSheet $shift): array
    {
        if ($shift === null || ! $this->adding) {
            return ['categories' => collect(), 'tiles' => collect(), 'tileTones' => [], 'pendingRows' => []];
        }

        $items = RevenueItem::query()->where('project_code', $shift->project_code)->where('active', true)->where('currency', $shift->currency)
            ->orderBy('category')->orderBy('name')->get();
        $categories = $items->map(fn (RevenueItem $item): string => $this->categoryOf($item))->unique()->values();
        $find = trim($this->find);

        return [
            'categories' => $categories,
            'tiles' => $items
                ->when($this->category !== '', fn (Collection $shown) => $shown->filter(fn (RevenueItem $item): bool => $this->categoryOf($item) === $this->category))
                ->when($find !== '', fn (Collection $shown) => $shown->filter(fn (RevenueItem $item): bool => mb_stripos($item->name, $find) !== false || $item->code === $find))
                ->values(),
            'tileTones' => $categories->flip()->map(fn (int $index): int => $index % 6 + 1)->all(),
            'pendingRows' => collect($this->pending)->map(function (mixed $quantity, int|string $id) use ($items): ?array {
                $item = $items->firstWhere('id', (int) $id);

                return $item instanceof RevenueItem ? ['id' => $item->id, 'name' => $item->name, 'quantity' => (string) $quantity, 'price' => (string) $item->unit_price] : null;
            })->filter()->values()->all(),
        ];
    }

    private function categoryOf(RevenueItem $item): string
    {
        return (string) ($item->category ?: __('revenues.kinds.'.$item->kind));
    }

    private function loadDetails(?RevenueTicket $order): void
    {
        $this->details = $order === null ? [] : [
            'table' => (string) ($order->table_label ?? ''), 'service' => $order->service, 'customer' => (string) ($order->customer ?? ''),
            'persons' => (string) ($order->persons ?? ''), 'payment_method' => $order->payment_method, 'waiter' => (string) ($order->waiter ?? ''),
            'note' => (string) ($order->note ?? ''),
            'discount_percent' => $order->discount_percent !== null ? $this->trimmed((string) $order->discount_percent) : '',
            'discount' => $order->discount_percent === null && bccomp((string) $order->discount, '0', 2) === 1 ? $this->trimmed((string) $order->discount) : '',
        ];
    }

    private function workingShift(): ?RevenueSheet
    {
        if ($this->shiftId === null || ! ProjectRevenues::pointOfSaleReady()) {
            return null;
        }

        $shift = RevenueSheet::query()->where('status', RevenueSheet::OPEN)->find($this->shiftId);

        return $shift !== null && $this->mayWorkOn($shift) ? $shift : null;
    }

    private function workingOrder(): ?RevenueTicket
    {
        $shift = $this->workingShift();

        return $shift === null || $this->orderId === null ? null
            : RevenueTicket::query()->where('sheet_id', $shift->id)->where('status', RevenueTicket::OPEN)->find($this->orderId);
    }

    private function shiftOrFail(int $id): RevenueSheet
    {
        $shift = RevenueSheet::query()->where('status', RevenueSheet::OPEN)->findOrFail($id);
        abort_unless($this->mayWorkOn($shift), 403);

        return $shift;
    }

    private function orderOfShift(int $id): RevenueTicket
    {
        return RevenueTicket::query()->where('sheet_id', $this->shiftOrFail((int) $this->shiftId)->id)->findOrFail($id);
    }

    private function lineOfOrder(int $lineId): RevenueLine
    {
        return RevenueLine::query()->where('ticket_id', $this->orderOfShift((int) $this->orderId)->id)->findOrFail($lineId);
    }

    /** A person works on their own shift; those who may edit revenues on anyone's. Never outside their projects. */
    private function mayWorkOn(RevenueSheet $shift): bool
    {
        return ($shift->created_by === (int) $this->user()->getAuthIdentifier() || $this->mayEditOthers())
            && app(ProjectAccess::class)->canOpen($this->user(), $shift->project_code);
    }

    private function mayEditOthers(): bool
    {
        return $this->user()->canOnScreen('revenues', ScreenAction::Edit);
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

    private function guard(): void
    {
        abort_unless($this->user()->canOnScreen('revenues', ScreenAction::Create), 403);
    }

    private function clearFeedback(): void
    {
        $this->notice = null;
        $this->problems = [];
    }

    private function plus(string $quantity, string $more): string
    {
        return $this->trimmed(bcadd($this->numeric($quantity), $this->numeric($more), 2));
    }

    /** @return numeric-string */
    private function numeric(string $text): string
    {
        $clean = str_replace([',', ' '], '', trim($text));

        return is_numeric($clean) ? (string) $clean : '0';
    }

    /** "2.00" as "2", "1.50" as "1.5". */
    private function trimmed(string $number): string
    {
        return str_contains($number, '.') ? rtrim(rtrim($number, '0'), '.') : $number;
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
