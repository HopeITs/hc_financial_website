<?php

namespace App\Livewire;

use App\Models\GlAccount;
use App\Models\GlBudgetLine;
use App\Models\User;
use App\Services\Entries\CashVouchers;
use App\Services\Entries\EntryException;
use App\Services\Ledger\VoucherAttachments;
use App\Support\Navigation;
use App\Support\UserPreferences;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * The payment and the receipt voucher screens (finance design of 2026-11-19): a header and a grid of lines, the
 * totals in the voucher's currency and in dollars as you type, soft warnings (budget used, project ended), and the
 * invoice attached. Choosing a budget line brings its account from the chart; the rate is the month's unless changed.
 * The voucher is saved as pending approval. PaymentVoucher and ReceiptVoucher say which one it is.
 */
#[Layout('components.layouts.app')]
abstract class CashVoucherForm extends Component
{
    use WithFileUploads;

    public string $date = '';

    public string $account = '';

    public string $rate = '';

    /** Whether the person typed the rate: then a new date or account does not replace it. */
    public bool $rateTyped = false;

    public string $party = '';

    public string $reference = '';

    public string $center = '';

    public string $description = '';

    public string $request = '';

    /** @var list<array{project: string, budget_line: string, account: string, description: string, amount: string}> */
    public array $lines = [];

    /** @var list<TemporaryUploadedFile> */
    public array $attachments = [];

    public ?string $notice = null;

    public ?string $savedVoucher = null;

    /** @var list<string> */
    public array $problems = [];

    /**
     * The voucher saved last on this screen, to enter it again with one click (finance request of 2026-11-24).
     *
     * @var array{party?: string, center?: string, description?: string, lines?: list<array{project: string, budget_line: string, account: string, description: string, amount: string}>}
     */
    public array $lastSaved = [];

    /** CashVouchers::PAYMENT or CashVouchers::RECEIPT. */
    abstract protected function kind(): string;

    /** The permission slug of the screen. */
    abstract protected function screen(): string;

    public function mount(): void
    {
        Gate::authorize('screen', [$this->screen(), 'view']);

        $this->date = CarbonImmutable::today()->toDateString();
        $this->rememberedDefaults(app(CashVouchers::class));
    }

    /** Fills the form again with the voucher saved last: its party, lines and amounts; not its number, request or files. */
    public function repeatLast(): void
    {
        if ($this->lastSaved === []) {
            return;
        }

        $this->party = (string) ($this->lastSaved['party'] ?? '');
        $this->center = (string) ($this->lastSaved['center'] ?? '');
        $this->description = (string) ($this->lastSaved['description'] ?? '');
        $this->lines = array_values((array) ($this->lastSaved['lines'] ?? [])) ?: [$this->blankLine()];
        $this->notice = null;
        $this->savedVoucher = null;
    }

    public function addLine(): void
    {
        $this->lines[] = $this->blankLine();
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);

        if ($this->lines === []) {
            $this->lines = [$this->blankLine()];
        }
    }

    public function updatedAccount(CashVouchers $vouchers): void
    {
        $this->refreshRate($vouchers);
    }

    public function updatedDate(CashVouchers $vouchers): void
    {
        $this->refreshRate($vouchers);
    }

    public function updatedRate(): void
    {
        $this->rateTyped = trim($this->rate) !== '';
    }

    /** A new project clears the budget line; a budget line brings its project and its account from the chart. */
    public function updatedLines(mixed $value, string $key): void
    {
        [$index, $field] = array_pad(explode('.', $key, 2), 2, '');
        $index = (int) $index;

        if (! isset($this->lines[$index])) {
            return;
        }

        if ($field === 'project') {
            $this->lines[$index]['budget_line'] = '';
        }

        if ($field === 'budget_line' && $value !== '') {
            $budgetLine = GlBudgetLine::query()->find((string) $value);

            if ($budgetLine !== null) {
                $this->lines[$index]['project'] = $budgetLine->project_code;
                $this->lines[$index]['account'] = $budgetLine->account_code;
            }
        }
    }

    /** Choosing an approved request fills the grid from its lines. */
    public function updatedRequest(CashVouchers $vouchers): void
    {
        if ($this->request === '' || ! ctype_digit($this->request)) {
            return;
        }

        $currency = $vouchers->cashAccounts()->get($this->account)?->currency ?? 'USD';
        $lines = $vouchers->linesFromRequest((int) $this->request, (string) $currency, $currency === 'USD' ? '1' : $this->rate);

        if ($lines !== []) {
            $this->lines = $lines;
        }
    }

    public function save(CashVouchers $vouchers, VoucherAttachments $files): void
    {
        Gate::authorize('screen', [$this->screen(), 'create']);
        $this->validate($files->rules(), $files->messages());

        try {
            $voucher = $vouchers->save($this->kind(), $this->header(), $this->lines, $this->attachments, $this->user());
        } catch (EntryException $refused) {
            $this->problems = $refused->problems;
            $this->notice = null;

            return;
        }

        $this->lastSaved = [
            'party' => $this->party, 'center' => $this->center, 'description' => $this->description,
            'lines' => array_values(array_filter($this->lines, fn (array $line): bool => trim($line['amount'].$line['account'].$line['budget_line']) !== '')),
        ];
        $this->remember();

        // The cash account and the project stay for the next voucher: most vouchers in a row share them.
        $this->reset('rate', 'rateTyped', 'party', 'reference', 'center', 'description', 'request', 'attachments', 'problems');
        $this->rememberedDefaults($vouchers);
        $this->savedVoucher = $voucher->id;
        $this->notice = __('entries.saved', ['voucher' => $voucher->id]);
    }

    public function render(CashVouchers $vouchers): View
    {
        $kind = $this->kind();
        $cash = $vouchers->cashAccounts();
        $chosen = $cash->get($this->account);
        $lineAccounts = $vouchers->lineAccounts($kind);
        $projects = $vouchers->projects();
        $arabic = app()->getLocale() === 'ar';
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date) === 1 ? CarbonImmutable::parse($this->date) : CarbonImmutable::today();
        $built = $vouchers->build($kind, $this->header(), $this->lines);
        $budgetLines = [];

        foreach (collect($this->lines)->pluck('project')->filter()->unique() as $project) {
            $budgetLines[$project] = $vouchers->budgetLines((string) $project)->mapWithKeys(fn ($line): array => [$line->key => $line->name])->all();
        }

        return view('livewire.cash-voucher-form', [
            'kind' => $kind,
            'title' => __('entries.title.'.$kind),
            'cashAccounts' => $cash->map(fn (GlAccount $account): string => $account->code.' · '.($arabic ? $account->name_ar : $account->name_en).' ('.$account->currency.')')->all(),
            'chosen' => $chosen,
            'needsCenter' => collect([$chosen, ...collect($this->lines)->map(fn (array $line) => $lineAccounts->get($line['account']))->all()])
                ->filter()->contains(fn (GlAccount $account): bool => in_array('Center', (array) $account->required_tags, true)),
            'defaultRate' => $chosen !== null ? $vouchers->defaultRate((string) $chosen->currency, $day) : null,
            'accounts' => $vouchers->accountLabels($lineAccounts),
            'projects' => $projects->map(fn ($project): string => $project->code.' · '.$project->name)->all(),
            'budgetLines' => $budgetLines,
            'requests' => $kind === CashVouchers::PAYMENT ? $vouchers->openRequests() : collect(),
            'total' => $built['total'],
            'totalUsd' => $built['total_usd'],
            'currency' => $built['currency'],
            'warnings' => $built['warnings'],
            'canCreate' => Gate::allows('screen', [$this->screen(), 'create']),
            'listUrl' => route(Navigation::routeName('vouchers')),
        ]);
    }

    /** @return array{date: string, account: string, rate: string, party: string, reference: string, center: string, description: string, request: string} */
    private function header(): array
    {
        return [
            'date' => $this->date, 'account' => $this->account, 'rate' => $this->rate, 'party' => $this->party,
            'reference' => $this->reference, 'center' => $this->center, 'description' => $this->description, 'request' => $this->request,
        ];
    }

    /** The month's rate for the chosen account's currency, unless the person typed one. */
    private function refreshRate(CashVouchers $vouchers): void
    {
        if ($this->rateTyped) {
            return;
        }

        $currency = $vouchers->cashAccounts()->get($this->account)?->currency;
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date) === 1 ? CarbonImmutable::parse($this->date) : CarbonImmutable::today();
        $default = $currency !== null ? $vouchers->defaultRate((string) $currency, $day) : null;

        $this->rate = $default !== null ? rtrim(rtrim($default['rate'], '0'), '.') : '';
    }

    /** @return array{project: string, budget_line: string, account: string, description: string, amount: string} */
    private function blankLine(string $project = ''): array
    {
        return ['project' => $project, 'budget_line' => '', 'account' => '', 'description' => '', 'amount' => ''];
    }

    /**
     * The cash account and the project the person used last on this screen, when they are still open to them, with
     * the rate of that account; two blank lines on that project.
     */
    private function rememberedDefaults(CashVouchers $vouchers): void
    {
        $preferences = app(UserPreferences::class);
        $userId = (int) Auth::id();

        $account = (string) $preferences->get($userId, self::LAST_ACCOUNT.$this->kind());
        if ($account !== '' && $vouchers->cashAccounts()->has($account)) {
            $this->account = $account;
            $this->refreshRate($vouchers);
        }

        $project = (string) $preferences->get($userId, self::LAST_PROJECT);
        if ($project !== '' && ! $vouchers->projects()->contains(fn ($open): bool => $open->code === $project)) {
            $project = '';
        }

        $this->lines = [$this->blankLine($project), $this->blankLine($project)];
    }

    /** Keeps the cash account and the first project of the voucher just saved, for the next one. */
    private function remember(): void
    {
        $preferences = app(UserPreferences::class);
        $userId = (int) Auth::id();

        if ($this->account !== '') {
            $preferences->set($userId, self::LAST_ACCOUNT.$this->kind(), $this->account);
        }

        $project = (string) collect($this->lines)->pluck('project')->filter()->first();
        if ($project !== '') {
            $preferences->set($userId, self::LAST_PROJECT, $project);
        }
    }

    /** Preference keys: the last cash account, by kind of voucher, and the last project of any voucher. */
    private const LAST_ACCOUNT = 'entries.last_account.';

    private const LAST_PROJECT = 'entries.last_project';

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
