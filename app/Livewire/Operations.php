<?php

namespace App\Livewire;

use App\Enums\FundSource;
use App\Enums\RoleId;
use App\Enums\ScreenAction;
use App\Enums\TransferKind;
use App\Livewire\Concerns\HasFormDraft;
use App\Models\GlAccount;
use App\Models\GlBudgetLine;
use App\Models\GlProject;
use App\Models\User;
use App\Services\Ledger\LedgerRuleException;
use App\Services\Ledger\OperationEntry;
use App\Services\Ledger\PostVoucher;
use App\Services\Ledger\TransferBuilder;
use App\Services\Ledger\TransferRates;
use App\Services\Ledger\VoucherAttachments;
use App\Services\Ledger\VoucherDraft;
use App\Support\Navigation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Operations (F-02): one place to enter every financial operation. The person picks the type, fills a form whose lists
 * follow the type, and sees the lines the program writes before saving them as a pending voucher. The lines come from the
 * existing builders, so the posting rules are not repeated here:
 *
 *  - CRV receipt and CPV payment: OperationEntry (income or advance repayment; expense or advance).
 *  - TRF transfer between projects or back to the general account, ALC allocation from a city's general account to a
 *    project, and EXG currency exchange by the general account: TransferBuilder. Until F-03 they are saved as TRF with
 *    their transfer kind, as the allocation-rates screen saves them.
 *  - JV manual entry: the voucher builder, for the Financial Manager and Head Office only.
 *
 * Only JV asks for debit and credit accounts. It never writes to the legacy tables.
 */
#[Layout('components.layouts.app')]
class Operations extends Component
{
    use HasFormDraft;
    use WithFileUploads;

    public const TYPES = ['CRV', 'CPV', 'TRF', 'ALC', 'EXG', 'JV'];

    /** The variants of each type, the first being the default; a variant is an OperationEntry kind or a TransferKind. */
    public const MODES = [
        'CRV' => ['income', 'repayment'],
        'CPV' => ['expense', 'advance', 'settlement'],
        'TRF' => ['between_projects', 'return'],
        'ALC' => ['usd_to_project', 'syp_to_project'],
        'EXG' => ['local_exchange'],
    ];

    #[Url(as: 'type', except: '')]
    public string $type = '';

    public string $mode = '';

    public string $date = '';

    public string $city = '';

    public string $project = '';

    public string $otherProject = '';

    public string $budgetLine = '';

    public string $account = '';

    public string $cash = '';

    public string $sypCash = '';

    public string $currency = 'USD';

    public string $amount = '';

    public string $rate = '';

    public string $received = '';

    public string $commission = '';

    public string $contact = '';

    public string $fundSource = 'donor_grant';

    public string $document = '';

    public string $description = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $attachments = [];

    /** @var list<string> */
    public array $failures = [];

    /** Ctrl+Enter (U-07): after saving, open a fresh form here instead of the saved voucher. */
    public bool $thenNew = false;

    public function mount(TransferRates $rates): void
    {
        $this->date = now()->toDateString();
        $this->city = $rates->cities()[0] ?? '';

        if (! in_array($this->type, $this->allowedTypes(), true)) {
            $this->type = '';
        }
        $this->resetForType();
    }

    public function pick(string $type): void
    {
        if (! in_array($type, $this->allowedTypes(), true)) {
            return;
        }

        $this->type = $type;
        $this->failures = [];
        $this->resetForType();
    }

    public function updatedMode(): void
    {
        $this->budgetLine = '';
        $this->account = '';
        $this->otherProject = '';
        $this->cash = $this->defaultCash();
    }

    public function updatedCity(): void
    {
        $this->cash = $this->defaultCash();
        $this->sypCash = $this->cashOf('SYP')->first()->code ?? '';
    }

    public function updatedCurrency(): void
    {
        $this->cash = $this->defaultCash();
    }

    public function updatedProject(): void
    {
        if ($this->type === 'CPV' && $this->budgetLine !== '' && GlBudgetLine::query()->whereKey($this->budgetLine)->value('project_code') !== $this->project) {
            $this->budgetLine = '';
            $this->account = '';
        }
    }

    public function updatedBudgetLine(): void
    {
        $line = $this->budgetLine !== '' ? GlBudgetLine::query()->whereKey($this->budgetLine)->first() : null;

        if ($line !== null) {
            $this->project = $line->project_code;
            $this->account = $line->account_code;
        }
    }

    public function updatedAttachments(): void
    {
        $this->validate(app(VoucherAttachments::class)->rules(), app(VoucherAttachments::class)->messages());
    }

    public function removeAttachment(int $index): void
    {
        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);
    }

    public function save(PostVoucher $posting, VoucherAttachments $files): void
    {
        abort_unless(in_array($this->type, $this->allowedTypes(), true) && $this->type !== 'JV', 403);
        Gate::authorize('screen', $this->permissionFor($this->type));
        $this->failures = [];

        $this->validate($files->rules(), $files->messages());

        [$draft, $problems] = $this->built();

        if ($draft === null || $problems !== []) {
            $this->failures = $problems !== [] ? $problems : [__('ledger.builder.no_lines')];

            return;
        }

        $stored = [];

        try {
            $voucher = DB::transaction(function () use ($posting, $draft, $files, &$stored) {
                $voucher = $posting->post($draft);
                $stored = $files->store($voucher->id, $this->attachments, $this->userId());

                return $voucher;
            });
        } catch (LedgerRuleException $e) {
            $files->forget($stored);
            $this->failures = array_map(fn ($violation) => $violation->message, $e->violations);

            return;
        } catch (Throwable $e) {
            $files->forget($stored);
            report($e);
            $this->failures = [__('ledger.builder.save_failed')];

            return;
        }

        $this->forgetDraft();
        session()->flash('notice', __('ledger.builder.saved', ['number' => $voucher->id]));

        if ($this->thenNew) {
            $this->redirectRoute(Navigation::routeName('single'), ['type' => $this->type], navigate: true);

            return;
        }

        $this->redirectRoute('entries.vouchers', ['voucher' => $voucher->id], navigate: true);
    }

    protected function draftFields(): array
    {
        return [
            'type', 'mode', 'date', 'city', 'project', 'otherProject', 'budgetLine', 'account', 'cash', 'sypCash', 'currency',
            'amount', 'rate', 'received', 'commission', 'contact', 'fundSource', 'document', 'description',
        ];
    }

    protected function draftScreen(): string
    {
        return 'operations';
    }

    protected function draftPermission(): array
    {
        return $this->permissionFor(in_array($this->type, self::TYPES, true) ? $this->type : 'CRV');
    }

    public function render(PostVoucher $posting, TransferRates $rates): View
    {
        $draft = null;
        $problems = [];
        if ($this->type !== '' && $this->type !== 'JV' && $this->mode !== 'settlement' && $this->amount !== '') {
            [$draft, $problems] = $this->built();
        }
        $violations = $draft !== null && $problems === [] ? $posting->check($draft) : [];
        $cash = $this->cash !== '' ? GlAccount::query()->whereKey($this->cash)->first() : null;

        return view('livewire.operations', [
            'types' => $this->allowedTypes(),
            'modes' => self::MODES[$this->type] ?? [],
            'cities' => $rates->cities(),
            'projects' => GlProject::query()->where('is_general_account', false)->orderBy('code')->get(['code', 'name']),
            'budgetLines' => GlBudgetLine::query()->when($this->project !== '', fn ($query) => $query->where('project_code', $this->project))->orderBy('key')->get(['key', 'project_code', 'account_code']),
            'incomeAccounts' => $this->postingAccounts('income'),
            'expenseAccounts' => $this->postingAccounts('expenses'),
            'cashAccounts' => $this->cashAccounts(),
            'sypCashAccounts' => $this->cashOf('SYP'),
            'fundSources' => FundSource::cases(),
            'cashCurrency' => $this->isTransfer() ? $this->transferCurrency() : $cash?->currency,
            'draft' => $draft,
            'accountNames' => $draft === null ? collect() : GlAccount::query()->whereIn('code', collect($draft->lines)->pluck('accountCode')->unique()->all())->get()->keyBy('code'),
            'messages' => [...$problems, ...array_map(fn ($violation) => $violation->message, $violations)],
            'ready' => $draft !== null && $problems === [] && $violations === [],
        ]);
    }

    /** @return array{0: ?VoucherDraft, 1: list<string>} the voucher the form describes, or the problems that stop it */
    private function built(): array
    {
        if ($this->isTransfer()) {
            $built = app(TransferBuilder::class)->build(
                $this->mode, $this->date, $this->city, $this->project, $this->otherProject, $this->currency,
                $this->amount, $this->cash, $this->contact, $this->description, $this->userId(),
                $this->rate, $this->received, $this->commission, $this->sypCash,
            );

            return [$built->draft, $built->problems];
        }

        $built = app(OperationEntry::class)->build([
            'kind' => $this->mode, 'date' => $this->date, 'project' => $this->project, 'budget_line' => $this->budgetLine,
            'cash' => $this->cash, 'account' => $this->account, 'amount' => $this->amount, 'rate' => $this->rate,
            'contact' => $this->contact, 'fund_source' => $this->fundSource, 'document' => $this->document,
            'description' => $this->description,
        ], $this->userId());

        return [$built->draft, $built->problems];
    }

    private function resetForType(): void
    {
        $this->mode = self::MODES[$this->type][0] ?? '';
        $this->project = '';
        $this->otherProject = '';
        $this->budgetLine = '';
        $this->account = '';
        $this->currency = 'USD';
        $this->amount = '';
        $this->rate = '';
        $this->received = '';
        $this->commission = '';
        $this->contact = '';
        $this->cash = $this->defaultCash();
        $this->sypCash = $this->cashOf('SYP')->first()->code ?? '';
    }

    private function isTransfer(): bool
    {
        return in_array($this->type, ['TRF', 'ALC', 'EXG'], true);
    }

    /** The currency a transfer moves: fixed by an allocation or an exchange, picked for a transfer between projects. */
    private function transferCurrency(): string
    {
        return match (TransferKind::tryFrom($this->mode)) {
            TransferKind::UsdToProject, TransferKind::LocalExchange => 'USD',
            TransferKind::SypToProject => 'SYP',
            default => $this->currency,
        };
    }

    /** @return Collection<int, GlAccount> the till a transfer uses is one of the city's, the till of a receipt or payment any */
    private function cashAccounts(): Collection
    {
        if ($this->isTransfer()) {
            return $this->cashOf($this->transferCurrency());
        }

        return GlAccount::query()->where('is_cash', true)->where('level', 'posting')->where('is_active', true)->orderBy('code')->get();
    }

    /** @return Collection<int, GlAccount> the city's cash accounts of the currency, in transit left out */
    private function cashOf(string $currency): Collection
    {
        return GlAccount::query()
            ->where('is_cash', true)->where('level', 'posting')->where('is_active', true)
            ->where('city', $this->city)->where('currency', $currency)
            ->orderBy('code')->get()
            ->reject(fn (GlAccount $account) => preg_match((string) config('ledger.in_transit_pattern'), $account->code) === 1)
            ->values();
    }

    private function defaultCash(): string
    {
        return $this->isTransfer() ? ($this->cashAccounts()->first()->code ?? '') : '';
    }

    /** @return Collection<int, GlAccount> */
    private function postingAccounts(string $class): Collection
    {
        return GlAccount::query()->where('level', 'posting')->where('is_active', true)->where('class', $class)->orderBy('code')->get();
    }

    /** @return array{0: string, 1: string} the screen and action that saving an operation of the type needs */
    private function permissionFor(string $type): array
    {
        return match ($type) {
            'TRF', 'ALC', 'EXG' => ['alloc', 'create'],
            'JV' => ['vouchers', 'create'],
            default => ['single', 'create'],
        };
    }

    /** @return list<string> the types this person may enter; JV is for the Financial Manager and Head Office only */
    private function allowedTypes(): array
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return [];
        }

        return array_values(array_filter(self::TYPES, function (string $type) use ($user): bool {
            [$screen] = $this->permissionFor($type);
            if (! $user->canOnScreen($screen, ScreenAction::Create)) {
                return false;
            }

            return $type !== 'JV' || array_intersect(array_map(fn (RoleId $role) => $role->value, $user->roleIds()), [RoleId::FinancialManager->value, RoleId::HeadOffice->value, RoleId::SuperUser->value]) !== [];
        }));
    }

    private function userId(): int
    {
        $user = auth()->user();
        abort_if($user === null, 403);

        return (int) $user->getAuthIdentifier();
    }
}
