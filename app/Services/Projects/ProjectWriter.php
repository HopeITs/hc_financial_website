<?php

namespace App\Services\Projects;

use App\Models\GlBudgetLine;
use App\Models\GlProject;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Saves a project draft and its budget lines in `gl_projects` / `gl_budget_lines`. A line's key (`CODE|name`) is
 * fixed when it is created, because vouchers refer to it; editing a saved line never renames its key.
 */
class ProjectWriter
{
    /**
     * @param  array<string, mixed>  $draft  a validated project draft with its `lines`
     */
    public function save(array $draft, bool $exists): GlProject
    {
        return DB::transaction(function () use ($draft, $exists): GlProject {
            $fields = [
                'name' => trim((string) $draft['name']),
                'contract_number' => $this->blank($draft['contract_number'] ?? null),
                'start_date' => $this->blank($draft['start_date'] ?? null),
                'end_date' => $this->endDate($draft),
                'donor' => $this->existing('donor', $this->blank($draft['donor'] ?? null)),
                'restriction' => $draft['restriction'],
                'city' => $this->existing('city', $this->blank($draft['city'] ?? null)),
                'donor_currency' => $draft['donor_currency'],
                'donor_rate' => $draft['donor_rate'],
            ];

            $project = $exists
                ? tap(GlProject::query()->findOrFail($draft['code']))->update($fields)
                : GlProject::query()->create(['code' => $draft['code'], 'is_general_account' => false] + $fields);

            foreach ($draft['lines'] as $line) {
                $values = [
                    'name' => trim((string) $line['name']),
                    'account_code' => $line['account_code'],
                    'donor' => $this->blank($line['donor'] ?? null),
                    'budget' => $line['budget'],
                    'currency' => $line['currency'],
                ];

                if (filled($line['key'] ?? null)) {
                    GlBudgetLine::query()->whereKey($line['key'])->update($values);
                } else {
                    GlBudgetLine::query()->create(['key' => $project->code.'|'.$values['name'], 'project_code' => $project->code] + $values);
                }
            }

            return $project;
        });
    }

    /** A project runs a year unless told otherwise (R4-5: each donor project is one year from its start). */
    private function endDate(array $draft): ?string
    {
        $end = $this->blank($draft['end_date'] ?? null);
        $start = $this->blank($draft['start_date'] ?? null);

        return $end ?? ($start === null ? null : Carbon::parse($start)->addYear()->subDay()->toDateString());
    }

    /** A donor or city that differs only in case or spacing from a saved one reuses that spelling, so none is listed twice. */
    private function existing(string $column, ?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $match = GlProject::query()->whereNotNull($column)
            ->whereRaw("LOWER(TRIM({$column})) = ?", [mb_strtolower($value)])->orderBy('code')->value($column);

        return $match === null ? $value : trim((string) $match);
    }

    private function blank(mixed $value): ?string
    {
        return filled($value) ? trim((string) $value) : null;
    }
}
