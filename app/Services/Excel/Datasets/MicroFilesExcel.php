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
 * (new or sent back) changes that file and keeps its purchase list; a file already sent for approval is refused.
 * Funding is a project's code, or the funder and the city of an instalment fund.
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

        if ($existing !== null && ! in_array($existing->stage, MicroLoan::EDITABLE, true)) {
            $problems[] = __('excel.micro_file_sent', ['stage' => __('micro.stages.'.$existing->stage)]);
        }

        if ($problems !== []) {
            return ['action' => $existing !== null ? 'update' : 'new', 'problems' => $problems];
        }

        // Save it for real, then undo: every rule of the file is checked.
        DB::beginTransaction();

        try {
            $this->saveFile($cells, $user, $existing);
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
            $this->saveFile($cells, $user, $this->existing($cells));
        } catch (LoanException $refused) {
            throw new ExcelException($refused->problems);
        }
    }

    /**
     * @param  array<string, string>  $cells
     *
     * @throws LoanException
     */
    private function saveFile(array $cells, User $user, ?MicroLoan $existing): void
    {
        $funding = $cells['project'] !== '' ? 'project:'.$cells['project'] : 'fund:'.$cells['fund_funder'].'|'.$cells['fund_city'];
        // A file changed from Excel keeps the purchase list typed on the screen.
        $items = $existing?->items()->get()->map(fn (MicroItem $item): array => [
            'name' => $item->name, 'kind' => (string) $item->kind, 'quantity' => (string) $item->quantity, 'condition' => $item->condition,
        ])->all() ?? [];

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
