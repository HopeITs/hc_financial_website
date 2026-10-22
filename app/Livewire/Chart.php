<?php

namespace App\Livewire;

use App\Enums\AccountClass;
use App\Enums\AccountLevel;
use App\Enums\NormalSide;
use App\Models\GlAccount;
use App\Services\Chart\AccountBalances;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The chart of accounts: the five classes, or one account with its path, rules and the accounts below it,
 * with filters, balances and an editor. The tree to pick an account lives in the main sidebar.
 * A used account (a voucher line points at it) can only be deactivated and keeps its code, class and currency.
 */
#[Layout('components.layouts.app')]
class Chart extends Component
{
    public const CURRENCIES = ['USD', 'SYP', 'Any'];

    #[Url(as: 'account')]
    public ?string $code = null;

    public string $classFilter = '';

    public bool $postingOnly = false;

    public string $search = '';

    public bool $showEditor = false;

    /** The code being edited, or null while adding. */
    public ?string $editing = null;

    public string $formCode = '';

    public string $formLevel = 'posting';

    public string $formNameEn = '';

    public string $formNameAr = '';

    public string $formParent = '';

    public string $formClass = 'assets';

    public string $formSide = 'debit';

    public string $formCurrency = 'USD';

    public string $formCity = '';

    public bool $formCash = false;

    public string $formStatementLine = '';

    /** @var list<string> */
    public array $formTags = [];

    public string $formNote = '';

    public bool $showDeleteConfirm = false;

    public ?string $confirmDelete = null;

    public ?string $notice = null;

    public function openAdd(?string $parent = null): void
    {
        Gate::authorize('screen', ['chart', 'create']);

        $this->resetValidation();
        $this->reset('formCode', 'formNameEn', 'formNameAr', 'formCity', 'formCash', 'formStatementLine', 'formTags', 'formNote', 'formParent');
        $this->editing = null;
        $this->formLevel = 'posting';
        $this->formClass = 'assets';
        $this->formSide = 'debit';
        $this->formCurrency = 'USD';

        $parentAccount = $parent !== null ? GlAccount::query()->where('code', $parent)->where('level', 'header')->first() : null;
        if ($parentAccount !== null) {
            $this->formParent = $parentAccount->code;
            $this->formClass = $parentAccount->class->value;
            $this->formSide = $parentAccount->normal_side->value;
        }

        $this->showEditor = true;
    }

    public function openEdit(string $code): void
    {
        Gate::authorize('screen', ['chart', 'edit']);

        $account = GlAccount::query()->findOrFail($code);

        $this->resetValidation();
        $this->editing = $account->code;
        $this->formCode = $account->code;
        $this->formLevel = $account->level->value;
        $this->formNameEn = $account->name_en;
        $this->formNameAr = $account->name_ar;
        $this->formParent = (string) $account->parent_code;
        $this->formClass = $account->class->value;
        $this->formSide = $account->normal_side->value;
        $this->formCurrency = (string) ($account->currency ?? 'USD');
        $this->formCity = (string) $account->city;
        $this->formCash = $account->is_cash;
        $this->formStatementLine = (string) $account->statement_line;
        $this->formTags = array_values($account->required_tags ?? []);
        $this->formNote = (string) $account->usage_note;
        $this->showEditor = true;
    }

    public function closeEditor(): void
    {
        $this->showEditor = false;
    }

    public function save(AccountBalances $balances): void
    {
        Gate::authorize('screen', ['chart', $this->editing === null ? 'create' : 'edit']);

        $account = $this->editing !== null ? GlAccount::query()->findOrFail($this->editing) : null;
        $used = $account !== null && $balances->isUsed($account->code);

        $data = $this->validate([
            'formCode' => $account !== null ? ['in:'.$account->code] : ['required', 'regex:/^\d{4}$/', Rule::unique('gl_accounts', 'code')],
            'formLevel' => ['required', Rule::enum(AccountLevel::class)],
            'formNameEn' => ['required', 'string', 'max:255'],
            'formNameAr' => ['required', 'string', 'max:255'],
            'formParent' => ['nullable', Rule::exists('gl_accounts', 'code')->where('level', 'header')->whereNull('deleted_at')],
            'formClass' => ['required', Rule::enum(AccountClass::class)],
            'formSide' => ['required', Rule::enum(NormalSide::class)],
            'formCurrency' => [$this->formLevel === 'posting' ? 'required' : 'nullable', Rule::in(self::CURRENCIES)],
            'formCity' => ['nullable', 'string', 'max:255'],
            'formCash' => ['boolean'],
            'formStatementLine' => ['nullable', 'string', 'max:255'],
            'formTags' => ['array'],
            'formTags.*' => [Rule::in(array_keys(__('chart.tags')))],
            'formNote' => ['nullable', 'string', 'max:2000'],
        ]);

        $parent = $data['formParent'] !== '' ? GlAccount::query()->find($data['formParent']) : null;

        if ($parent !== null && $parent->class->value !== $data['formClass']) {
            throw ValidationException::withMessages(['formClass' => __('chart.error_class_parent')]);
        }
        if ($account !== null && $data['formParent'] === $account->code) {
            throw ValidationException::withMessages(['formParent' => __('chart.error_parent')]);
        }
        if ($account !== null && $data['formLevel'] === 'posting' && $account->children()->exists()) {
            throw ValidationException::withMessages(['formLevel' => __('chart.error_header_has_children')]);
        }
        if ($used && $data['formLevel'] === 'header') {
            throw ValidationException::withMessages(['formLevel' => __('chart.error_used_level')]);
        }
        if ($used && ($data['formClass'] !== $account->class->value || ($data['formCurrency'] ?: null) !== $account->currency)) {
            throw ValidationException::withMessages(['formClass' => __('chart.error_used_locked')]);
        }

        $isPosting = $data['formLevel'] === 'posting';

        $attributes = [
            'parent_code' => $parent?->code,
            'level' => $data['formLevel'],
            'class' => $data['formClass'],
            'name_en' => trim($data['formNameEn']),
            'name_ar' => trim($data['formNameAr']),
            'normal_side' => $data['formSide'],
            'currency' => $isPosting ? $data['formCurrency'] : null,
            'city' => $data['formCity'] !== '' ? $data['formCity'] : null,
            'is_cash' => $isPosting && $data['formCash'],
            'statement_line' => $data['formStatementLine'] !== '' ? $data['formStatementLine'] : null,
            'required_tags' => $isPosting ? array_values($data['formTags']) : [],
            'usage_note' => $data['formNote'] !== '' ? $data['formNote'] : null,
        ];

        if ($account === null) {
            GlAccount::query()->create($attributes + ['code' => $data['formCode'], 'is_active' => true]);
            $this->code = $data['formCode'];
            $this->notice = __('chart.saved_new');
        } else {
            $account->update($attributes);
            $this->notice = __('chart.saved');
        }

        $this->showEditor = false;
    }

    public function toggleActive(string $code): void
    {
        Gate::authorize('screen', ['chart', 'edit']);

        $account = GlAccount::query()->findOrFail($code);
        $account->update(['is_active' => ! $account->is_active]);

        $this->notice = $account->is_active ? __('chart.reactivated') : __('chart.deactivated');
    }

    public function askDelete(string $code): void
    {
        Gate::authorize('screen', ['chart', 'delete']);

        $this->confirmDelete = $code;
        $this->showDeleteConfirm = true;
    }

    public function cancelDelete(): void
    {
        $this->confirmDelete = null;
        $this->showDeleteConfirm = false;
    }

    public function delete(AccountBalances $balances): void
    {
        Gate::authorize('screen', ['chart', 'delete']);

        $account = GlAccount::query()->findOrFail($this->confirmDelete);
        $this->cancelDelete();

        if ($balances->isUsed($account->code)) {
            $this->notice = __('chart.error_delete_used');

            return;
        }
        if ($account->children()->exists()) {
            $this->notice = __('chart.error_delete_children');

            return;
        }

        $parent = $account->parent_code;
        // Never used, so the code is freed for good rather than kept as a soft deleted row.
        $account->forceDelete();

        $this->code = $parent;
        $this->notice = __('chart.deleted');
    }

    public function render(AccountBalances $balances): View
    {
        $account = $this->code !== null ? GlAccount::query()->where('code', $this->code)->first() : null;

        $path = [];
        for ($step = $account; $step !== null; $step = $step->parent_code ? GlAccount::query()->where('code', $step->parent_code)->first() : null) {
            array_unshift($path, $step);
        }

        $roots = GlAccount::query()->whereNull('parent_code')->orderBy('code')->get();
        $postingCounts = GlAccount::query()->where('level', 'posting')->selectRaw('class, count(*) as total')->groupBy('class')->pluck('total', 'class');

        $listed = collect();
        if ($account === null) {
            $term = trim($this->search);
            $listed = GlAccount::query()
                ->when($this->classFilter !== '', fn ($query) => $query->where('class', $this->classFilter))
                ->when($this->postingOnly, fn ($query) => $query->where('level', 'posting'))
                ->when($term !== '', fn ($query) => $query->where(fn ($inner) => $inner
                    ->where('code', 'like', $term.'%')
                    ->orWhere('name_en', 'like', '%'.$term.'%')
                    ->orWhere('name_ar', 'like', '%'.$term.'%')))
                ->orderBy('code')
                ->get();
        }

        return view('livewire.chart', [
            'account' => $account,
            'path' => $path,
            'children' => $account ? GlAccount::query()->where('parent_code', $account->code)->orderBy('code')->get() : collect(),
            'roots' => $roots,
            'postingCounts' => $postingCounts,
            'total' => GlAccount::query()->count(),
            'listed' => $listed,
            'balances' => $balances->all(),
            'used' => $account !== null && $balances->isUsed($account->code),
            'editingUsed' => $this->editing !== null && $balances->isUsed($this->editing),
            'headers' => GlAccount::query()->where('level', 'header')->orderBy('code')->get(['code', 'name_en', 'name_ar']),
            'canCreate' => Gate::allows('screen', ['chart', 'create']),
            'canEdit' => Gate::allows('screen', ['chart', 'edit']),
            'canDelete' => Gate::allows('screen', ['chart', 'delete']),
            'classes' => AccountClass::cases(),
            'sides' => NormalSide::cases(),
            'currencies' => self::CURRENCIES,
        ]);
    }
}
