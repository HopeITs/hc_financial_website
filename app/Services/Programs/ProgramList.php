<?php

namespace App\Services\Programs;

use App\Contracts\ProjectBalanceSource;
use App\Data\ProgramRow;
use App\Models\GlProgram;
use App\Models\Legacy\Project;
use App\Models\Legacy\SubProject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The programs of the Windows program (read only) and those added on the web, with the number of
 * active projects and the balances of each. Balances come from the same source as the home page.
 */
class ProgramList
{
    public const LEGACY = 'legacy';

    public const WEB = 'web';

    public function __construct(private readonly ProjectBalanceSource $balances) {}

    /**
     * @return Collection<int, ProgramRow> Legacy programs first, then web ones, each by name.
     */
    public function rows(): Collection
    {
        $totals = $this->totals();

        $legacy = Project::query()->orderBy('Name')->get()->map(fn (Project $program): ProgramRow => $this->row(
            self::LEGACY, (int) $program->getKey(), (string) $program->getAttribute('Name'), null, null, $totals[self::LEGACY.':'.$program->getKey()] ?? null,
        ));

        $web = GlProgram::query()->orderBy('name_en')->get()->map(fn (GlProgram $program): ProgramRow => $this->row(
            self::WEB, $program->id, $program->localizedName(), $program->description, $program->status->value, $totals[self::WEB.':'.$program->id] ?? null,
        ));

        return $legacy->concat($web)->values();
    }

    /**
     * Every program a project can be filed under: key (`legacy:1`, `web:2`) => name.
     *
     * @return Collection<string, string>
     */
    public function options(): Collection
    {
        return $this->rows()->mapWithKeys(fn (ProgramRow $row): array => [$row->key() => $row->name]);
    }

    /**
     * The projects (`subproject`) that can be filed, with the program each is filed under now.
     *
     * @return Collection<int, array{id: int, name: string, program: string, linked: bool}>
     */
    public function projects(): Collection
    {
        $names = $this->options();
        $links = $this->links();

        return SubProject::query()->orderBy('Name')->get()->map(function (SubProject $project) use ($names, $links): array {
            $id = (int) $project->getKey();
            $key = $links[$id] ?? self::LEGACY.':'.$project->getAttribute('Project_ID');

            return ['id' => $id, 'name' => (string) $project->getAttribute('Name'), 'program' => (string) ($names[$key] ?? ''), 'linked' => isset($links[$id])];
        })->values();
    }

    /**
     * Whether a program (legacy or web) already has one of these names, compared without case; for the duplicate warning.
     */
    public function nameExists(string ...$names): bool
    {
        $wanted = array_map(fn (string $name): string => mb_strtolower(trim($name)), array_filter($names));

        return Project::query()->pluck('Name')
            ->concat(GlProgram::query()->pluck('name_en'))
            ->concat(GlProgram::query()->pluck('name_ar'))
            ->map(fn ($name): string => mb_strtolower(trim((string) $name)))
            ->intersect($wanted)
            ->isNotEmpty();
    }

    /**
     * subproject id => program key, for the projects filed by hand.
     *
     * @return array<int, string>
     */
    public function links(): array
    {
        return DB::table('gl_program_projects')->get()
            ->mapWithKeys(fn ($link): array => [(int) $link->sub_project_id => $link->program_source.':'.$link->program_id])
            ->all();
    }

    /**
     * Program key => active projects and balances. A project counts under its link when it has one, else under its own program.
     *
     * @return array<string, array{count: int, syp: string, usd: string}>
     */
    private function totals(): array
    {
        $links = $this->links();

        $accounts = DB::connection('legacy')->table('account')
            ->join('subproject', 'subproject.ID', '=', 'account.SubProject_ID')
            ->get(['account.ID as account_id', 'subproject.ID as sub_project_id', 'subproject.Project_ID as program_id'])
            ->keyBy('account_id');

        $totals = [];

        foreach ($this->balances->activeProjects() as $balance) {
            $account = $accounts->get($balance->accountId);

            if ($account === null) {
                continue;
            }

            $key = $links[(int) $account->sub_project_id] ?? self::LEGACY.':'.$account->program_id;

            $total = $totals[$key] ?? ['count' => 0, 'syp' => '0.00', 'usd' => '0.00'];
            $totals[$key] = [
                'count' => $total['count'] + 1,
                'syp' => bcadd($total['syp'], $balance->syp, 2),
                'usd' => bcadd($total['usd'], $balance->usd, 2),
            ];
        }

        return $totals;
    }

    /**
     * @param  array{count: int, syp: string, usd: string}|null  $total
     */
    private function row(string $source, int $id, string $name, ?string $description, ?string $status, ?array $total): ProgramRow
    {
        return new ProgramRow($source, $id, $name, $description, $status, $total['count'] ?? 0, $total['syp'] ?? '0.00', $total['usd'] ?? '0.00');
    }
}
