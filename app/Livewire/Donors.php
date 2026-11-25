<?php

namespace App\Livewire;

use App\Enums\ContractStatus;
use App\Enums\DonorType;
use App\Models\Contract;
use App\Models\Donor;
use App\Services\Ledger\Currencies;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * The donor list (finance design of 2026-11-16): kept apart from the chart of accounts, chosen on a funding contract
 * and, when needed, on a voucher line. The Financial Manager and Head Office add and edit donors; the other finance
 * roles only read the list (config/permissions.php, screen `donors`).
 */
#[Layout('components.layouts.app')]
class Donors extends Component
{
    use WithFileUploads;

    /** Where the logos are kept on the private disk. */
    private const LOGO_FOLDER = 'donor-logos';

    public string $search = '';

    public string $typeFilter = '';

    public string $statusFilter = 'active';

    public bool $showForm = false;

    public ?int $editing = null;

    public string $code = '';

    public string $nameEn = '';

    public string $nameAr = '';

    public string $type = '';

    public string $country = '';

    public string $currency = '';

    public string $contactName = '';

    public string $contactEmail = '';

    public string $contactPhone = '';

    public string $notes = '';

    public bool $isActive = true;

    /** A new logo for the donor (finance request of 2026-11-28). */
    public ?TemporaryUploadedFile $logo = null;

    /** The donor's logo is taken off when saved. */
    public bool $removeLogo = false;

    public ?string $notice = null;

    public function add(): void
    {
        Gate::authorize('screen', ['donors', 'create']);

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize('screen', ['donors', 'edit']);

        $donor = Donor::query()->findOrFail($id);

        $this->resetForm();
        $this->editing = $donor->id;
        $this->code = $donor->code;
        $this->nameEn = $donor->name_en;
        $this->nameAr = $donor->name_ar;
        $this->type = $donor->type->value;
        $this->country = (string) $donor->country;
        $this->currency = (string) $donor->currency;
        $this->contactName = (string) $donor->contact_name;
        $this->contactEmail = (string) $donor->contact_email;
        $this->contactPhone = (string) $donor->contact_phone;
        $this->notes = (string) $donor->notes;
        $this->isActive = $donor->is_active;
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    public function save(Currencies $currencies): void
    {
        Gate::authorize('screen', ['donors', $this->editing === null ? 'create' : 'edit']);

        $this->code = strtoupper(trim($this->code));

        $data = $this->validate([
            'code' => ['required', 'regex:/^[A-Z0-9-]{2,16}$/', Rule::unique('gl_donors', 'code')->ignore($this->editing)],
            'nameEn' => ['required', 'string', 'max:255'],
            'nameAr' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(DonorType::class)],
            'country' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', Rule::in($currencies->codes())],
            'contactName' => ['nullable', 'string', 'max:255'],
            'contactEmail' => ['nullable', 'email', 'max:255'],
            'contactPhone' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'isActive' => ['boolean'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:2048'],
        ]);

        $attributes = [
            'code' => $data['code'],
            'name_en' => trim($data['nameEn']),
            'name_ar' => trim($data['nameAr']),
            'type' => $data['type'],
            'country' => $this->nullIfBlank($data['country']),
            'currency' => $this->nullIfBlank($data['currency']),
            'contact_name' => $this->nullIfBlank($data['contactName']),
            'contact_email' => $this->nullIfBlank($data['contactEmail']),
            'contact_phone' => $this->nullIfBlank($data['contactPhone']),
            'notes' => $this->nullIfBlank($data['notes']),
            'is_active' => $data['isActive'],
        ];

        $isNew = $this->editing === null;

        $donor = $isNew ? Donor::query()->create($attributes) : tap(Donor::query()->findOrFail($this->editing))->update($attributes);

        if ($this->logo !== null || $this->removeLogo) {
            $old = $donor->logo_path;
            $path = $this->logo !== null ? $this->logo->storeAs(self::LOGO_FOLDER, $donor->id.'-'.Str::random(8).'.'.$this->logo->extension(), 'local') : null;
            $donor->update(['logo_path' => $path]);

            if ($old !== null && $old !== $path && str_starts_with($old, self::LOGO_FOLDER.'/')) {
                Storage::disk('local')->delete($old);
            }
        }

        $this->resetForm();
        $this->showForm = false;
        $this->notice = __($isNew ? 'donors.added' : 'donors.saved', ['name' => $attributes['name_en']]);
    }

    /**
     * Deletes a donor that no agreement names (finance request of 2026-11-20); one with agreements is deactivated
     * instead, so the agreements keep their donor.
     */
    public function delete(int $id): void
    {
        Gate::authorize('screen', ['donors', 'delete']);
        $donor = Donor::query()->findOrFail($id);

        if ($donor->contracts()->exists()) {
            $this->notice = __('donors.delete_has_agreements', ['name' => $donor->name_en]);

            return;
        }

        if ($donor->logo_path !== null && str_starts_with($donor->logo_path, self::LOGO_FOLDER.'/')) {
            Storage::disk('local')->delete($donor->logo_path);
        }

        $donor->delete();
        $this->resetForm();
        $this->showForm = false;
        $this->notice = __('donors.deleted', ['name' => $donor->name_en]);
    }

    /** The Excel tools saved donors: show them with their message. */
    #[On('excel-imported')]
    public function excelImported(string $message): void
    {
        $this->notice = $message;
    }

    public function render(Currencies $currencies): View
    {
        $donors = Donor::query()
            ->when($this->search !== '', fn (Builder $query) => $query->where(fn (Builder $match) => $match
                ->where('code', 'like', '%'.$this->search.'%')
                ->orWhere('name_en', 'like', '%'.$this->search.'%')
                ->orWhere('name_ar', 'like', '%'.$this->search.'%')))
            ->when($this->typeFilter !== '', fn (Builder $query) => $query->where('type', $this->typeFilter))
            ->when($this->statusFilter !== 'all', fn (Builder $query) => $query->where('is_active', $this->statusFilter === 'active'))
            ->orderBy('code')
            ->get();

        $running = Contract::query()
            ->whereIn('donor_id', $donors->pluck('id'))
            ->whereNotIn('status', [ContractStatus::Ended->value, ContractStatus::Cancelled->value])
            ->get(['donor_id', 'currency', 'amount', 'fixed_rate'])
            ->groupBy('donor_id');

        $runningUsd = $running->map(fn ($contracts) => $contracts->reduce(
            fn (string $sum, Contract $contract): string => bcadd($sum, $currencies->toUsd($contract->currency, (string) $contract->amount, (string) $contract->fixed_rate), 2),
            '0.00',
        ));

        return view('livewire.donors', [
            'donors' => $donors,
            'runningCount' => $running->map->count(),
            'runningUsd' => $runningUsd,
            'types' => DonorType::cases(),
            'currencyCodes' => $currencies->codes(),
            'canCreate' => Gate::allows('screen', ['donors', 'create']),
            'canEdit' => Gate::allows('screen', ['donors', 'edit']),
            'canDelete' => Gate::allows('screen', ['donors', 'delete']),
            'canSeeContracts' => Gate::allows('screen', ['contracts', 'view']),
        ]);
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->reset('editing', 'code', 'nameEn', 'nameAr', 'type', 'country', 'currency', 'contactName', 'contactEmail', 'contactPhone', 'notes', 'notice', 'logo', 'removeLogo');
        $this->isActive = true;
    }

    private function nullIfBlank(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
