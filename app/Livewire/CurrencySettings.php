<?php

namespace App\Livewire;

use App\Models\Currency;
use App\Services\Ledger\Currencies;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The currencies of the books (decision R4-4). USD is the base and cannot be changed; any other currency is added here and
 * then works like SYP and EUR: cash accounts in it, voucher lines at a typed rate, reports at a typed rate. A currency used
 * by a voucher line keeps its code and its way of quoting the rate, so old lines keep their meaning.
 */
#[Layout('components.layouts.app')]
class CurrencySettings extends Component
{
    public bool $showForm = false;

    public ?string $editing = null;

    public string $code = '';

    public string $nameEn = '';

    public string $nameAr = '';

    public string $quote = Currency::PER_USD;

    public string $active = '1';

    public ?string $notice = null;

    public function add(): void
    {
        Gate::authorize('screen', ['currency', 'create']);

        $this->resetValidation();
        $this->reset('editing', 'code', 'nameEn', 'nameAr', 'notice');
        $this->quote = Currency::PER_USD;
        $this->active = '1';
        $this->showForm = true;
    }

    public function edit(string $code): void
    {
        Gate::authorize('screen', ['currency', 'edit']);

        $currency = Currency::query()->findOrFail($code);

        if ($currency->is_base) {
            return;
        }

        $this->resetValidation();
        $this->notice = null;
        $this->editing = $currency->code;
        $this->code = $currency->code;
        $this->nameEn = $currency->name_en;
        $this->nameAr = $currency->name_ar;
        $this->quote = $currency->quote;
        $this->active = $currency->is_active ? '1' : '0';
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->showForm = false;
    }

    public function save(): void
    {
        Gate::authorize('screen', ['currency', $this->editing === null ? 'create' : 'edit']);

        $this->code = strtoupper(trim($this->code));

        $data = $this->validate([
            'code' => ['required', 'regex:/^[A-Z]{3}$/', Rule::unique('gl_currencies', 'code')->ignore($this->editing, 'code')],
            'nameEn' => ['required', 'string', 'max:255'],
            'nameAr' => ['required', 'string', 'max:255'],
            'quote' => ['required', Rule::in([Currency::PER_USD, Currency::USD_PER_UNIT])],
            'active' => ['required', Rule::in(['0', '1'])],
        ]);

        if ($this->editing === null) {
            Currency::query()->create([
                'code' => $data['code'], 'name_en' => trim($data['nameEn']), 'name_ar' => trim($data['nameAr']), 'quote' => $data['quote'],
                'is_base' => false, 'is_active' => $data['active'] === '1', 'sort_order' => (int) Currency::query()->max('sort_order') + 1,
            ]);
            $this->notice = __('currencies.added', ['code' => $data['code']]);
        } else {
            $currency = Currency::query()->findOrFail($this->editing);

            if ($currency->is_base) {
                return;
            }

            if ($currency->quote !== $data['quote'] && $this->linesIn($currency->code) > 0) {
                $this->addError('quote', __('currencies.quote_locked'));

                return;
            }

            $currency->update(['name_en' => trim($data['nameEn']), 'name_ar' => trim($data['nameAr']), 'quote' => $data['quote'], 'is_active' => $data['active'] === '1']);
            $this->notice = __('currencies.saved', ['code' => $currency->code]);
        }

        app()->forgetInstance(Currencies::class);
        $this->showForm = false;
    }

    public function render(): View
    {
        $used = DB::table('gl_voucher_lines')->selectRaw('currency, count(*) as n')->groupBy('currency')->pluck('n', 'currency');

        return view('livewire.currency-settings', [
            'rows' => Currency::query()->orderBy('sort_order')->orderBy('code')->get(),
            'used' => $used,
            'canCreate' => Gate::allows('screen', ['currency', 'create']),
            'canEdit' => Gate::allows('screen', ['currency', 'edit']),
        ]);
    }

    private function linesIn(string $code): int
    {
        return DB::table('gl_voucher_lines')->where('currency', $code)->count();
    }
}
