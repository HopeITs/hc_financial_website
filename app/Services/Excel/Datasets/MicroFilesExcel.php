<?php

namespace App\Services\Excel\Datasets;

use App\Models\GlProject;
use App\Models\MicroItem;
use App\Models\MicroLoan;
use App\Models\User;
use App\Services\Excel\ExcelDataset;
use App\Services\Excel\ExcelException;
use App\Services\Loans\InstalmentFunds;
use App\Services\Loans\LoanException;
use App\Services\Loans\MicroFiles;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Beneficiaries' files from Excel (finance request of 2026-11-23): one row per beneficiary, saved as a new file with no
 * voucher, exactly as the files screen saves it. A row whose project number is already a file still being prepared
 * (new or sent back) changes that file; a file already sent for approval is left as it is. Funding is a project's code,
 * or the funder and the city of an instalment fund. The purchase list is an optional second sheet, under each file's
 * project number: when it lists a file's items they replace the file's list, otherwise the file keeps its own.
 */
class MicroFilesExcel extends ExcelDataset
{
    public function __construct(
        private readonly MicroFiles $files,
        private readonly InstalmentFunds $funds,
    ) {}

    public function screen(): string
    {
        return 'microfiles';
    }

    public function fileName(): string
    {
        return 'micro-files';
    }

    public function columns(): array
    {
        return [
            'project_number', 'beneficiary', 'name_en', 'national_number', 'mobile', 'partners', 'profession', 'address', 'project', 'fund_funder',
            'fund_city', 'currency', 'rate', 'requested_amount', 'seizure_fee', 'recovery_percent', 'instalment_amount', 'interval',
        ];
    }

    public function exportColumns(): array
    {
        return [...$this->columns(), 'stage'];
    }

    public function exportRows(array $context): iterable
    {
        foreach (MicroLoan::query()->whereNotNull('requested_amount')->orderBy('id')->get() as $file) {
            yield [
                (string) $file->project_number, $file->beneficiary, (string) $file->name_en, (string) $file->national_number, (string) $file->mobile,
                (string) $file->partners, (string) $file->profession, (string) $file->address, (string) $file->project_code,
                $file->project_code === null ? (string) $file->funder : '', $file->project_code === null ? (string) $file->city : '',
                $file->currency, $file->rate !== null ? (float) $file->rate : null, (float) $file->requested_amount, (float) $file->seizure_fee,
                (float) $file->recovery_percent, (float) $file->instalment_amount, $file->payment_interval_months, __('micro.stages.'.$file->stage),
            ];
        }
    }

    public function exampleRows(array $context): array
    {
        $project = GlProject::query()->where('is_general_account', false)->orderBy('code')->first();

        return [
            ['3520', 'Beneficiary full name', 'Name in English', '0102030405', '0944 000 000', '', 'Upholstery', 'Aleppo', $project?->code ?? 'PROJECT', '', '', 'USD', null, 2500, 0, 50, 12.5, 1],
            ['3521', 'Beneficiary full name', '', '', '', '', 'Sewing', 'Aleppo', '', 'Funder', 'Aleppo', 'SYP', 15000, 37500000, 0, 50, 187500, 1],
        ];
    }

    public function referenceSheets(array $context): array
    {
        return [
            __('excel.sheet.projects') => [
                'headings' => [__('excel.col.project'), __('excel.col.name_en'), __('excel.col.donor')],
                'rows' => GlProject::query()->where('is_general_account', false)->orderBy('code')->get(['code', 'name', 'donor'])
                    ->map(fn (GlProject $project): array => [$project->code, (string) $project->name, (string) $project->donor])->all(),
            ],
            __('excel.sheet.funds') => [
                'headings' => [__('excel.col.fund_funder'), __('excel.col.fund_city')],
                'rows' => array_map(fn (array $fund): array => [$fund['funder'], $fund['city']], $this->funds->balances(CarbonImmutable::today())),
            ],
        ];
    }

    protected function check(array $cells, array $before, User $user, array $context): array
    {
        $problems = [];
        $existing = $this->existing($cells);

        if ($cells['project_number'] !== '' && in_array($cells['project_number'], array_column($before, 'project_number'), true)) {
            $problems[] = __('excel.twice');
        }

        $items = $this->itemsFor($cells['project_number'], $context);

        // A file already sent for approval is changed on the screen only: an export imported again leaves it as it is.
        if ($existing !== null && ! in_array($existing->stage, MicroLoan::EDITABLE, true)) {
            return $items === []
                ? ['action' => 'skip', 'problems' => $problems]
                : ['action' => 'skip', 'problems' => [...$problems, __('excel.micro_file_sent', ['stage' => __('micro.stages.'.$existing->stage)])]];
        }

        foreach ($items as $item) {
            if ($item['condition'] === null) {
                $problems[] = __('excel.bad_condition', ['row' => $item['row']]);
            }
        }

        if ($problems !== []) {
            return ['action' => $existing !== null ? 'update' : 'new', 'problems' => $problems];
        }

        // Save it for real, then undo: every rule of the file is checked.
        DB::beginTransaction();

        try {
            $this->saveFile($cells, $user, $existing, $context);
        } catch (LoanException $refused) {
            $problems = $refused->problems;
        } finally {
            DB::rollBack();
        }

        return ['action' => $existing !== null ? 'update' : 'new', 'problems' => $problems];
    }

    protected function saveRow(array $cells, User $user, array $context): void
    {
        try {
            $this->saveFile($cells, $user, $this->existing($cells), $context);
        } catch (LoanException $refused) {
            throw new ExcelException($refused->problems);
        }
    }

    /** The purchase list, an optional second sheet: one row per item, under the file's project number. */
    public function extraSheet(): ?array
    {
        return ['key' => 'micro_items', 'columns' => ['project_number', 'item', 'item_kind', 'quantity', 'condition']];
    }

    public function extraExampleRows(array $context): array
    {
        return [
            ['3520', 'Sewing machine', 'Juki', 1, 'new'],
            ['3520', 'Work table', '', 2, 'used'],
            ['3521', 'Industrial oven', '', 1, 'new'],
        ];
    }

    public function extraExportRows(array $context): iterable
    {
        foreach (MicroLoan::query()->whereNotNull('requested_amount')->with('items')->orderBy('id')->get() as $file) {
            foreach ($file->items as $item) {
                yield [(string) $file->project_number, $item->name, (string) $item->kind, (float) $item->quantity, $item->condition];
            }
        }
    }

    /** Every item must belong to a file of the main sheet, by its project number. */
    public function extraProblems(array $read, array $context): array
    {
        $numbers = array_filter(array_map(fn (array $row): string => trim($row['cells'][0] ?? ''), $read));
        $problems = [];

        foreach ($context['extra'] ?? [] as $row) {
            $number = trim($row['cells'][0] ?? '');
            if (! in_array($number, $numbers, true)) {
                $problems[] = __('excel.item_without_file', ['row' => $row['row'], 'number' => $number !== '' ? $number : '—']);
            }
        }

        return $problems;
    }

    /**
     * The purchase list a file takes from the second sheet, with each row's number in the sheet; a condition that is
     * neither new nor used is null.
     *
     * @param  array<string, mixed>  $context
     * @return list<array{row: int, name: string, kind: string, quantity: string, condition: string|null}>
     */
    private function itemsFor(string $projectNumber, array $context): array
    {
        if ($projectNumber === '') {
            return [];
        }

        $items = [];

        foreach ($context['extra'] ?? [] as $row) {
            $cells = array_map(fn (mixed $cell): string => trim((string) $cell), $row['cells']);
            if ($cells[0] !== $projectNumber) {
                continue;
            }

            $condition = mb_strtolower($cells[4] ?? '');
            $items[] = [
                'row' => (int) $row['row'], 'name' => $cells[1] ?? '', 'kind' => $cells[2] ?? '', 'quantity' => $cells[3] ?? '',
                'condition' => match ($condition) {
                    '', 'new', 'جديد' => MicroItem::NEW,
                    'used', 'مستعمل' => MicroItem::USED,
                    default => null,
                },
            ];
        }

        return $items;
    }

    /**
     * @param  array<string, string>  $cells
     * @param  array<string, mixed>  $context
     *
     * @throws LoanException
     */
    private function saveFile(array $cells, User $user, ?MicroLoan $existing, array $context): void
    {
        $funding = $cells['project'] !== '' ? 'project:'.$cells['project'] : 'fund:'.$cells['fund_funder'].'|'.$cells['fund_city'];
        $fromSheet = $this->itemsFor($cells['project_number'], $context);
        // The purchase list of the second sheet, when it has the file's items; otherwise a changed file keeps its own.
        $items = $fromSheet !== []
            ? array_map(fn (array $item): array => ['name' => $item['name'], 'kind' => $item['kind'], 'quantity' => $item['quantity'], 'condition' => (string) $item['condition']], $fromSheet)
            : ($existing?->items()->get()->map(fn (MicroItem $item): array => [
                'name' => $item->name, 'kind' => (string) $item->kind, 'quantity' => (string) $item->quantity, 'condition' => $item->condition,
            ])->all() ?? []);

        $this->files->save($user, $existing, [
            'beneficiary' => $cells['beneficiary'], 'name_en' => $cells['name_en'], 'national_number' => $cells['national_number'], 'mobile' => $cells['mobile'],
            'partners' => $cells['partners'], 'project_number' => $cells['project_number'], 'profession' => $cells['profession'], 'address' => $cells['address'],
            'funding' => $funding, 'currency' => strtoupper($cells['currency'] !== '' ? $cells['currency'] : 'USD'), 'rate' => $cells['rate'],
            'requested_amount' => $cells['requested_amount'], 'seizure_fee' => $cells['seizure_fee'],
            'recovery_percent' => $cells['recovery_percent'] !== '' ? $cells['recovery_percent'] : '50', 'instalment_amount' => $cells['instalment_amount'],
            'interval' => $cells['interval'] !== '' ? $cells['interval'] : '1',
        ], $items);
    }

    /** @param  array<string, string>  $cells */
    private function existing(array $cells): ?MicroLoan
    {
        return $cells['project_number'] === ''
            ? null
            : MicroLoan::query()->where('project_number', $cells['project_number'])->whereNotNull('requested_amount')->orderByDesc('id')->first();
    }
}
