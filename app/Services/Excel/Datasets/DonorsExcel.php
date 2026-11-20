<?php

namespace App\Services\Excel\Datasets;

use App\Enums\DonorType;
use App\Models\Donor;
use App\Models\User;
use App\Services\Excel\ExcelDataset;
use App\Services\Ledger\Currencies;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * Donors to and from Excel: one donor per row, known by its code. A code already in the list updates that donor (the
 * person needs `edit`); a new code adds one. The type is its key or its name in either language; active is yes or no.
 */
class DonorsExcel extends ExcelDataset
{
    private const YES = ['1', 'yes', 'y', 'true', 'active', 'نعم', 'فعال', 'فعّال'];

    private const NO = ['0', 'no', 'n', 'false', 'inactive', 'لا', 'موقوف'];

    public function __construct(private readonly Currencies $currencies) {}

    public function screen(): string
    {
        return 'donors';
    }

    public function fileName(): string
    {
        return 'donors';
    }

    public function columns(): array
    {
        return ['code', 'name_en', 'name_ar', 'type', 'country', 'currency', 'contact_name', 'contact_email', 'contact_phone', 'active', 'notes'];
    }

    public function exportRows(array $context): iterable
    {
        foreach (Donor::query()->orderBy('code')->cursor() as $donor) {
            yield [
                $donor->code, $donor->name_en, $donor->name_ar, $donor->type->value, (string) $donor->country, (string) $donor->currency,
                (string) $donor->contact_name, (string) $donor->contact_email, (string) $donor->contact_phone, $donor->is_active ? 1 : 0, (string) $donor->notes,
            ];
        }
    }

    public function exampleRows(array $context): array
    {
        return [['DONOR1', 'Donor name', 'اسم المانح', DonorType::Foundation->value, 'Germany', 'EUR', '', '', '', 1, '']];
    }

    public function referenceSheets(array $context): array
    {
        return [
            __('excel.sheet.donor_types') => [
                'headings' => [__('excel.col.type'), __('excel.col.name_en'), __('excel.col.name_ar')],
                'rows' => array_map(fn (DonorType $type): array => [$type->value, __('donors.types.'.$type->value, [], 'en'), __('donors.types.'.$type->value, [], 'ar')], DonorType::cases()),
            ],
            __('excel.sheet.currencies') => [
                'headings' => [__('excel.col.currency')],
                'rows' => array_map(fn (string $code): array => [$code], $this->currencies->codes()),
            ],
        ];
    }

    protected function check(array $cells, array $before, User $user, array $context): array
    {
        $code = strtoupper($cells['code']);
        $existing = Donor::query()->where('code', $code)->first();
        $problems = [];

        $validator = Validator::make([
            'code' => $code,
            'name_en' => $cells['name_en'],
            'name_ar' => $cells['name_ar'],
            'country' => $cells['country'],
            'contact_name' => $cells['contact_name'],
            'contact_email' => $cells['contact_email'],
            'contact_phone' => $cells['contact_phone'],
            'notes' => $cells['notes'],
        ], [
            'code' => ['required', 'regex:/^[A-Z0-9-]{2,16}$/'],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], array_combine(
            ['code', 'name_en', 'name_ar', 'country', 'contact_name', 'contact_email', 'contact_phone', 'notes'],
            array_map(fn (string $column): string => __('excel.col.'.$column), ['code', 'name_en', 'name_ar', 'country', 'contact_name', 'contact_email', 'contact_phone', 'notes']),
        ));
        $problems = $validator->errors()->all();

        if ($this->type($cells['type']) === null) {
            $problems[] = __('excel.bad_donor_type');
        }

        if ($cells['currency'] !== '' && ! in_array(strtoupper($cells['currency']), $this->currencies->codes(), true)) {
            $problems[] = __('excel.bad_currency', ['currency' => $cells['currency'], 'allowed' => implode(', ', $this->currencies->codes())]);
        }

        if ($this->active($cells['active']) === null) {
            $problems[] = __('excel.bad_yes_no', ['column' => __('excel.col.active')]);
        }

        if (in_array($code, array_map(fn (array $earlier): string => strtoupper($earlier['code']), $before), true)) {
            $problems[] = __('excel.twice');
        }

        if ($existing !== null && ! Gate::forUser($user)->allows('screen', ['donors', 'edit'])) {
            $problems[] = __('excel.no_update_right');
        }

        return ['action' => $existing !== null ? 'update' : 'new', 'problems' => $problems];
    }

    protected function saveRow(array $cells, User $user, array $context): void
    {
        $blank = fn (string $value): ?string => $value === '' ? null : $value;

        Donor::query()->updateOrCreate(['code' => strtoupper($cells['code'])], [
            'name_en' => $cells['name_en'],
            'name_ar' => $cells['name_ar'],
            'type' => $this->type($cells['type'])?->value,
            'country' => $blank($cells['country']),
            'currency' => $blank(strtoupper($cells['currency'])),
            'contact_name' => $blank($cells['contact_name']),
            'contact_email' => $blank($cells['contact_email']),
            'contact_phone' => $blank($cells['contact_phone']),
            'notes' => $blank($cells['notes']),
            'is_active' => (bool) $this->active($cells['active']),
        ]);
    }

    /** The type from its key or its name in either language. */
    private function type(string $text): ?DonorType
    {
        $text = mb_strtolower(trim($text));

        foreach (DonorType::cases() as $type) {
            if (in_array($text, [$type->value, mb_strtolower(__('donors.types.'.$type->value, [], 'en')), mb_strtolower(__('donors.types.'.$type->value, [], 'ar'))], true)) {
                return $type;
            }
        }

        return null;
    }

    /** Yes or no; an empty cell is yes, a new donor being active. */
    private function active(string $text): ?bool
    {
        $text = mb_strtolower(trim($text));

        return match (true) {
            $text === '' || in_array($text, self::YES, true) => true,
            in_array($text, self::NO, true) => false,
            default => null,
        };
    }
}
