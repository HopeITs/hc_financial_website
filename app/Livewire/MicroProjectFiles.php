<?php

namespace App\Livewire;

use App\Enums\ScreenAction;
use App\Http\Controllers\MicroFilePrintController;
use App\Models\GlAccount;
use App\Models\GlProject;
use App\Models\MicroItem;
use App\Models\MicroLoan;
use App\Models\MicroPurchase;
use App\Models\MicroQuote;
use App\Models\User;
use App\Services\Loans\InstalmentFunds;
use App\Services\Loans\LoanException;
use App\Services\Loans\MicroFiles;
use App\Services\Loans\MicroOverview;
use App\Services\Loans\MicroPurchases;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Micro-project files (finance decisions of 2026-11-23): a beneficiary's details, purchase list and quotes with no
 * voucher; the payment order and its approvals; the purchases, paid to the merchant or through the purchasing
 * officer's advance; and the receipt protocol that opens the loan. Each step's paper form prints from here.
 */
#[Layout('components.layouts.app')]
class MicroProjectFiles extends Component
{
    use WithFileUploads;

    public const SCAN_RULES = ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'];

    #[Url]
    public string $file = '';

    /** Which files the list shows: those on their way, one step of MicroOverview::STEPS, the cancelled ones, or all. */
    #[Url]
    public string $show = 'open';

    /** A name, project number, national number or mobile to look for. */
    #[Url]
    public string $search = '';

    /** @var array<string, string> */
    public array $form = [];

    /** @var list<array{name: string, kind: string, quantity: string, condition: string}> */
    public array $items = [];

    public string $quoteSupplier = '';

    public string $quoteSource = MicroQuote::MERCHANT;

    public string $quoteDate = '';

    public string $quoteAmount = '';

    public string $quoteCurrency = 'SYP';

    public ?TemporaryUploadedFile $quoteScan = null;

    public string $reason = '';

    public string $extraAmount = '';

    public string $extraReason = '';

    /** @var array<string, string> the invoice form: paid from the cash or from an advance ("advance:ID") */
    public array $invoice = [];

    public ?TemporaryUploadedFile $invoiceScan = null;

    /** @var array<string, string> */
    public array $advance = [];

    /** @var array<int, string> what is given back of each advance */
    public array $returnAmount = [];

    public string $signedOn = '';

    public string $trustBond = '';

    public string $firstDue = '';

    public ?string $notice = null;

    /** @var list<string> */
    public array $problems = [];

    public function mount(): void
    {
        $this->resetInvoice();
        $this->resetAdvance();

        if ($this->file === 'new' && auth()->user() instanceof User && auth()->user()->canOnScreen('microfiles', ScreenAction::Create)) {
            $this->blank();
        } elseif ($this->file !== '' && ($found = MicroLoan::query()->find((int) $this->file)) !== null) {
            $this->load($found);
        } else {
            $this->file = '';
            $this->blank();
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
        $this->guard(ScreenAction::View);
        $this->load(MicroLoan::query()->findOrFail($id));
        $this->clearFeedback();
    }

    public function closeFile(): void
    {
        $this->file = '';
        $this->blank();
        $this->clearFeedback();
    }

    public function addItem(): void
    {
        $this->items[] = ['name' => '', 'kind' => '', 'quantity' => '1', 'condition' => MicroItem::NEW];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    /** The instalment follows the amount until it is typed. */
    public function updatedFormRequestedAmount(MicroFiles $files): void
    {
        $this->proposeInstalment($files);
    }

    public function updatedFormSeizureFee(MicroFiles $files): void
    {
        $this->proposeInstalment($files);
    }

    public function saveFile(MicroFiles $files): void
    {
        $user = $this->guard($this->file === 'new' ? ScreenAction::Create : ScreenAction::Edit);
        $current = $this->current();

        $this->attempt(function () use ($files, $user, $current): void {
            $saved = $files->save($user, $current, $this->form, $this->items);
            $this->load($saved);
            $this->notice = __('micro.saved');
        });
    }

    public function addQuote(MicroFiles $files): void
    {
        $user = $this->guard(ScreenAction::Edit);
        $this->validate(['quoteScan' => self::SCAN_RULES]);
        $file = $this->currentOrFail();

        $this->attempt(function () use ($files, $user, $file): void {
            $files->addQuote($user, $file, $this->quoteSupplier, $this->quoteSource, $this->quoteDate, $this->quoteAmount, $this->quoteCurrency, $this->quoteScan);
            $this->reset('quoteSupplier', 'quoteAmount', 'quoteScan');
            $this->notice = __('micro.quote_added');
        });
    }

    public function removeQuote(int $id, MicroFiles $files): void
    {
        $this->guard(ScreenAction::Edit);
        $this->attempt(fn () => $files->removeQuote($this->currentOrFail(), $id));
    }

    public function chooseQuote(int $id, MicroFiles $files): void
    {
        $this->guard(ScreenAction::Edit);
        $this->attempt(fn () => $files->chooseQuote($this->currentOrFail(), $id));
    }

    public function submit(MicroFiles $files): void
    {
        $this->guard(ScreenAction::Edit);

        $this->attempt(function () use ($files): void {
            $this->load($files->submit($this->currentOrFail()));
            $this->notice = __('micro.submitted');
        });
    }

    public function approve(MicroFiles $files): void
    {
        $user = $this->guard(ScreenAction::Approve);

        $this->attempt(function () use ($files, $user): void {
            $file = $files->approve($user, $this->currentOrFail());
            $this->load($file);
            $this->notice = $file->stage === MicroLoan::APPROVED ? __('micro.approved') : __('micro.approved_step');
        });
    }

    public function sendBack(MicroFiles $files): void
    {
        $user = $this->guard(ScreenAction::Approve);

        $this->attempt(function () use ($files, $user): void {
            $this->load($files->returnForChanges($user, $this->currentOrFail(), $this->reason));
            $this->reason = '';
            $this->notice = __('micro.returned');
        });
    }

    public function cancelFile(MicroFiles $files): void
    {
        $this->guard(ScreenAction::Edit);

        $this->attempt(function () use ($files): void {
            $this->load($files->cancel($this->currentOrFail(), $this->reason));
            $this->reason = '';
            $this->notice = __('micro.cancelled');
        });
    }

    public function saveExtra(MicroPurchases $purchases): void
    {
        $this->guard(ScreenAction::Edit);

        $this->attempt(function () use ($purchases): void {
            $purchases->setExtra($this->currentOrFail(), $this->extraAmount, $this->extraReason);
            $this->notice = __('micro.extra_saved');
        });
    }

    public function payInvoice(MicroPurchases $purchases): void
    {
        $user = $this->guard(ScreenAction::Create);
        $this->validate(['invoiceScan' => self::SCAN_RULES]);
        $file = $this->currentOrFail();
        $form = $this->invoice + ['is_seizure' => ($this->invoice['is_seizure'] ?? '') === '1'];

        $this->attempt(function () use ($purchases, $user, $file, $form): void {
            $source = (string) ($this->invoice['source'] ?? 'cash');
            $saved = str_starts_with($source, 'advance:')
                ? $purchases->invoiceFromAdvance($user, $file, (int) substr($source, 8), $form, $this->invoiceScan)
                : $purchases->payInvoice($user, $file, $form, $this->invoiceScan);

            $this->resetInvoice();
            $this->notice = __('micro.invoice_saved', ['voucher' => $saved->voucher_id]);
        });
    }

    public function giveAdvance(MicroPurchases $purchases): void
    {
        $user = $this->guard(ScreenAction::Create);

        $this->attempt(function () use ($purchases, $user): void {
            $saved = $purchases->giveAdvance($user, $this->currentOrFail(), $this->advance);
            $this->resetAdvance();
            $this->notice = __('micro.advance_saved', ['voucher' => $saved->voucher_id]);
        });
    }

    public function returnAdvance(int $id, MicroPurchases $purchases): void
    {
        $user = $this->guard(ScreenAction::Create);

        $this->attempt(function () use ($purchases, $user, $id): void {
            $saved = $purchases->returnAdvance($user, $this->currentOrFail(), $id, ['date' => $this->invoice['date'] ?? '', 'amount' => $this->returnAmount[$id] ?? '']);
            unset($this->returnAmount[$id]);
            $this->notice = __('micro.return_saved', ['voucher' => $saved->voucher_id]);
        });
    }

    public function removePurchase(int $id, MicroPurchases $purchases): void
    {
        $user = $this->guard(ScreenAction::Edit);

        $this->attempt(function () use ($purchases, $user, $id): void {
            $purchases->remove($user, $this->currentOrFail(), $id, $this->reason !== '' ? $this->reason : __('micro.removed_reason'));
            $this->notice = __('micro.removed');
        });
    }

    public function close(MicroPurchases $purchases): void
    {
        $user = $this->guard(ScreenAction::Create);

        $this->attempt(function () use ($purchases, $user): void {
            $file = $purchases->close($user, $this->currentOrFail(), ['signed_on' => $this->signedOn, 'trust_bond' => $this->trustBond, 'first_due' => $this->firstDue]);
            $this->load($file);
            $this->notice = __('micro.closed', ['voucher' => $file->closing_voucher_id ?? '—']);
        });
    }

    /** The Excel tools saved files: show them with their message. */
    #[On('excel-imported')]
    public function excelImported(string $message): void
    {
        $this->clearFeedback();
        $this->notice = $message;
    }

    /** The first instalment proposed two months after the signature. */
    public function updatedSignedOn(): void
    {
        $day = $this->day($this->signedOn);
        if ($day !== null) {
            $this->firstDue = $day->addMonthsNoOverflow(MicroPurchases::FIRST_DUE_MONTHS)->toDateString();
        }
    }

    public function render(MicroFiles $files, MicroPurchases $purchases, InstalmentFunds $funds, MicroOverview $overview): View
    {
        $user = $this->guard(ScreenAction::View);
        $current = $this->current();
        $stages = match ($this->show) {
            'draft' => [MicroLoan::DRAFT, MicroLoan::RETURNED],
            'submitted' => [MicroLoan::SUBMITTED],
            'approved', 'ready' => [MicroLoan::APPROVED],
            'active' => [MicroLoan::ACTIVE],
            'cancelled' => [MicroLoan::CANCELLED],
            'all' => null,
            default => [MicroLoan::DRAFT, MicroLoan::SUBMITTED, MicroLoan::RETURNED, MicroLoan::APPROVED],
        };
        $search = trim($this->search);

        $list = MicroLoan::query()->whereNotNull('requested_amount')
            ->when($stages !== null, fn ($query) => $query->whereIn('stage', $stages))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('beneficiary', 'like', '%'.$search.'%')->orWhere('name_en', 'like', '%'.$search.'%')
                ->orWhere('project_number', 'like', '%'.$search.'%')->orWhere('national_number', 'like', '%'.$search.'%')
                ->orWhere('mobile', 'like', '%'.$search.'%')))
            ->orderByDesc('id')->limit(200)->get()
            // The purchases step and the protocol step are both approved files: the protocol's have their invoices booked.
            ->when(in_array($this->show, ['approved', 'ready'], true), fn ($rows) => $rows->filter(fn (MicroLoan $file): bool => $overview->stepOf($file) === $this->show)->values());

        $totals = $current !== null ? $purchases->totals($current) : null;
        $advances = $current !== null
            ? $current->purchases()->where('kind', MicroPurchase::ADVANCE)->get()->map(fn (MicroPurchase $advance): array => [
                'advance' => $advance, 'open' => $purchases->openOn($advance), 'open_label' => number_format((float) $purchases->openOn($advance), 2),
            ])
            : collect();
        $amount = fn (mixed $value, int $decimals = 2): string => number_format((float) $value, $decimals);

        return view('livewire.micro-project-files', [
            'list' => $list,
            'current' => $current,
            'editable' => $current === null ? $this->file === 'new' : in_array($current->stage, MicroLoan::EDITABLE, true),
            'canEdit' => $user->canOnScreen('microfiles', ScreenAction::Edit) || $user->canOnScreen('microfiles', ScreenAction::Create),
            'canApprove' => $current !== null && $user->canOnScreen('microfiles', ScreenAction::Approve) && $files->mayApprove($user, $current),
            'nextRole' => $current !== null ? $files->nextRole($current) : null,
            'usdValue' => $current !== null ? $files->usdValue($current) : null,
            'needsSeizure' => $current !== null && $files->needsSeizureFee($current),
            'totals' => $totals,
            // The figures the tiles and sentences show, formatted here (no number_format in the view).
            'figures' => $current === null ? [] : [
                'order_total' => $amount(bcadd(bcadd((string) ($current->requested_amount ?? '0'), (string) $current->seizure_fee, 2), (string) $current->extra_amount, 2)),
                'usd_value' => $amount($files->usdValue($current)),
                'instalment' => $amount($current->instalment_amount),
                'approved' => $amount($totals['approved'] ?? 0),
                'invoiced' => $amount($totals['invoiced'] ?? 0),
                'invoiced_plain' => Money::normalize((string) ($totals['invoiced'] ?? '0')),
                'share' => ($totals['share'] ?? null) !== null ? $amount($totals['share'], 1).' %' : '—',
                'over_ten' => ($totals['share'] ?? null) !== null && abs((float) $totals['share']) > 10 ? ((float) $totals['share'] > 0 ? 'over' : 'under') : null,
                'open_advances' => $amount($totals['open_advances'] ?? 0),
                'loan' => $amount($current->loan_amount),
                'grant' => $amount($current->grant_amount),
                'recovery' => rtrim(rtrim((string) $current->recovery_percent, '0'), '.').' %',
            ],
            'currencies' => MicroFiles::CURRENCIES,
            // The step bar of the open file: the steps done, the one it is at, and those to come.
            'currentStep' => $current !== null ? $overview->stepOf($current) : ($this->file === 'new' ? 'draft' : null),
            'extraNeedsEmail' => $current !== null && $purchases->extraNeedsEmail($current),
            'advances' => $advances,
            'fundings' => $this->fundings($funds),
            'cashAccounts' => $current !== null ? $this->cashAccounts($current) : [],
            'purchaseRows' => $current?->purchases()->with('voucher')->get() ?? collect(),
            'forms' => MicroFilePrintController::FORMS,
        ]);
    }

    private function load(MicroLoan $file): void
    {
        $this->file = (string) $file->id;
        $trim = fn (?string $value, int $scale = 2): string => $value === null ? '' : rtrim(rtrim(Money::normalize($value, $scale), '0'), '.');

        $this->form = [
            'beneficiary' => $file->beneficiary, 'name_en' => (string) $file->name_en, 'national_number' => (string) $file->national_number,
            'mobile' => (string) $file->mobile, 'partners' => (string) $file->partners, 'project_number' => (string) $file->project_number,
            'profession' => (string) $file->profession, 'address' => (string) $file->address,
            'funding' => $file->project_code !== null ? 'project:'.$file->project_code : ($file->funder !== null ? 'fund:'.$file->funder.'|'.$file->city : ''),
            'currency' => $file->currency, 'rate' => $trim($file->rate, 6), 'requested_amount' => $trim($file->requested_amount),
            'seizure_fee' => $trim($file->seizure_fee), 'recovery_percent' => $trim($file->recovery_percent), 'instalment_amount' => $trim($file->instalment_amount),
            'interval' => (string) $file->payment_interval_months,
        ];
        $this->items = $file->items()->get()->map(fn (MicroItem $item): array => [
            'name' => $item->name, 'kind' => (string) $item->kind, 'quantity' => $trim($item->quantity), 'condition' => $item->condition,
        ])->all();
        if ($this->items === []) {
            $this->addItem();
        }

        $this->extraAmount = $trim($file->extra_amount);
        $this->extraReason = (string) $file->extra_reason;
        $this->quoteCurrency = $file->currency;
        $this->trustBond = '';
        $this->signedOn = '';
        $this->firstDue = '';
    }

    private function blank(): void
    {
        $this->form = [
            'beneficiary' => '', 'name_en' => '', 'national_number' => '', 'mobile' => '', 'partners' => '', 'project_number' => '', 'profession' => '',
            'address' => '', 'funding' => '', 'currency' => 'USD', 'rate' => '', 'requested_amount' => '', 'seizure_fee' => '', 'recovery_percent' => '50',
            'instalment_amount' => '', 'interval' => '1',
        ];
        $this->items = [];
        $this->addItem();
    }

    private function proposeInstalment(MicroFiles $files): void
    {
        $requested = str_replace([',', ' '], '', $this->form['requested_amount'] ?? '');
        $seizure = str_replace([',', ' '], '', $this->form['seizure_fee'] ?? '');

        if (is_numeric($requested) && ($seizure === '' || is_numeric($seizure))) {
            $total = bcadd(Money::normalize($requested), Money::normalize($seizure === '' ? '0' : $seizure), 2);
            $this->form['instalment_amount'] = rtrim(rtrim($files->defaultInstalment($total), '0'), '.');
        }
    }

    private function resetInvoice(): void
    {
        $this->invoice = [
            'source' => 'cash', 'date' => now()->toDateString(), 'cash_account' => '', 'rate' => '', 'amount' => '', 'merchant' => '', 'number' => '',
            'description_ar' => '', 'description_en' => '', 'is_seizure' => '0',
        ];
        $this->invoiceScan = null;
    }

    private function resetAdvance(): void
    {
        $this->advance = ['date' => now()->toDateString(), 'cash_account' => '', 'rate' => '', 'amount' => '', 'person' => ''];
    }

    /** @return array<string, string> "project:CODE" or "fund:FUNDER|CITY" => label */
    private function fundings(InstalmentFunds $funds): array
    {
        $choices = [];

        foreach (GlProject::query()->where('is_general_account', false)->orderBy('code')->get(['code', 'name', 'donor']) as $project) {
            $choices['project:'.$project->code] = $project->code.' · '.$project->name;
        }

        foreach ($funds->choices(CarbonImmutable::today()) as $key => $label) {
            $choices['fund:'.$key] = __('loans.from_fund', ['fund' => $label]);
        }

        if (($this->form['funding'] ?? '') !== '' && ! isset($choices[$this->form['funding']])) {
            $choices[$this->form['funding']] = str_replace(['project:', 'fund:', '|'], ['', '', ' · '], $this->form['funding']);
        }

        return $choices;
    }

    /** @return array<string, string> the cash accounts a file's purchases are paid from */
    private function cashAccounts(MicroLoan $file): array
    {
        $arabic = app()->getLocale() === 'ar';

        return GlAccount::query()->where('is_cash', true)->where('is_active', true)
            ->whereIn('currency', $file->currency === 'USD' ? ['USD', 'SYP'] : ['SYP'])
            ->when($file->project_code === null, fn ($query) => $query->where('city', $file->city))
            ->whereRaw('code not like ?', ['11_3'])->whereRaw('code not like ?', ['11_4'])
            ->orderBy('code')->get()
            ->mapWithKeys(fn (GlAccount $account): array => [$account->code => $account->code.' · '.($arabic ? $account->name_ar : $account->name_en)])->all();
    }

    private function current(): ?MicroLoan
    {
        return ctype_digit($this->file) ? MicroLoan::query()->with(['items', 'quotes', 'project'])->find((int) $this->file) : null;
    }

    private function currentOrFail(): MicroLoan
    {
        $file = $this->current();
        abort_if($file === null, 404);

        return $file;
    }

    /** Runs a step, showing what refused it. */
    private function attempt(callable $step): void
    {
        $this->clearFeedback();

        try {
            $step();
        } catch (LoanException $refused) {
            $this->problems = $refused->problems;
        }
    }

    private function clearFeedback(): void
    {
        $this->notice = null;
        $this->problems = [];
    }

    private function guard(ScreenAction $action): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->canOnScreen('microfiles', $action), 403);

        return $user;
    }

    private function day(string $text): ?CarbonImmutable
    {
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) === 1 ? CarbonImmutable::createFromFormat('!Y-m-d', $text) : false;

        return $day instanceof CarbonImmutable && $day->format('Y-m-d') === $text ? $day : null;
    }
}
