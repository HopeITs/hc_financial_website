<?php

namespace App\Services\MasterData;

use App\Models\Role;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The master-data screens that only show what the Windows program keeps (decision of T38): donors, cities, currencies,
 * denominations, employees, cash tills, fiscal bonds, budget line categories, approval limits and the log. They read
 * through the SELECT-only `legacy` connection (the log through the default one, which the web app also writes to at
 * login) and never write. The ledger's own master data (the chart, the programs and the projects) is edited on its
 * own screens; the old project windows are the tabs of the project page.
 *
 * Each screen is a query, its columns and the columns a search looks in. A column is [key, label, type], the type being
 * text, code, date, number or yesno.
 */
class ReferenceData
{
    public const PER_PAGE = 25;

    /** @var list<string> */
    public const SLUGS = ['donors', 'cities', 'currency', 'denoms', 'employees', 'tills', 'bonds', 'blcats', 'limits', 'log'];

    /** Screens that the project page replaces. */
    public const MERGED = ['projwin', 'budgetlines', 'lifetime'];

    public function has(string $slug): bool
    {
        return in_array($slug, self::SLUGS, true);
    }

    /** @return list<array{0: string, 1: string, 2: string}> */
    public function columns(string $slug): array
    {
        return match ($slug) {
            'donors' => [['name', 'master.col.name', 'text'], ['abbreviation', 'master.col.abbreviation', 'text']],
            'cities' => [['name', 'master.col.name', 'text'], ['projects', 'master.col.accounts', 'number'], ['cash_boxes', 'master.col.cash_boxes', 'number']],
            'currency' => [['name', 'master.col.name', 'text'], ['local', 'master.col.local', 'yesno'], ['divisor', 'master.col.divisor', 'number']],
            'denoms' => [['name', 'master.col.name', 'text'], ['currency', 'master.col.currency', 'text']],
            'employees' => [['name', 'master.col.name', 'text'], ['father', 'master.col.father', 'text'], ['begin', 'master.col.begin', 'date'], ['end', 'master.col.end', 'date']],
            'tills' => [['name', 'master.col.name', 'text'], ['currency', 'master.col.currency', 'text'], ['city', 'master.col.city', 'text']],
            'bonds' => [['name', 'master.col.name', 'text'], ['date', 'master.col.date', 'date'], ['account', 'master.col.account', 'text'], ['notes', 'master.col.notes', 'text']],
            'blcats' => [['name', 'master.col.name', 'text'], ['type', 'master.col.type', 'text'], ['kind', 'master.col.kind', 'number']],
            'limits' => [['role', 'master.col.role', 'text'], ['currency', 'master.col.currency', 'text'], ['limit', 'master.col.limit', 'number']],
            'log' => [['date', 'master.col.date', 'date'], ['user', 'master.col.user', 'text'], ['form', 'master.col.form', 'text'], ['title', 'master.col.title', 'text'], ['details', 'master.col.details', 'text']],
            default => [],
        };
    }

    /**
     * One page of a screen, as rows keyed by the column keys.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function page(string $slug, string $search = '', ?string $from = null, ?string $to = null): LengthAwarePaginator
    {
        [$query, $searchIn] = $this->query($slug);
        $term = trim($search);

        if ($term !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
            $query->where(function (Builder $inner) use ($searchIn, $like) {
                foreach ($searchIn as $column) {
                    $inner->orWhere($column, 'like', $like);
                }
            });
        }

        if ($slug === 'log') {
            $query->when($from !== null, fn (Builder $q) => $q->whereDate('date', '>=', $from))
                ->when($to !== null, fn (Builder $q) => $q->whereDate('date', '<=', $to));
        }

        $page = $query->paginate(self::PER_PAGE);
        $roles = $slug === 'limits' ? $this->roleNames(array_values($page->getCollection()->pluck('role_id')->all())) : [];

        return $page->through(fn (object $row) => $this->row($slug, $row, $roles));
    }

    /**
     * @return array{0: Builder, 1: list<string>} the query and the columns a search looks in
     */
    private function query(string $slug): array
    {
        $legacy = fn (string $table) => DB::connection('legacy')->table($table);

        return match ($slug) {
            'donors' => [$legacy('donor')->select('ID as id', 'Name as name', 'Abb_Name as abbreviation')->orderBy('Name'), ['Name', 'Abb_Name']],
            'cities' => [$legacy('city')->select('city.ID as id', 'city.Name as name')
                ->selectRaw('(select count(*) from account where account.city_id = city.ID) as projects')
                ->selectRaw('(select count(*) from cashaccount where cashaccount.city_id = city.ID) as cash_boxes')
                ->orderBy('city.Name'), ['city.Name']],
            'currency' => [$legacy('tilltypes')->select('ID as id', 'Name as name', 'is_local_currency as local', 'base_divisor as divisor')->orderBy('ID'), ['Name']],
            'denoms' => [$legacy('denominations as d')->leftJoin('tilltypes as t', 't.ID', '=', 'd.currency_tilltype_id')
                ->select('d.id as id', 'd.name as name', 't.Name as currency')->orderBy('t.Name')->orderBy('d.id'), ['d.name', 't.Name']],
            'employees' => [$legacy('employee')->select('id', 'FirstName as first', 'LastName as last', 'FatherName as father', 'BeginDate as begin', 'EndDate as end')
                ->orderBy('FirstName')->orderBy('LastName'), ['FirstName', 'LastName', 'FatherName']],
            'tills' => [$legacy('cashaccount as c')->leftJoin('tilltypes as t', 't.ID', '=', 'c.currency_tilltype_id')->leftJoin('city as y', 'y.ID', '=', 'c.city_id')
                ->select('c.id as id', 'c.Name as name', 't.Name as currency', 'y.Name as city')->orderBy('y.Name')->orderBy('c.Name'), ['c.Name', 'y.Name', 't.Name']],
            'bonds' => [$legacy('fiscalbond as f')->leftJoin('account as a', 'a.ID', '=', 'f.Account_ID')
                ->select('f.ID as id', 'f.Name as name', 'f.Date as date', 'f.Notes as notes', 'a.Name as account')->orderByDesc('f.Date')->orderByDesc('f.ID'), ['f.Name', 'f.Notes', 'a.Name']],
            'blcats' => [$legacy('linecategory as c')->leftJoin('linecategorytype as t', 't.ID', '=', 'c.Type_ID')
                ->select('c.ID as id', 'c.Name as name', 't.Name as type', 'c.Kind as kind')->orderBy('t.Name')->orderBy('c.Name'), ['c.Name', 't.Name']],
            'limits' => [$legacy('role_currency_limit as l')->leftJoin('tilltypes as t', 't.ID', '=', 'l.TillType_ID')
                ->select('l.Role_ID as role_id', 't.Name as currency', 'l.Approval_Limit as limit')->orderBy('l.Role_ID')->orderBy('t.Name'), ['t.Name']],
            default => [DB::table('log')->select('id', 'date', 'user', 'form_name as form', 'title', 'details')->orderByDesc('id'), ['user', 'form_name', 'title', 'details']],
        };
    }

    /**
     * @param  array<int, string>  $roles
     * @return array<string, mixed>
     */
    private function row(string $slug, object $row, array $roles): array
    {
        $values = (array) $row;

        if ($slug === 'employees') {
            $values['name'] = trim(($values['first'] ?? '').' '.($values['last'] ?? ''));
        }

        if ($slug === 'currency') {
            $values['local'] = (bool) $values['local'];
            $values['divisor'] = rtrim(rtrim(number_format((float) $values['divisor'], 6, '.', ''), '0'), '.');
        }

        if ($slug === 'limits') {
            $values['role'] = $roles[(int) $values['role_id']] ?? (string) $values['role_id'];
            $values['limit'] = number_format((float) $values['limit'], 2);
        }

        return $values;
    }

    /**
     * @param  list<mixed>  $ids
     * @return array<int, string>
     */
    private function roleNames(array $ids): array
    {
        $names = [];

        foreach (Role::query()->whereIn('ID', $ids)->get() as $role) {
            $names[(int) $role->getKey()] = $role->name;
        }

        return $names;
    }
}
