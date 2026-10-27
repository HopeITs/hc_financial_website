<?php

namespace App\Livewire;

use App\Enums\FundSource;
use App\Models\GlAccount;
use App\Models\GlBudgetLine;
use App\Models\GlProject;
use App\Services\Ledger\BuiltVoucher;
use App\Services\Ledger\LedgerRuleException;
use App\Services\Ledger\OperationEntry;
use App\Services\Ledger\PostVoucher;
use App\Services\Ledger\VoucherAttachments;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Single entry: one operation of the Windows program (income, expense, exchange, advance, repayment) typed as the old
 * screen asked for it, and saved as a pending voucher through the posting rules (see OperationEntry). It never writes to
 * the legacy tables.
 */
#[Layout('components.layouts.app')]
class SingleEntry extends Component
{
    use WithFileUploads;

    public string $kind = 'income';

    public string $date = '';

    public string $project = '';

    public string $budgetLine = '';

    public string $cash = '';

    public string $account = '';

    public string $toCash = '';

    public string $amount = '';

    public string $rate = '';

    public string $contact = '';

    public string $fundSource = 'donor_grant';

    public string $document = '';

    public string $description = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $attachments = [];

    /** @var list<string> */
    public array $failures = [];

    public function mount(): void
    {
        $this->date = now()->toDateString();
    }

    public function updatedKind(): void
    {
        $this->account = '';
        $this->budgetLine = '';
        $this->toCash = '';
        $this->contact = '';
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

    public function save(OperationEntry $entry, PostVoucher $posting, VoucherAttachments $files): void
    {
        Gate::authorize('screen', ['single', 'create']);
        $this->failures = [];

        $this->validate($files->rules(), $files->messages());

        $built = $this->built($entry);

        if ($built->draft === null || $built->problems !== []) {
            $this->failures = $built->problems !== [] ? $built->problems : [__('ledger.builder.no_lines')];

            return;
        }

        $stored = [];

        try {
            $voucher = DB::transaction(function () use ($posting, $built, $files, &$stored) {
                $voucher = $posting->post($built->draft);
                $stored = $files->store($voucher->id, $this->attachments, $this->preparer());

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

        session()->flash('notice', __('ledger.builder.saved', ['number' => $voucher->id]));
        $this->redirectRoute('entries.vouchers', ['voucher' => $voucher->id], navigate: true);
    }

    public function render(OperationEntry $entry, PostVoucher $posting): View
    {
        $cash = $this->cash !== '' ? GlAccount::query()->whereKey($this->cash)->first() : null;
        $built = $this->amount !== '' ? $this->built($entry) : null;
        $violations = $built?->draft !== null && $built->problems === [] ? $posting->check($built->draft) : [];

        return view('livewire.single-entry', [
            'kinds' => OperationEntry::KINDS,
            'cashAccounts' => $this->accounts(fn ($query) => $query->where('is_cash', true)),
            'incomeAccounts' => $this->accounts(fn ($query) => $query->where('class', 'income')),
            'expenseAccounts' => $this->accounts(fn ($query) => $query->where('class', 'expenses')),
            'projects' => GlProject::query()->where('is_general_account', false)->orderBy('code')->get(['code', 'name']),
            'budgetLines' => GlBudgetLine::query()->when($this->project !== '', fn ($query) => $query->where('project_code', $this->project))->orderBy('key')->get(['key', 'project_code', 'account_code']),
            'fundSources' => FundSource::cases(),
            'cashCurrency' => $cash?->currency,
            'built' => $built,
            'messages' => [...($built->problems ?? []), ...array_map(fn ($violation) => $violation->message, $violations)],
            'ready' => $built !== null && $built->draft !== null && $built->problems === [] && $violations === [],
        ]);
    }

    private function built(OperationEntry $entry): BuiltVoucher
    {
        return $entry->build([
            'kind' => $this->kind, 'date' => $this->date, 'project' => $this->project, 'budget_line' => $this->budgetLine,
            'cash' => $this->cash, 'account' => $this->account, 'to_cash' => $this->toCash, 'amount' => $this->amount,
            'rate' => $this->rate, 'contact' => $this->contact, 'fund_source' => $this->fundSource,
            'document' => $this->document, 'description' => $this->description,
        ], $this->preparer());
    }

    /**
     * @param  callable(Builder<GlAccount>): mixed  $scope
     * @return Collection<int, GlAccount>
     */
    private function accounts(callable $scope): Collection
    {
        $query = GlAccount::query()->where('level', 'posting')->where('is_active', true)->orderBy('code');
        $scope($query);

        return $query->get();
    }

    private function preparer(): int
    {
        $user = auth()->user();
        abort_if($user === null, 403);

        return (int) $user->getAuthIdentifier();
    }
}
