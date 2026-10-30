<?php

namespace App\Services\Audit;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The audit log (F-14): every voucher audit, request audit and web login in one list, newest first. Each row has a source
 * (voucher, request or login), the time, the person (a user id for audits, a username for logins), the action, the voucher
 * or request it is about and the reason. It is a read-only view over `gl_voucher_audit`, `gl_request_audit` and the Windows
 * program's `log` table (rows the web app wrote at sign-in).
 */
class AuditLog
{
    public const SOURCES = ['voucher', 'request', 'login'];

    /**
     * @param  array{person?: string, action?: string, source?: string, from?: string, to?: string}  $filters
     */
    public function query(array $filters = []): Builder
    {
        $union = DB::table('gl_voucher_audit')
            ->selectRaw("'voucher' as source, created_at as at, changed_by as person_id, null as username, action, voucher_id as subject, reason")
            ->unionAll(DB::table('gl_request_audit')
                ->selectRaw("'request' as source, created_at as at, changed_by as person_id, null as username, action, CAST(request_id AS CHAR) as subject, reason"))
            ->unionAll(DB::table('log')->where('form_name', 'Web_Login')
                ->selectRaw("'login' as source, date as at, null as person_id, user as username, 'login' as action, null as subject, null as reason"));

        $query = DB::query()->fromSub($union, 'a')->select('a.*');

        if (($filters['source'] ?? '') !== '') {
            $query->where('a.source', $filters['source']);
        }

        if (($filters['action'] ?? '') !== '') {
            $query->where('a.action', $filters['action']);
        }

        if (($filters['person'] ?? '') !== '') {
            [$id, $username] = array_pad(explode('|', $filters['person'], 2), 2, '');
            $query->where(fn (Builder $inner) => $inner->where('a.person_id', (int) $id)->orWhere('a.username', $username));
        }

        if (($filters['from'] ?? '') !== '') {
            $query->whereDate('a.at', '>=', $filters['from']);
        }

        if (($filters['to'] ?? '') !== '') {
            $query->whereDate('a.at', '<=', $filters['to']);
        }

        return $query->orderByDesc('a.at')->orderByDesc('a.subject');
    }

    /** @return array<int, string> every action that appears in the log, for the filter */
    public function actions(): array
    {
        $actions = DB::table('gl_voucher_audit')->distinct()->pluck('action')
            ->merge(DB::table('gl_request_audit')->distinct()->pluck('action'))
            ->push('login')->unique()->sort()->values();

        return array_map('strval', $actions->all());
    }
}
