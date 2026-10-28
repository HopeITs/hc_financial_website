<?php

namespace App\Livewire;

use App\Enums\AccountLevel;
use App\Enums\FundSource;
use App\Enums\VoucherType;
use App\Models\GlAccount;
use App\Models\GlBudgetLine;
use App\Models\GlProject;
use App\Models\VoucherAttachment;
use App\Services\Ledger\Currencies;
use App\Services\Ledger\LedgerRuleException;
use App\Services\Ledger\PostVoucher;
use App\Services\Ledger\ProjectAverageRate;
use App\Services\Ledger\VoucherDraft;
use App\Services\Ledger\VoucherDraftBuilder;
use App\Services\Ledger\VoucherWarnings;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

/**
 * The voucher builder: a header and a grid of lines, a live check list that is the posting rules themselves, two soft
 * warnings, and files to attach. Choosing a budget line fills its project and account, a cash account fixes the
 * currency, and a pound payment takes the project's average rate. It saves through PostVoucher, as pending approval.
 */
#[Layout('components.layouts.app')]
class VoucherBuilder extends Component
{
    use WithFileUploads;

    private const BLANK = [
        'account' => '', 'currency' => 'USD', 'side' => 'debit', 'amount' => '', 'rate' => '', 'project' => '',
        'budget_line' => '', 'fund_source' => '', 'contact' => '', 'center' => '', 'lira_type' => '',
        'eur_amount' => '', 'eur_rate' => '', 'commission' => '',
    ];

    /** The rule numbers behind each item of the check list. */
    private const CHECKS = ['accounts' => [1], 'currency' => [2], 'sides' => [3], 'balanced' => [4], 'tags' => [5], 'budget' => [6], 'month' => [8], 'request' => [14], 'hawala' => [15]];

    public string $type = 'CRV';

    public string $date = '';

    public string $description = '';

    public string $requestId = '';

    /** @var array<int, array<string, string>> */
    public array $lines = [];

    /** @var array<int, TemporaryUploadedFile> */
    public array $attachments = [];

    /** @var list<string> */
    public array $failures = [];

    public function mount(): void
    {
        $this->date = now()->toDateString();
        $this->lines = [self::BLANK, self::BLANK];
    }

    public function addLine(): void
    {
        $this->lines[] = self::BLANK;
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);

        if ($this->lines === []) {
            $this->lines = [self::BLANK];
        }
    }

    public function removeAttachment(int $index): void
    {
        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);
    }

    public function updatedType(): void
    {
        foreach (array_keys($this->lines) as $index) {
            $this->fillAverageRate((int) $index);
        }
    }

    public function updatedAttachments(): void
    {
        $this->validate($this->fileRules(), $this->fileMessages());
    }

    /** The form's own rules, applied as soon as a line changes: `lines.3.budget_line` and so on. */
    public function updatedLines(mixed $value, string $key): void
    {
        [$index, $field] = array_pad(explode('.', $key, 2), 2, '');

        if (! isset($this->lines[(int) $index]) || $field === '') {
            return;
        }

        $i = (int) $index;
        $line = &$this->lines[$i];

        if ($field === 'budget_line' && $line['budget_line'] !== '') {
            $budgetLine = GlBudgetLine::query()->find($line['budget_line']);
            if ($budgetLine !== null) {
                $line['account'] = $budgetLine->account_code;
                $line['project'] = $budgetLine->project_code;
            }
        }

        if (in_array($field, ['account', 'project'], true) && $line['budget_line'] !== '') {
            $budgetLine = GlBudgetLine::query()->find($line['budget_line']);
            if ($budgetLine !== null && ($budgetLine->account_code !== $line['account'] || $budgetLine->project_code !== $line['project'])) {
                $line['budget_line'] = '';
            }
        }

        if ($field === 'account') {
            $account = GlAccount::query()->find($line['account']);
            if ($account !== null && app(Currencies::class)->isActive((string) $account->currency)) {
                $line['currency'] = $account->currency;
            }
        }

        unset($line);
        $this->fillAverageRate($i);
    }

    public function save(PostVoucher $posting, VoucherDraftBuilder $builder): void
    {
        Gate::authorize('screen', ['vouchers', 'create']);
        $this->failures = [];

        $this->validate($this->fileRules(), $this->fileMessages());

        $built = $builder->build($this->type, $this->date, $this->description, $this->requestId, $this->lines, $this->preparer());

        if ($built->problems !== []) {
            $this->failures = $built->problems;

            return;
        }

        if ($built->draft === null || count($built->draft->lines) < 2) {
            $this->failures = [__('ledger.builder.no_lines')];

            return;
        }

        $stored = [];

        try {
            $voucher = DB::transaction(function () use ($posting, $built, &$stored) {
                $voucher = $posting->post($built->draft);

                foreach ($this->attachments as $file) {
                    $name = Str::uuid()->toString().'.'.($file->guessExtension() ?: 'bin');
                    $path = $file->storeAs('voucher-attachments/'.$voucher->id, $name, 'local');
                    $stored[] = $path;

                    VoucherAttachment::query()->create([
                        'voucher_id' => $voucher->id,
                        'original_name' => Str::limit(basename($file->getClientOriginalName()), 250, ''),
                        'stored_path' => $path,
                        'mime' => $file->getMimeType(),
                        'size' => $file->getSize(),
                        'sha256' => hash_file('sha256', $file->getRealPath()) ?: null,
                        'uploaded_by' => $this->preparer(),
                    ]);
                }

                return $voucher;
            });
        } catch (LedgerRuleException $e) {
            $this->failures = array_map(fn ($violation) => $violation->message, $e->violations);
            Storage::disk('local')->delete($stored);

            return;
        } catch (Throwable $e) {
            Storage::disk('local')->delete($stored);
            report($e);
            $this->failures = [__('ledger.builder.save_failed')];

            return;
        }

        session()->flash('notice', __('ledger.builder.saved', ['number' => $voucher->id]));
        $this->redirectRoute('entries.vouchers', ['voucher' => $voucher->id], navigate: true);
    }

    public function render(VoucherDraftBuilder $builder, PostVoucher $posting, VoucherWarnings $warnings): View
    {
        $built = $builder->build($this->type, $this->date, $this->description, $this->requestId, $this->lines, $this->preparer());
        $draft = $built->draft;
        $hasLines = $draft !== null && $draft->lines !== [];

        $violations = $hasLines ? $posting->check($draft) : [];
        $rowOf = array_keys($built->rows);

        $messages = array_map(
            fn ($violation) => $violation->line !== null && isset($rowOf[$violation->line - 1])
                ? __('ledger.builder.line_prefix', ['line' => $rowOf[$violation->line - 1] + 1, 'message' => $violation->message])
                : $violation->message,
            $violations,
        );

        $broken = collect($violations)->pluck('rule')->unique()->all();
        $checks = [];
        foreach (self::CHECKS as $name => $rules) {
            $ok = $hasLines && array_intersect($rules, $broken) === [] && ! ($name === 'currency' && $built->problems !== []);
            $checks[] = ['label' => __('ledger.builder.check.'.$name), 'ok' => $ok];
        }

        [$debit, $credit] = $this->totals($draft);

        $accounts = GlAccount::query()->where('level', AccountLevel::Posting->value)->where('is_active', true)->orderBy('code')->get();

        return view('livewire.voucher-builder', [
            'built' => $built,
            'ready' => $hasLines && $violations === [] && $built->problems === [] && count($draft->lines) >= 2,
            'checks' => $checks,
            'messages' => [...$messages, ...$built->problems],
            'warnings' => $hasLines ? $warnings->for($draft) : [],
            'debit' => $debit,
            'credit' => $credit,
            'difference' => bcsub($debit, $credit, 2),
            'accountGroups' => $accounts->groupBy(fn (GlAccount $account) => $account->class->value),
            'currencyCodes' => app(Currencies::class)->codes(),
            'fixedCurrency' => $accounts->filter(fn (GlAccount $account) => app(Currencies::class)->isActive((string) $account->currency))->pluck('currency', 'code')->all(),
            'projects' => GlProject::query()->orderBy('code')->get(['code', 'name']),
            'projectRates' => GlProject::query()->where('donor_currency', '!=', 'USD')->where('donor_rate', '>', 0)->pluck('donor_rate', 'code')->all(),
            'budgetLines' => GlBudgetLine::query()->orderBy('key')->get(['key', 'project_code', 'account_code']),
            'types' => collect(VoucherType::cases())->reject(fn (VoucherType $case) => $case === VoucherType::Opening)->values()->all(),
            'fundSources' => FundSource::cases(),
        ]);
    }

    /**
     * The debits and credits of the draft in USD, before any cents are moved to the exchange differences account.
     *
     * @return array{0: numeric-string, 1: numeric-string}
     */
    private function totals(?VoucherDraft $draft): array
    {
        $debit = Money::zero();
        $credit = Money::zero();

        foreach ($draft === null ? [] : $draft->lines as $line) {
            $debit = bcadd($debit, $line->debitUsd, 2);
            $credit = bcadd($credit, $line->creditUsd, 2);
        }

        return [$debit, $credit];
    }

    /** A pound line of a payment with a project and no rate takes the project's average rate. */
    private function fillAverageRate(int $index): void
    {
        $line = $this->lines[$index] ?? null;

        if ($line === null || $this->type !== VoucherType::Payment->value || $line['rate'] !== '' || $line['project'] === '') {
            return;
        }

        $account = $line['account'] !== '' ? GlAccount::query()->find($line['account']) : null;
        $currency = $account !== null && app(Currencies::class)->isActive((string) $account->currency) ? $account->currency : $line['currency'];

        if ($currency !== Currencies::BASE) {
            $average = app(ProjectAverageRate::class)->rate($line['project'], $currency);
            if ($average !== null) {
                $this->lines[$index]['rate'] = rtrim(rtrim($average, '0'), '.');
            }
        }
    }

    private function preparer(): int
    {
        $user = auth()->user();
        abort_if($user === null, 403);

        return (int) $user->getAuthIdentifier();
    }

    /** @return array<string, list<string>> */
    private function fileRules(): array
    {
        return [
            'attachments' => ['array', 'max:10'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    /** @return array<string, string> */
    private function fileMessages(): array
    {
        return [
            'attachments.max' => __('ledger.builder.files_count'),
            'attachments.*.file' => __('ledger.builder.files_type'),
            'attachments.*.mimes' => __('ledger.builder.files_type'),
            'attachments.*.max' => __('ledger.builder.files_size'),
        ];
    }
}
