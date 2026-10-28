<?php

namespace App\Livewire;

use App\Enums\AccountClass;
use App\Enums\AccountLevel;
use App\Models\GlAccount;
use App\Models\GlProject;
use App\Services\Ledger\Currencies;
use App\Services\Projects\BudgetSheetImport;
use App\Services\Projects\ProjectWriter;
use App\Support\Navigation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Create a project in the ledger (`gl_projects`) with its budget lines, or edit one (decisions R4-5, R4-11, R4-12).
 * A budget sheet in the ADISC format can be imported: each donor in it becomes a project, and each line shows the
 * expense account proposed from its type and name, to change before saving. Nothing is saved until "Save".
 */
#[Layout('components.layouts.app')]
class ProjectEditor extends Component
{
    use WithFileUploads;

    public const RESTRICTIONS = ['With donor restrictions', 'Without donor restrictions'];

    /** The project being edited; null when creating. */
    public ?string $editing = null;

    /** @var list<array<string, mixed>> */
    public array $projects = [];

    public ?TemporaryUploadedFile $sheet = null;

    public ?string $notice = null;

    public ?string $importError = null;

    public function mount(?string $code = null): void
    {
        Gate::authorize('screen', ['projects', $code === null ? 'create' : 'edit']);

        if ($code === null) {
            $this->projects = [$this->blankProject()];

            return;
        }

        $project = GlProject::query()->with(['budgetLines' => fn ($query) => $query->orderBy('key')])->findOrFail($code);
        $this->editing = $project->code;
        $this->projects = [[
            'code' => $project->code,
            'name' => $project->name,
            'donor' => (string) $project->donor,
            'restriction' => $project->restriction,
            'city' => (string) $project->city,
            'donor_currency' => $project->donor_currency,
            'donor_rate' => (string) $project->donor_rate,
            'contract_number' => (string) $project->contract_number,
            'start_date' => $project->start_date?->toDateString() ?? '',
            'end_date' => $project->end_date?->toDateString() ?? '',
            'lines' => $project->budgetLines->map(fn ($line): array => [
                'key' => $line->key,
                'name' => $line->name,
                'type' => '',
                'account_code' => $line->account_code,
                'donor' => (string) $line->donor,
                'budget' => (string) $line->budget,
                'currency' => $line->currency,
            ])->all(),
        ]];
    }

    public function addProject(): void
    {
        $this->authorizeWrite();

        if ($this->editing === null) {
            $this->projects[] = $this->blankProject();
        }
    }

    public function removeProject(int $index): void
    {
        $this->authorizeWrite();

        if ($this->editing === null && count($this->projects) > 1) {
            unset($this->projects[$index]);
            $this->projects = array_values($this->projects);
        }
    }

    public function addLine(int $project): void
    {
        $this->authorizeWrite();

        if (isset($this->projects[$project])) {
            $this->projects[$project]['lines'][] = $this->blankLine((string) $this->projects[$project]['donor']);
        }
    }

    /** Only a line that is not saved yet can be dropped: vouchers may already refer to a saved one. */
    public function removeLine(int $project, int $line): void
    {
        $this->authorizeWrite();

        if (isset($this->projects[$project]['lines'][$line]) && blank($this->projects[$project]['lines'][$line]['key'])) {
            unset($this->projects[$project]['lines'][$line]);
            $this->projects[$project]['lines'] = array_values($this->projects[$project]['lines']);
        }
    }

    /**
     * Reads the sheet and replaces the form with one project per donor, built from the first project's fields.
     */
    public function importSheet(BudgetSheetImport $import): void
    {
        $this->authorizeWrite();
        abort_if($this->editing !== null, 403);

        $this->importError = null;
        $this->validate(['sheet' => ['required', 'file', 'mimes:xlsx', 'max:5120']]);

        $template = $this->projects[0];
        unset($template['lines']);

        if (blank($template['code']) || blank($template['name'])) {
            $this->importError = __('projects.manage.import_template');

            return;
        }

        try {
            $drafts = $import->drafts($this->sheet->getRealPath(), $template);
        } catch (InvalidArgumentException $exception) {
            $this->importError = $exception->getMessage();

            return;
        } catch (Throwable) {
            $this->importError = __('projects.manage.import_unreadable');

            return;
        }

        if ($drafts === []) {
            $this->importError = __('projects.manage.import_empty');

            return;
        }

        $this->projects = $drafts;
        $this->sheet = null;
        $this->notice = __('projects.manage.imported', ['lines' => array_sum(array_map(fn (array $draft): int => count($draft['lines']), $drafts)), 'projects' => count($drafts)]);
    }

    public function save(ProjectWriter $writer): void
    {
        $this->authorizeWrite();

        $this->validate($this->projectRules(), $this->projectMessages(), $this->projectAttributes());

        $saved = [];

        foreach ($this->projects as $draft) {
            $saved[] = $writer->save($draft, $this->editing !== null)->code;
        }

        session()->flash('status', __('projects.manage.saved', ['codes' => implode(', ', $saved)]));

        $this->redirectRoute(Navigation::routeName('projects'));
    }

    public function render(): View
    {
        $accounts = GlAccount::query()
            ->where('class', AccountClass::Expenses->value)->where('level', AccountLevel::Posting->value)->where('is_active', true)
            ->orderBy('code')->get(['code', 'name_en']);

        return view('livewire.project-editor', [
            'accounts' => $accounts,
            'restrictions' => self::RESTRICTIONS,
            'donors' => GlAccount::donorNames(),
            'currencies' => app(Currencies::class)->codes(),
            'isEditing' => $this->editing !== null,
        ]);
    }

    /** @return array<string, mixed> */
    private function projectRules(): array
    {
        $account = Rule::exists('gl_accounts', 'code')->where(fn ($query) => $query->where('class', AccountClass::Expenses->value)->where('level', AccountLevel::Posting->value)->whereNull('deleted_at'));

        return [
            'projects' => ['required', 'array', 'min:1'],
            'projects.*.code' => ['required', 'regex:/^[A-Z0-9][A-Z0-9_-]{0,15}$/', 'distinct', $this->editing === null ? Rule::unique('gl_projects', 'code') : Rule::in([$this->editing])],
            'projects.*.name' => ['required', 'string', 'max:255', 'distinct:ignore_case', function (string $attribute, mixed $value, \Closure $fail): void {
                $taken = GlProject::query()->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim((string) $value))])
                    ->when($this->editing !== null, fn ($query) => $query->where('code', '!=', $this->editing))->exists();

                if ($taken) {
                    $fail(__('projects.manage.name_taken'));
                }
            }],
            'projects.*.donor' => ['nullable', Rule::in(GlAccount::donorNames())],
            'projects.*.restriction' => ['required', Rule::in(self::RESTRICTIONS)],
            'projects.*.city' => ['nullable', 'string', 'max:255'],
            'projects.*.donor_currency' => ['required', 'string', 'size:3', 'alpha'],
            'projects.*.donor_rate' => ['required', 'numeric', 'gt:0'],
            'projects.*.contract_number' => ['nullable', 'string', 'max:64'],
            'projects.*.start_date' => ['nullable', 'date'],
            'projects.*.end_date' => ['nullable', 'date', 'after_or_equal:projects.*.start_date'],
            'projects.*.lines' => ['array'],
            'projects.*.lines.*.name' => ['required', 'string', 'max:255', 'distinct:ignore_case'],
            'projects.*.lines.*.account_code' => ['required', $account],
            'projects.*.lines.*.donor' => ['nullable', Rule::in(GlAccount::donorNames())],
            'projects.*.lines.*.budget' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
            'projects.*.lines.*.currency' => ['required', Rule::in(app(Currencies::class)->codes())],
        ];
    }

    /** @return array<string, string> */
    private function projectMessages(): array
    {
        return [
            'projects.*.code.regex' => __('projects.manage.code_format'),
            'projects.*.lines.*.account_code.required' => __('projects.manage.account_required'),
            'projects.*.lines.*.account_code.exists' => __('projects.manage.account_required'),
        ];
    }

    /** @return array<string, string> */
    private function projectAttributes(): array
    {
        return [
            'projects.*.code' => __('projects.manage.code'),
            'projects.*.name' => __('projects.manage.name'),
            'projects.*.donor_rate' => __('projects.manage.donor_rate'),
            'projects.*.end_date' => __('projects.manage.end_date'),
            'projects.*.lines.*.name' => __('projects.manage.line_name'),
            'projects.*.lines.*.budget' => __('projects.manage.line_budget'),
        ];
    }

    private function authorizeWrite(): void
    {
        Gate::authorize('screen', ['projects', $this->editing === null ? 'create' : 'edit']);
    }

    /** @return array<string, mixed> */
    private function blankProject(): array
    {
        return [
            'code' => '', 'name' => '', 'donor' => '', 'restriction' => self::RESTRICTIONS[0], 'city' => '',
            'donor_currency' => 'USD', 'donor_rate' => '1', 'contract_number' => '', 'start_date' => '', 'end_date' => '',
            'lines' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function blankLine(string $donor): array
    {
        return ['key' => null, 'name' => '', 'type' => '', 'account_code' => '', 'donor' => $donor, 'budget' => '0', 'currency' => 'USD'];
    }
}
