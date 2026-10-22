<?php

namespace App\Services\Projects;

use App\Data\ProjectRow;
use App\Services\Programs\ProgramList;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The project accounts of the Windows program (`account`) with their city, program and donors, read through
 * the `legacy` connection. Only accounts that appear as projects in `cash_balances` are listed, as on the home page.
 */
class ProjectDirectory
{
    /** @var Collection<string, string> */
    private Collection $programNames;

    /** @var array<int, string> */
    private array $links = [];

    public function __construct(private readonly ProgramList $programs) {}

    /**
     * @param  array{city?: ?int, program?: ?string, donor?: ?int, status?: ?string, search?: ?string}  $filters
     * @param  array<int, int>|null  $limitedTo  Account ids the person is limited to; null for all.
     * @return LengthAwarePaginator<int, ProjectRow>
     */
    public function paginate(array $filters, ?array $limitedTo, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->query();

        if ($limitedTo !== null) {
            $query->whereIn('account.ID', $limitedTo);
        }

        if (! empty($filters['city'])) {
            $query->whereRaw('COALESCE(account.city_id, subproject.City_ID) = ?', [(int) $filters['city']]);
        }

        if (! empty($filters['donor'])) {
            $donor = (int) $filters['donor'];
            $query->whereExists(fn (Builder $sub) => $sub->selectRaw('1')->from('account_donor')
                ->whereColumn('account_donor.Account_id', 'account.ID')->where('account_donor.Donor_id', $donor));
        }

        if (($filters['status'] ?? null) === 'active') {
            $query->where(fn (Builder $sub) => $sub->whereNull('account.IsClosed')->orWhere('account.IsClosed', 0));
        } elseif (($filters['status'] ?? null) === 'closed') {
            $query->where('account.IsClosed', '<>', 0);
        }

        if (filled($filters['search'] ?? null)) {
            $like = '%'.trim((string) $filters['search']).'%';
            $query->where(fn (Builder $sub) => $sub->where('account.Name', 'like', $like)->orWhere('subproject.Name', 'like', $like));
        }

        if (filled($filters['program'] ?? null)) {
            $query->whereIn('account.SubProject_ID', $this->subProjectsOf((string) $filters['program']));
        }

        return $query->orderBy('account.Name')->paginate($perPage)
            ->through(fn (object $row): ProjectRow => $this->row((array) $row));
    }

    public function find(int $accountId): ?ProjectRow
    {
        $row = $this->query()->where('account.ID', $accountId)->first();

        return $row === null ? null : $this->row((array) $row);
    }

    /**
     * @return Collection<int, string> city id => name
     */
    public function cities(): Collection
    {
        return DB::connection('legacy')->table('city')->orderBy('Name')->pluck('Name', 'ID')->map(fn ($name): string => (string) $name);
    }

    /**
     * @return Collection<int, string> donor id => name
     */
    public function donors(): Collection
    {
        return DB::connection('legacy')->table('donor')->orderBy('Name')->pluck('Name', 'ID')->map(fn ($name): string => (string) $name);
    }

    /**
     * Program key (`legacy:1`, `web:2`) => name.
     *
     * @return Collection<string, string>
     */
    public function programOptions(): Collection
    {
        return $this->programs->options();
    }

    /**
     * The project accounts of the same sub-project, for the Accounts tab.
     *
     * @return Collection<int, ProjectRow>
     */
    public function siblings(ProjectRow $project): Collection
    {
        if ($project->subProjectId === null) {
            return collect([$project]);
        }

        return $this->query()->where('account.SubProject_ID', $project->subProjectId)->orderBy('account.Name')->get()
            ->map(fn (object $row): ProjectRow => $this->row((array) $row))->values();
    }

    private function query(): Builder
    {
        $this->links = $this->programs->links();
        $this->programNames = $this->programs->options();

        return DB::connection('legacy')->table('account')
            ->leftJoin('subproject', 'subproject.ID', '=', 'account.SubProject_ID')
            ->leftJoin('city', 'city.ID', '=', DB::raw('COALESCE(account.city_id, subproject.City_ID)'))
            ->whereIn('account.ID', fn (Builder $sub) => $sub->select('Account_ID')->from('cash_balances')->where('Account_Type', 'Project'))
            ->select([
                'account.ID as account_id', 'account.Name as name', 'account.IsClosed as closed', 'account.BeginDate as begin_date', 'account.EndDate as end_date',
                'account.SubProject_ID as sub_project_id', 'subproject.Name as sub_project_name', 'subproject.Project_ID as program_id',
                'city.ID as city_id', 'city.Name as city_name',
            ]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function row(array $row): ProjectRow
    {
        $subProjectId = $row['sub_project_id'] === null ? null : (int) $row['sub_project_id'];
        $key = $subProjectId === null ? null : ($this->links[$subProjectId] ?? ProgramList::LEGACY.':'.$row['program_id']);

        $donors = DB::connection('legacy')->table('account_donor')
            ->join('donor', 'donor.ID', '=', 'account_donor.Donor_id')
            ->where('account_donor.Account_id', $row['account_id'])->orderBy('donor.Name')->pluck('donor.Name')->implode(', ');

        return new ProjectRow(
            accountId: (int) $row['account_id'],
            name: (string) $row['name'],
            cityId: $row['city_id'] === null ? null : (int) $row['city_id'],
            cityName: $row['city_name'],
            subProjectId: $subProjectId,
            subProjectName: $row['sub_project_name'],
            programName: $key === null ? null : ($this->programNames[$key] ?? null),
            donors: $donors,
            closed: (int) $row['closed'] !== 0,
            beginDate: $row['begin_date'] === null ? null : substr((string) $row['begin_date'], 0, 10),
            endDate: $row['end_date'] === null ? null : substr((string) $row['end_date'], 0, 10),
        );
    }

    /**
     * Sub-project ids that sit under a program (`legacy:1`, `web:2`), honouring the links filed by hand.
     *
     * @return list<int>
     */
    private function subProjectsOf(string $key): array
    {
        $links = $this->programs->links();
        $linked = array_keys(array_filter($links, fn (string $target): bool => $target === $key));

        if (! str_starts_with($key, ProgramList::LEGACY.':')) {
            return $linked;
        }

        $own = DB::connection('legacy')->table('subproject')->where('Project_ID', (int) substr($key, strlen(ProgramList::LEGACY) + 1))
            ->pluck('ID')->map(fn ($id): int => (int) $id)->reject(fn (int $id): bool => isset($links[$id]))->all();

        return array_values(array_unique([...$own, ...$linked]));
    }
}
