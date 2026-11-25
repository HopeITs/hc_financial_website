<?php

namespace App\Services\Loans;

use App\Enums\FundSource;
use App\Models\GlCity;
use App\Models\GlProject;
use App\Models\MicroLoan;
use App\Models\User;
use App\Services\Excel\ExcelException;
use App\Services\Excel\ExcelSheet;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The old micro projects brought in for follow-up (finance request of 2026-11-27): about 3,000 loans given before the
 * web program, in every city, from one Excel file of two sheets: the projects, and each one's instalments with what was
 * paid on them. Only what follow-up needs comes in (no quotes, purchases or approvals), and no voucher is posted: what
 * the beneficiaries still owe enters 1204 through the opening balances. Repayments received from then on post the usual
 * way, at the loan's own rate, and the last one clears the loan's dollars exactly.
 *
 * A project already imported (the same project number) is skipped, so the same file can be sent again after adding
 * projects to it. The whole file is saved, or nothing.
 */
class LegacyMicroImport
{
    public const MAX_PROJECTS = 5000;

    public const MAX_INSTALMENTS = 100000;

    /** The most problems shown at once; the count says how many there are in all. */
    public const SHOWN_PROBLEMS = 100;

    /** How far the instalments may be from the total × the recovery percent, as a part of the loan (rounding of old files). */
    private const TOLERANCE = '0.01';

    public const PROJECT_COLUMNS = [
        'project_number', 'beneficiary', 'national_number', 'mobile', 'profession', 'city', 'project', 'funder', 'currency',
        'total_amount', 'recovery_percent', 'rate', 'loan_date', 'interval',
    ];

    public const INSTALMENT_COLUMNS = ['project_number', 'due_date', 'amount', 'paid_amount'];

    public function __construct(private readonly ExcelSheet $sheet) {}

    /**
     * A template to fill: the two sheets with an example project, and the projects and cities to choose from.
     *
     * @return string the path of the file written
     */
    public function template(bool $rtl): string
    {
        $project = GlProject::query()->where('is_general_account', false)->orderBy('code')->first();
        $day = CarbonImmutable::parse('2024-01-15');

        return $this->sheet->write([
            __('legacy_micro.sheet.projects') => [
                'headings' => $this->headings(self::PROJECT_COLUMNS),
                'rows' => [
                    ['P-0001', 'Beneficiary name', '', '', 'Grocery', (string) ($project?->city ?? 'Aleppo'), $project?->code ?? '', '', 'USD', 1000, 50, null, $day->toDateString(), 1],
                ],
            ],
            __('legacy_micro.sheet.instalments') => [
                'headings' => $this->headings(self::INSTALMENT_COLUMNS),
                'rows' => array_map(fn (int $month): array => ['P-0001', $day->addMonthsNoOverflow($month)->toDateString(), 100, $month <= 2 ? 100 : 0], range(1, 5)),
            ],
            __('excel.sheet.projects') => [
                'headings' => [__('excel.col.project'), __('excel.col.name_en'), __('legacy_micro.col.funder'), __('legacy_micro.col.city')],
                'rows' => GlProject::query()->where('is_general_account', false)->orderBy('code')->get(['code', 'name', 'donor', 'city'])
                    ->map(fn (GlProject $row): array => [$row->code, (string) $row->name, (string) $row->donor, (string) $row->city])->all(),
            ],
            __('legacy_micro.sheet.cities') => [
                'headings' => [__('legacy_micro.col.city'), __('legacy_micro.col.city_ar')],
                'rows' => GlCity::query()->orderBy('name')->get(['name', 'name_ar'])->map(fn (GlCity $city): array => [$city->name, (string) $city->name_ar])->all(),
            ],
        ], $rtl);
    }

    /**
     * Reads a filled file and checks every project and instalment, without saving anything.
     *
     * @return array{projects: int, new: int, skipped: int, instalments: int, owed: array<string, string>, problems: list<string>, problem_count: int}
     *
     * @throws InvalidArgumentException when the file cannot be read
     */
    public function review(string $path): array
    {
        $checked = $this->check($path);

        return [
            'projects' => $checked['projects'],
            'new' => count($checked['loans']),
            'skipped' => $checked['skipped'],
            'instalments' => array_sum(array_map(fn (array $loan): int => count($loan['instalments']), $checked['loans'])),
            'owed' => $checked['owed'],
            'problems' => array_slice($checked['problems'], 0, self::SHOWN_PROBLEMS),
            'problem_count' => count($checked['problems']),
        ];
    }

    /**
     * Saves every new project of a file with its instalments, or nothing when one is wrong.
     *
     * @return int how many projects were saved
     *
     * @throws ExcelException when a project or an instalment is wrong
     * @throws InvalidArgumentException when the file cannot be read
     */
    public function save(string $path, User $user): int
    {
        $checked = $this->check($path);

        if ($checked['problems'] !== []) {
            throw new ExcelException(array_slice($checked['problems'], 0, self::SHOWN_PROBLEMS));
        }

        $now = CarbonImmutable::now();
        $userId = (int) $user->getAuthIdentifier();

        DB::transaction(function () use ($checked, $now, $userId): void {
            foreach ($checked['loans'] as $loan) {
                $instalments = $loan['instalments'];
                unset($loan['instalments']);

                $saved = MicroLoan::query()->create($loan + ['stage' => MicroLoan::ACTIVE, 'imported_at' => $now, 'created_by' => $userId]);

                foreach (array_chunk($instalments, 500) as $chunk) {
                    DB::table('gl_micro_instalments')->insert(array_map(fn (array $instalment): array => $instalment + [
                        'loan_id' => $saved->id, 'written_off_amount' => 0, 'paid_voucher_id' => null, 'created_at' => $now, 'updated_at' => $now,
                    ], $chunk));
                }
            }
        });

        return count($checked['loans']);
    }

    /**
     * @return array{projects: int, skipped: int, loans: list<array<string, mixed>>, owed: array<string, string>, problems: list<string>}
     *
     * @throws InvalidArgumentException
     */
    private function check(string $path): array
    {
        $projectRows = $this->sheet->read($path, count(self::PROJECT_COLUMNS), self::MAX_PROJECTS);
        $instalmentRows = $this->sheet->readNamed($path, [__('legacy_micro.sheet.instalments', [], 'ar'), __('legacy_micro.sheet.instalments', [], 'en')], count(self::INSTALMENT_COLUMNS), self::MAX_INSTALMENTS);

        $projects = GlProject::query()->where('is_general_account', false)->get(['code', 'donor', 'city'])->keyBy(fn (GlProject $project): string => mb_strtoupper($project->code));
        $cities = $this->cities();
        $existing = MicroLoan::query()->whereNotNull('project_number')->pluck('imported_at', 'project_number')->all();

        $problems = [];
        $schedules = [];

        foreach ($instalmentRows as $row) {
            $cells = array_combine(self::INSTALMENT_COLUMNS, $row['cells']);
            $where = __('legacy_micro.where.instalment', ['row' => $row['row']]);
            $number = $cells['project_number'];
            $amount = $this->amount($cells['amount']);
            $paid = $cells['paid_amount'] === '' ? '0.00' : $this->amount($cells['paid_amount']);
            $wrong = [];

            if ($number === '') {
                $wrong[] = __('legacy_micro.problem.number');
            }
            if (! $this->isDay($cells['due_date'])) {
                $wrong[] = __('legacy_micro.problem.due_date');
            }
            if ($amount === null || bccomp($amount, '0', 2) <= 0) {
                $wrong[] = __('legacy_micro.problem.instalment_amount');
            }
            if ($paid === null || ($amount !== null && bccomp($paid, $amount, 2) > 0)) {
                $wrong[] = __('legacy_micro.problem.paid_amount');
            }

            if ($wrong !== []) {
                array_push($problems, ...array_map(fn (string $problem): string => $where.': '.$problem, $wrong));

                continue;
            }

            $schedules[$number][] = ['due_date' => $cells['due_date'], 'amount' => $amount, 'paid_amount' => $paid];
        }

        $loans = [];
        $seen = [];
        $skipped = 0;
        $owed = [];

        foreach ($projectRows as $row) {
            $cells = array_combine(self::PROJECT_COLUMNS, $row['cells']);
            $number = $cells['project_number'];
            $where = __('legacy_micro.where.project', ['row' => $row['row'], 'number' => $number]);

            if ($number !== '' && isset($seen[$number])) {
                $problems[] = $where.': '.__('legacy_micro.problem.duplicate', ['row' => $seen[$number]]);

                continue;
            }
            if ($number !== '') {
                $seen[$number] = $row['row'];
            }

            if ($number !== '' && array_key_exists($number, $existing)) {
                if ($existing[$number] !== null) {
                    $skipped++;
                } else {
                    $problems[] = $where.': '.__('legacy_micro.problem.taken');
                }

                continue;
            }

            ['loan' => $loan, 'problems' => $wrong] = $this->loan($cells, $schedules[$number] ?? [], $projects, $cities);

            if ($wrong !== []) {
                array_push($problems, ...array_map(fn (string $problem): string => $where.': '.$problem, $wrong));

                continue;
            }

            $loans[] = $loan;
            $left = bcsub($loan['loan_amount'], $loan['legacy_paid'], 2);
            $owed[$loan['currency']] = bcadd($owed[$loan['currency']] ?? '0.00', $left, 2);
        }

        foreach (array_keys($schedules) as $number) {
            if (! isset($seen[$number])) {
                $problems[] = __('legacy_micro.problem.orphan', ['number' => $number]);
            }
        }

        ksort($owed);

        return ['projects' => count($projectRows), 'skipped' => $skipped, 'loans' => $loans, 'owed' => $owed, 'problems' => $problems];
    }

    /**
     * One project as a loan to save, or what is wrong in it.
     *
     * @param  array<string, string>  $cells
     * @param  list<array{due_date: string, amount: string, paid_amount: string}>  $schedule
     * @param  \Illuminate\Support\Collection<string, GlProject>  $projects
     * @param  array<string, string>  $cities  every spelling of a city to its name
     * @return array{loan: array<string, mixed>, problems: list<string>}
     */
    private function loan(array $cells, array $schedule, $projects, array $cities): array
    {
        $problems = [];

        if ($cells['project_number'] === '') {
            $problems[] = __('legacy_micro.problem.number');
        }
        if ($cells['beneficiary'] === '') {
            $problems[] = __('legacy_micro.problem.beneficiary');
        }

        $project = $cells['project'] !== '' ? $projects->get(mb_strtoupper($cells['project'])) : null;
        if ($cells['project'] !== '' && $project === null) {
            $problems[] = __('legacy_micro.problem.project', ['code' => $cells['project']]);
        }
        if ($cells['project'] === '' && $cells['funder'] === '') {
            $problems[] = __('legacy_micro.problem.funder');
        }

        $city = $cells['city'] !== '' ? ($cities === [] ? $cells['city'] : ($cities[mb_strtolower($cells['city'])] ?? null)) : (string) $project?->city;
        if ($cells['city'] !== '' && $city === null) {
            $problems[] = __('legacy_micro.problem.city', ['city' => $cells['city']]);
        }
        if ($project === null && ($city === null || $city === '')) {
            $problems[] = __('legacy_micro.problem.city_needed');
        }

        $currency = strtoupper($cells['currency'] !== '' ? $cells['currency'] : 'USD');
        if (! in_array($currency, ['USD', 'SYP'], true)) {
            $problems[] = __('legacy_micro.problem.currency');
        }

        $rate = $currency === 'SYP' ? $this->amount($cells['rate'], 6) : '1.000000';
        if ($rate === null || bccomp($rate, '0', 6) <= 0) {
            $problems[] = __('legacy_micro.problem.rate');
        }

        $total = $this->amount($cells['total_amount']);
        if ($total === null || bccomp($total, '0', 2) <= 0) {
            $problems[] = __('legacy_micro.problem.total');
        }

        $percent = $cells['recovery_percent'] !== '' ? $this->amount($cells['recovery_percent']) : null;
        if ($cells['recovery_percent'] !== '' && ($percent === null || bccomp($percent, '100', 2) > 0)) {
            $problems[] = __('legacy_micro.problem.percent');
        }

        if (! $this->isDay($cells['loan_date'])) {
            $problems[] = __('legacy_micro.problem.loan_date');
        }

        $interval = $cells['interval'] !== '' ? $cells['interval'] : '1';
        if (! ctype_digit($interval) || (int) $interval < 1 || (int) $interval > 12) {
            $problems[] = __('legacy_micro.problem.interval');
        }

        usort($schedule, fn (array $a, array $b): int => strcmp($a['due_date'], $b['due_date']));
        $loanAmount = '0.00';
        $paid = '0.00';
        foreach ($schedule as $instalment) {
            $loanAmount = bcadd($loanAmount, $instalment['amount'], 2);
            $paid = bcadd($paid, $instalment['paid_amount'], 2);
        }

        if ($schedule === [] && ($percent === null || bccomp($percent, '0', 2) > 0)) {
            $problems[] = __('legacy_micro.problem.no_instalments');
        }

        if ($total !== null && bccomp($loanAmount, $total, 2) > 0) {
            $problems[] = __('legacy_micro.problem.over_total', ['loan' => $loanAmount, 'total' => $total]);
        } elseif ($total !== null && $percent !== null && $schedule !== []) {
            $expected = bcdiv(bcmul($total, $percent, 4), '100', 2);
            $gap = bcsub($loanAmount, $expected, 2);
            $allowed = bcmul($expected, self::TOLERANCE, 2);
            if (bccomp(ltrim($gap, '-'), bccomp($allowed, '1', 2) > 0 ? $allowed : '1', 2) > 0) {
                $problems[] = __('legacy_micro.problem.percent_gap', ['loan' => $loanAmount, 'expected' => $expected]);
            }
        }

        if ($problems !== []) {
            return ['loan' => [], 'problems' => $problems];
        }

        $percent ??= bccomp($total, '0', 2) > 0 ? bcdiv(bcmul($loanAmount, '100', 4), $total, 2) : '0.00';

        return ['loan' => [
            'project_number' => $cells['project_number'],
            'beneficiary' => $cells['beneficiary'],
            'national_number' => $cells['national_number'] !== '' ? $cells['national_number'] : null,
            'mobile' => $cells['mobile'] !== '' ? $cells['mobile'] : null,
            'profession' => $cells['profession'] !== '' ? $cells['profession'] : null,
            'project_code' => $project?->code,
            'funder' => $project !== null ? (string) $project->donor : $cells['funder'],
            'city' => $city,
            'currency' => $currency,
            'loan_amount' => $loanAmount,
            'grant_amount' => bcsub($total, $loanAmount, 2),
            'recovery_percent' => $percent,
            'instalment_amount' => $schedule[0]['amount'] ?? null,
            'rate' => $currency === 'USD' ? null : $rate,
            'book_usd' => $currency === 'USD' ? $loanAmount : Money::normalize(bcdiv($loanAmount, $rate, 6)),
            'fund_source' => ($project !== null ? FundSource::DonorGrant : FundSource::RecoveredLoans)->value,
            'loan_date' => $cells['loan_date'],
            'first_payment_date' => $schedule[0]['due_date'] ?? null,
            'payment_interval_months' => (int) $interval,
            'legacy_paid' => $paid,
            'instalments' => $schedule,
        ], 'problems' => []];
    }

    /** @return array<string, string> every spelling of a city (English or Arabic, any case) to its name */
    private function cities(): array
    {
        $cities = [];

        foreach (GlCity::query()->get(['name', 'name_ar']) as $city) {
            $cities[mb_strtolower($city->name)] = $city->name;
            if ((string) $city->name_ar !== '') {
                $cities[mb_strtolower((string) $city->name_ar)] = $city->name;
            }
        }

        return $cities;
    }

    /**
     * @param  list<string>  $columns
     * @return list<string>
     */
    private function headings(array $columns): array
    {
        return array_map(fn (string $column): string => __('legacy_micro.col.'.$column), $columns);
    }

    /** @return numeric-string|null */
    private function amount(string $text, int $scale = 2): ?string
    {
        $clean = str_replace([',', ' '], '', trim($text));

        return preg_match('/^\d+(\.\d+)?$/', $clean) === 1 ? Money::normalize($clean, $scale) : null;
    }

    private function isDay(string $text): bool
    {
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) === 1 ? CarbonImmutable::createFromFormat('!Y-m-d', $text) : false;

        return $day instanceof CarbonImmutable && $day->format('Y-m-d') === $text;
    }
}
