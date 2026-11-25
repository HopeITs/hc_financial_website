<?php

namespace App\Livewire;

use App\Enums\ScreenAction;
use App\Models\GlBudgetLine;
use App\Models\LedgerRequest;
use App\Models\Purchase;
use App\Models\User;
use App\Services\Entries\CashVouchers;
use App\Services\Entries\EntryException;
use App\Services\Ledger\VoucherAttachments;
use App\Services\Projects\ProjectAccess;
use App\Services\Purchasing\PurchaseFiles;
use App\Services\Purchasing\PurchasePayments;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Purchase files (finance request of 2026-11-24): the procurement cycle on one page, step by step: the purchase request
 * on an approved request, the suppliers' quotes and their comparison, the purchase order and its approvals, the goods
 * or service received with the invoice, and the payment. Each step's form prints from here.
 */
#[Layout('components.layouts.app')]
class Purchases extends Component
{
    use WithFileUploads;

    public const SCAN_RULES = ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'];

    /** The open file's id, `new` for the form of a new one, or '' for the list only. */
    #[Url]
    public string $file = '';

    /** Which files the list shows: those on their way, one stage, the cancelled ones, or all. */
    #[Url]
    public string $show = 'open';

    /** @var array<string, string> */
    public array $form = [];

    /** @var list<array<string, string>> */
    public array $items = [];

    /** @var array<string, string> */
    public array $quote = [];

    /** @var array<int|string, array{price: string, brand: string}> */
    public array $quotePrices = [];

    public ?TemporaryUploadedFile $quoteScan = null;

    public string $awardQuote = '';

    public string $awardReason = '';

    public string $otherCharges = '';

    public string $returnReason = '';

    /** @var array<string, mixed> */
    public array $receipt = [];

    /** @var array<int|string, array<string, string>> */
    public array $received = [];

    /** @var array<string, string> */
    public array $pay = [];

    /** @var list<TemporaryUploadedFile> */
    public array $payFiles = [];

    public ?string $notice = null;

    /** @var list<string> */
    public array $problems = [];

    public function mount(): void
    {
        $this->blank();

        if ($this->file === 'new' && $this->user()->canOnScreen('purchases', ScreenAction::Create)) {
            return;
        }

        $found = ctype_digit($this->file) ? $this->visible()->find((int) $this->file) : null;

        if ($found !== null) {
            $this->load($found);
        } else {
            $this->file = '';
        }
    }

    public function newFile(): void
    {
        $this->guard(ScreenAction::Create);
        $this->file = 'new';
        $this->blank();
        $this->clearFeedback();
    }

    public function select(int $id): void
    {
        $found = $this->visible()->find($id);

        if ($found !== null) {
            $this->file = (string) $found->id;
            $this->load($found);
            $this->clearFeedback();
        }
    }

    public function closeFile(): void
    {
        $this->file = '';
        $this->blank();
        $this->clearFeedback();
    }

    public function addItem(): void
    {
        $this->items[] = $this->blankItem();
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items) ?: [$this->blankItem()];
    }

    /** Saves the purchase request: a new file on an approved request, or the open file's details. */
    public function save(PurchaseFiles $files): void
    {
        $this->clearFeedback();

        try {
            if ($this->file === 'new') {
                $this->guard(ScreenAction::Create);
                $purchase = $files->create($this->user(), $this->form, $this->items);
                $this->notice = __('purchases.saved_new', ['number' => $purchase->number('PR')]);
            } else {
                $this->guard(ScreenAction::Edit);
                $purchase = $files->update($this->current() ?? abort(404), $this->form, $this->items);
                $this->notice = __('purchases.saved');
            }
        } catch (EntryException $refused) {
            $this->problems = $refused->problems;

            return;
        }

        $this->file = (string) $purchase->id;
        $this->load($purchase);
    }

    public function addQuote(PurchaseFiles $files): void
    {
        $this->guard(ScreenAction::Edit);
        $this->clearFeedback();
        $this->validate(['quoteScan' => self::SCAN_RULES]);

        try {
            $files->addQuote($this->user(), $this->current() ?? abort(404), $this->quote, $this->quotePrices, $this->quoteScan);
        } catch (EntryException $refused) {
            $this->problems = $refused->problems;

            return;
        }

        $this->notice = __('purchases.quote_added');
        $this->load($this->current() ?? abort(404));
    }

    public function removeQuote(PurchaseFiles $files, int $quoteId): void
    {
        $this->guard(ScreenAction::Edit);
        $this->clearFeedback();

        try {
            $files->removeQuote($this->current() ?? abort(404), $quoteId);
        } catch (EntryException $refused) {
            $this->problems = $refused->problems;

            return;
        }

        $this->load($this->current() ?? abort(404));
    }

    /** Awards the chosen supplier and sends the purchase order for approval. */
    public function submitOrder(PurchaseFiles $files): void
    {
        $this->guard(ScreenAction::Edit);
        $this->clearFeedback();

        try {
            $files->submitOrder($this->current() ?? abort(404), (int) $this->awardQuote, $this->awardReason, $this->otherCharges);
        } catch (EntryException $refused) {
            $this->problems = $refused->problems;

            return;
        }

        $this->notice = __('purchases.order_submitted');
        $this->load($this->current() ?? abort(404));
    }

    public function approve(PurchaseFiles $files): void
    {
        $this->guard(ScreenAction::Approve);
        $this->clearFeedback();

        try {
            $purchase = $files->approve($this->user(), $this->current() ?? abort(404));
        } catch (EntryException $refused) {
            $this->problems = $refused->problems;

            return;
        }

        $this->notice = $purchase->stage === Purchase::ORDERED ? __('purchases.order_approved') : __('purchases.approval_recorded');
        $this->load($purchase);
    }

    public function returnOrder(PurchaseFiles $files): void
    {
        $this->guard(ScreenAction::Approve);
        $this->clearFeedback();

        try {
            $purchase = $files->returnForChanges($this->user(), $this->current() ?? abort(404), $this->returnReason);
        } catch (EntryException $refused) {
            $this->problems = $refused->problems;

            return;
        }

        $this->notice = __('purchases.order_returned');
        $this->load($purchase);
    }

    /** Records the goods or service received and the supplier's invoice. */
    public function receive(PurchaseFiles $files): void
    {
        $this->guard(ScreenAction::Edit);
        $this->clearFeedback();

        try {
            $purchase = $files->receive($this->current() ?? abort(404), [...$this->receipt, 'accepted' => ($this->receipt['accepted'] ?? '') === 'yes'], $this->received);
        } catch (EntryException $refused) {
            $this->problems = $refused->problems;

            return;
        }

        $this->notice = __('purchases.received_saved');
        $this->load($purchase);
    }

    /** Choosing the cash account brings the month's rate of its currency. */
    public function updatedPay(mixed $value, string $key): void
    {
        if ($key !== 'account') {
            return;
        }

        $vouchers = app(CashVouchers::class);
        $currency = $vouchers->cashAccounts()->get($this->pay['account'] ?? '')?->currency;
        $default = $currency !== null ? $vouchers->defaultRate((string) $currency, CarbonImmutable::today()) : null;
        $this->pay['rate'] = $default !== null ? rtrim(rtrim($default['rate'], '0'), '.') : '';
    }

    public function payFile(PurchasePayments $payments, VoucherAttachments $attachments): void
    {
        $this->clearFeedback();
        abort_unless($this->mayPay(), 403);
        $this->validate($attachments->rules('payFiles'), $attachments->messages('payFiles'));

        try {
            $voucher = $payments->pay($this->user(), $this->current() ?? abort(404), $this->pay, $this->payFiles);
        } catch (EntryException $refused) {
            $this->problems = $refused->problems;

            return;
        }

        $this->payFiles = [];
        $this->notice = __('purchases.paid', ['voucher' => $voucher->id]);
        $this->load($this->current() ?? abort(404));
    }

    public function cancel(PurchaseFiles $files): void
    {
        $this->guard(ScreenAction::Edit);
        $this->clearFeedback();

        try {
            $purchase = $files->cancel($this->current() ?? abort(404));
        } catch (EntryException $refused) {
            $this->problems = $refused->problems;

            return;
        }

        $this->notice = __('purchases.cancelled');
        $this->load($purchase);
    }

    public function render(PurchaseFiles $files, PurchasePayments $payments, CashVouchers $vouchers): View
    {
        $user = $this->user();
        $current = $this->current();
        $current?->load(['items.budgetLine', 'request', 'project', 'awardedQuote', 'voucher']);
        $editable = $current === null ? $this->file === 'new' : $current->stage === Purchase::SOURCING;
        $projectCode = $current?->project_code ?? LedgerRequest::query()->find((int) ($this->form['request'] ?? 0))?->project_code;

        return view('livewire.purchases', [
            'list' => $this->listing(),
            'current' => $current,
            'editable' => $editable,
            'canEdit' => $user->canOnScreen('purchases', $this->file === 'new' ? ScreenAction::Create : ScreenAction::Edit),
            'canCreate' => $user->canOnScreen('purchases', ScreenAction::Create),
            'mayApprove' => $current !== null && $user->canOnScreen('purchases', ScreenAction::Approve) && $files->mayApprove($user, $current),
            'nextRole' => $current !== null ? $files->nextRole($current) : null,
            'mayPay' => $current !== null && $this->mayPay(),
            'requests' => $this->requestChoices($files),
            'budgetLines' => $projectCode !== null ? GlBudgetLine::query()->where('project_code', $projectCode)->orderBy('key')->get()->mapWithKeys(fn (GlBudgetLine $line): array => [$line->key => $line->name])->all() : [],
            'quotesNeeded' => $current !== null ? $files->quotesNeeded($current) : null,
            'estimated' => $current !== null ? $files->estimatedTotal($current) : null,
            'comparison' => $current !== null ? $files->comparison($current) : ['quotes' => [], 'lowest' => null],
            'orderLines' => $current !== null && $current->awarded_quote_id !== null ? $files->orderLines($current) : [],
            'orderTotal' => $current !== null && $current->awarded_quote_id !== null ? $files->orderTotal($current) : null,
            'advances' => $current !== null && $current->stage === Purchase::RECEIVED ? $payments->advancesFor($current) : [],
            'cashAccounts' => $current !== null && $current->stage === Purchase::RECEIVED ? $vouchers->cashAccounts()->filter(fn ($account): bool => $account->currency === $current->currency)->map(fn ($account): string => $account->code.' · '.(app()->getLocale() === 'ar' ? $account->name_ar : $account->name_en))->all() : [],
            'forms' => $current !== null ? $this->formsOf($current) : [],
        ]);
    }

    /** @return list<string> the forms of the file that can be printed at its stage */
    private function formsOf(Purchase $purchase): array
    {
        $forms = ['pr', 'rfq'];

        if ($purchase->quotes()->exists()) {
            $forms[] = 'cba';
        }

        if ($purchase->awarded_quote_id !== null && $purchase->stage !== Purchase::SOURCING) {
            $forms[] = 'po';
        }

        if ($purchase->received_on !== null) {
            $forms[] = $purchase->kind === Purchase::SERVICE ? 'srn' : 'grn';
            $forms[] = 'rdp';
        }

        return $forms;
    }

    /** Whether the person may pay: they make payment vouchers, or settle advances, and see purchase files. */
    private function mayPay(): bool
    {
        $user = $this->user();

        return $user->canOnScreen('purchases', ScreenAction::View)
            && ($user->canOnScreen('single', ScreenAction::Create) || $user->canOnScreen('advance', ScreenAction::Create));
    }

    /** @return Collection<int, Purchase> */
    private function listing(): Collection
    {
        return $this->visible()
            ->with(['project', 'awardedQuote'])
            ->when($this->show === 'open', fn (Builder $query) => $query->whereNotIn('stage', [Purchase::PAID, Purchase::CANCELLED]))
            ->when(in_array($this->show, [...Purchase::STAGES, Purchase::CANCELLED], true), fn (Builder $query) => $query->where('stage', $this->show))
            ->orderByDesc('id')->limit(200)->get();
    }

    /** @return Builder<Purchase> the files of the projects the person may see */
    private function visible(): Builder
    {
        $limit = app(ProjectAccess::class)->limitedTo($this->user());

        return Purchase::query()->when($limit !== null, fn (Builder $query) => $query->whereIn('project_code', $limit ?? []));
    }

    /** @return array<int, string> the approved requests of the person's projects: id => label */
    private function requestChoices(PurchaseFiles $files): array
    {
        $limit = app(ProjectAccess::class)->limitedTo($this->user());
        $codes = $limit;
        $choices = [];

        foreach ($files->openRequests() as $request) {
            if ($codes === null || in_array($request->project_code, $codes, true)) {
                $choices[$request->id] = '#'.$request->id.' · '.$request->project_code.' · '.($request->description ?? __('requests.type.'.$request->type)).' · '.Money::normalize($request->amount).' USD';
            }
        }

        return $choices;
    }

    private function current(): ?Purchase
    {
        return ctype_digit($this->file) ? $this->visible()->find((int) $this->file) : null;
    }

    private function load(Purchase $purchase): void
    {
        $purchase->load('items');
        $this->form = [
            'request' => (string) $purchase->request_id, 'kind' => $purchase->kind, 'title' => $purchase->title,
            'location' => (string) $purchase->location, 'delivery_place' => (string) $purchase->delivery_place,
            'needed_by' => (string) $purchase->needed_by?->toDateString(), 'currency' => $purchase->currency,
        ];
        $this->items = $purchase->items->map(fn ($item): array => [
            'budget_line' => $item->budget_line_key, 'description' => $item->description, 'unit' => (string) $item->unit,
            'quantity' => rtrim(rtrim((string) $item->quantity, '0'), '.'), 'estimated_price' => (string) $item->estimated_price, 'remarks' => (string) $item->remarks,
        ])->all() ?: [$this->blankItem()];

        $this->quote = ['supplier' => '', 'address' => '', 'phone' => '', 'quoted_on' => CarbonImmutable::today()->toDateString(), 'valid_for' => ''];
        $this->quotePrices = $purchase->items->mapWithKeys(fn ($item): array => [$item->id => ['price' => '', 'brand' => '']])->all();
        $this->quoteScan = null;
        $this->awardQuote = (string) ($purchase->awarded_quote_id ?? '');
        $this->awardReason = (string) ($purchase->award_reason ?? __('purchases.default_reason'));
        $this->otherCharges = bccomp(Money::normalize($purchase->other_charges), '0', 2) === 0 ? '' : Money::normalize($purchase->other_charges);
        $this->returnReason = '';

        $this->receipt = [
            'received_on' => (string) ($purchase->received_on?->toDateString() ?? CarbonImmutable::today()->toDateString()),
            'receiving_location' => (string) ($purchase->receiving_location ?? $purchase->delivery_place),
            'invoice_number' => (string) $purchase->invoice_number, 'invoice_date' => (string) $purchase->invoice_date?->toDateString(),
            'invoice_amount' => (string) ($purchase->invoice_amount ?? ''), 'accepted' => $purchase->accepted === false ? 'no' : 'yes',
            'remarks' => (string) $purchase->receipt_remarks,
        ];
        $this->received = $purchase->items->mapWithKeys(fn ($item): array => [$item->id => [
            'received_quantity' => rtrim(rtrim((string) ($item->received_quantity ?? $item->quantity), '0'), '.'), 'condition' => (string) $item->condition,
            'expected_on' => (string) $item->expected_on?->toDateString(), 'completed_on' => (string) $item->completed_on?->toDateString(),
        ]])->all();

        $this->pay = ['method' => Purchase::CASH, 'date' => CarbonImmutable::today()->toDateString(), 'account' => '', 'rate' => '', 'center' => '', 'advance' => ''];
    }

    private function blank(): void
    {
        $this->form = ['request' => '', 'kind' => Purchase::GOODS, 'title' => '', 'location' => '', 'delivery_place' => '', 'needed_by' => '', 'currency' => 'USD'];
        $this->items = [$this->blankItem()];
        $this->quote = [];
        $this->quotePrices = [];
        $this->receipt = [];
        $this->received = [];
        $this->pay = [];
    }

    /** @return array<string, string> */
    private function blankItem(): array
    {
        return ['budget_line' => '', 'description' => '', 'unit' => '', 'quantity' => '1', 'estimated_price' => '', 'remarks' => ''];
    }

    private function clearFeedback(): void
    {
        $this->notice = null;
        $this->problems = [];
    }

    private function guard(ScreenAction $action): void
    {
        abort_unless($this->user()->canOnScreen('purchases', $action), 403);
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
