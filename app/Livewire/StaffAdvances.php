<?php

namespace App\Livewire;

use App\Models\GlAccount;
use App\Models\GlBudgetLine;
use App\Models\User;
use App\Services\Entries\Advances;
use App\Services\Entries\CashVouchers;
use App\Services\Entries\EntryException;
use App\Services\Ledger\VoucherAttachments;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Staff advances (finance design of 2026-11-19): the advances still open, with their age; giving a new one (a payment
 * on 1201 for a person and a project); and settling one with its bills, in one voucher (Advances).
 */
#[Layout('components.layouts.app')]
class StaffAdvances extends Component
{
    use WithFileUploads;

    /** '' (the list), 'give' or 'settle'. */
    public string $mode = '';

    public string $giveDate = '';

    public string $giveAccount = '';

    public string $giveRate = '';

    public bool $giveRateTyped = false;

    public string $givePerson = '';

    public string $giveProject = '';

    public string $giveAmount = '';

    public string $givePurpose = '';

    public string $giveRequest = '';

    public string $giveCenter = '';

    public string $settleKey = '';

    public string $settleDate = '';

    /** @var list<array{project: string, budget_line: string, account: string, description: string, amount: string}> */
    public array $bills = [];

    /** 'now': the rest is handed back (or the excess paid out) now; 'open': the rest stays open for bills to come. */
    public string $rest = 'now';

    public string $settleAccount = '';

    public string $settleCenter = '';

    public string $settleNote = '';

    /** @var list<TemporaryUploadedFile> */
    public array $attachments = [];

    public ?string $notice = null;

    /** @var list<string> */
    public array $problems = [];

    public function mount(): void
    {
        Gate::authorize('screen', ['advance', 'view']);
    }

    public function startGive(): void
    {
        Gate::authorize('screen', ['advance', 'create']);

        $this->resetForms();
        $this->mode = 'give';
        $this->giveDate = CarbonImmutable::today()->toDateString();
    }

    public function startSettle(string $key, Advances $advances): void
    {
        Gate::authorize('screen', ['advance', 'create']);

        $advance = collect($advances->open())->firstWhere('key', $key);
        abort_if($advance === null, 404);

        $this->resetForms();
        $this->mode = 'settle';
        $this->settleKey = $key;
        $this->settleDate = CarbonImmutable::today()->toDateString();
        $this->bills = [$this->blankBill($advance['project']), $this->blankBill($advance['project'])];
    }

    public function cancel(): void
    {
        $this->resetForms();
    }

    public function updatedGiveAccount(CashVouchers $cash): void
    {
        $this->refreshGiveRate($cash);
    }

    public function updatedGiveDate(CashVouchers $cash): void
    {
        $this->refreshGiveRate($cash);
    }

    public function updatedGiveRate(): void
    {
        $this->giveRateTyped = trim($this->giveRate) !== '';
    }

    public function addBill(Advances $advances): void
    {
        $project = collect($advances->open())->firstWhere('key', $this->settleKey)['project'] ?? '';
        $this->bills[] = $this->blankBill((string) $project);
    }

    public function removeBill(int $index): void
    {
        unset($this->bills[$index]);
        $this->bills = array_values($this->bills);
    }

    /** A new project clears the budget line; a budget line brings its project and its account from the chart. */
    public function updatedBills(mixed $value, string $key): void
    {
        [$index, $field] = array_pad(explode('.', $key, 2), 2, '');
        $index = (int) $index;

        if (! isset($this->bills[$index])) {
            return;
        }

        if ($field === 'project') {
            $this->bills[$index]['budget_line'] = '';
        }

        if ($field === 'budget_line' && $value !== '') {
            $budgetLine = GlBudgetLine::query()->find((string) $value);

            if ($budgetLine !== null) {
                $this->bills[$index]['project'] = $budgetLine->project_code;
                $this->bills[$index]['account'] = $budgetLine->account_code;
            }
        }
    }

    public function give(Advances $advances, VoucherAttachments $files): void
    {
        Gate::authorize('screen', ['advance', 'create']);
        $this->validate($files->rules(), $files->messages());

        try {
            $voucher = $advances->give([
                'date' => $this->giveDate, 'account' => $this->giveAccount, 'rate' => $this->giveRate, 'person' => $this->givePerson,
                'project' => $this->giveProject, 'amount' => $this->giveAmount, 'purpose' => $this->givePurpose,
                'request' => $this->giveRequest, 'center' => $this->giveCenter,
            ], $this->attachments, $this->user());
        } catch (EntryException $refused) {
            $this->problems = $refused->problems;
            $this->notice = null;

            return;
        }

        $this->resetForms();
        $this->notice = __('advances.given', ['voucher' => $voucher->id]);
    }

    public function settle(Advances $advances, VoucherAttachments $files): void
    {
        Gate::authorize('screen', ['advance', 'create']);
        $this->validate($files->rules(), $files->messages());

        try {
            $voucher = $advances->settle($this->settleKey, $this->settleDate, $this->bills, $this->rest !== 'open', $this->settleAccount, $this->settleCenter, $this->settleNote, $this->attachments, $this->user());
        } catch (EntryException $refused) {
            $this->problems = $refused->problems;
            $this->notice = null;

            return;
        }

        $this->resetForms();
        $this->notice = __('advances.settled', ['voucher' => $voucher->id]);
    }

    public function render(Advances $advances, CashVouchers $cash): View
    {
        $open = $advances->open();
        $arabic = app()->getLocale() === 'ar';
        $cashAccounts = $cash->cashAccounts();
        $label = fn (GlAccount $account): string => $account->code.' · '.($arabic ? $account->name_ar : $account->name_en).' ('.$account->currency.')';
        $projects = $cash->projects()->map(fn ($project): string => $project->code.' · '.$project->name)->all();
        $preview = null;
        $budgetLines = [];
        $chosen = $cashAccounts->get($this->giveAccount);

        if ($this->mode === 'settle') {
            $preview = $advances->preview($this->settleKey, $this->settleDate, $this->bills, $this->rest !== 'open', $this->settleAccount, $this->settleCenter, $this->settleNote);

            foreach (collect($this->bills)->pluck('project')->filter()->unique() as $project) {
                $budgetLines[$project] = $cash->budgetLines((string) $project)->mapWithKeys(fn ($line): array => [$line->key => $line->name])->all();
            }
        }

        $advance = $preview['advance'] ?? null;

        return view('livewire.staff-advances', [
            'open' => $open,
            'totalsUsd' => array_reduce($open, fn (string $total, array $row): string => bcadd($total, $row['open_usd'], 2), '0.00'),
            'canCreate' => Gate::allows('screen', ['advance', 'create']),
            'cashAccounts' => $cashAccounts->map($label)->all(),
            'settleCashAccounts' => $advance !== null ? $cashAccounts->filter(fn (GlAccount $account): bool => $account->currency === $advance['currency'])->map($label)->all() : [],
            'chosen' => $chosen,
            'defaultRate' => $chosen !== null ? $cash->defaultRate((string) $chosen->currency, $this->day($this->giveDate)) : null,
            'giveNeedsCenter' => $chosen !== null && in_array('Center', (array) $chosen->required_tags, true),
            'projects' => $projects,
            'requests' => $this->mode === 'give' ? $cash->openRequests() : collect(),
            'accounts' => $this->mode === 'settle' ? $cash->accountLabels($cash->lineAccounts(CashVouchers::PAYMENT)) : [],
            'budgetLines' => $budgetLines,
            'preview' => $preview,
            'advance' => $advance,
            'figures' => $advance !== null ? array_map(fn (string $amount): string => number_format((float) $amount, 2), [
                'open' => (string) $advance['open'], 'billed' => $preview['billed'], 'back' => $preview['back'], 'extra' => $preview['extra'], 'left' => $preview['left'],
            ]) : [],
        ]);
    }

    /** The month's rate for the chosen account's currency, unless the person typed one. */
    private function refreshGiveRate(CashVouchers $cash): void
    {
        if ($this->giveRateTyped) {
            return;
        }

        $currency = $cash->cashAccounts()->get($this->giveAccount)?->currency;
        $default = $currency !== null ? $cash->defaultRate((string) $currency, $this->day($this->giveDate)) : null;

        $this->giveRate = $default !== null ? rtrim(rtrim($default['rate'], '0'), '.') : '';
    }

    private function resetForms(): void
    {
        $this->reset(
            'mode', 'giveDate', 'giveAccount', 'giveRate', 'giveRateTyped', 'givePerson', 'giveProject', 'giveAmount', 'givePurpose', 'giveRequest', 'giveCenter',
            'settleKey', 'settleDate', 'bills', 'rest', 'settleAccount', 'settleCenter', 'settleNote', 'attachments', 'problems', 'notice',
        );
    }

    /** @return array{project: string, budget_line: string, account: string, description: string, amount: string} */
    private function blankBill(string $project): array
    {
        return ['project' => $project, 'budget_line' => '', 'account' => '', 'description' => '', 'amount' => ''];
    }

    private function day(string $text): CarbonImmutable
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) === 1 ? CarbonImmutable::parse($text) : CarbonImmutable::today();
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
