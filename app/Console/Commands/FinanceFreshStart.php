<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Starts the web program's books again from nothing (finance request of 2026-11-30), keeping only the chart of
 * accounts and the users. Everything entered in the web program goes: vouchers, requests, purchases, micro files,
 * donors and their agreements, projects and budget lines, exchange rates, payroll and the month-end work.
 *
 * Kept: the chart (`gl_accounts`), cities, currencies, allocation rates, the general (city) accounts in `gl_projects`
 * with their budget lines, and every user table (roles, permissions, profiles, preferences). The Windows program's
 * own tables are never touched. Without --force it only counts what would go.
 */
class FinanceFreshStart extends Command
{
    protected $signature = 'finance:fresh-start {--force : Really delete, after typing the database name}';

    protected $description = 'Delete everything entered in the web program, keeping only the chart of accounts and the users';

    /**
     * Emptied completely, children before their parents.
     *
     * @var list<string>
     */
    public const EMPTIED = [
        'gl_voucher_attachments', 'gl_voucher_audit', 'gl_voucher_lines', 'gl_cash_till_merges', 'gl_vouchers', 'gl_voucher_sequences',
        'gl_month_close_log', 'gl_month_closes', 'gl_month_counts', 'gl_cash_counts', 'gl_revaluations',
        'gl_daily_rates', 'gl_monthly_rates',
        'gl_request_approvals', 'gl_request_audit', 'gl_request_quotes', 'gl_request_lines', 'gl_requests',
        'gl_purchase_quote_prices', 'gl_purchase_quotes', 'gl_purchase_items', 'gl_purchases',
        'gl_micro_payments', 'gl_micro_purchases', 'gl_micro_quotes', 'gl_micro_items', 'gl_micro_instalments', 'gl_micro_loans',
        'gl_fund_exchanges',
        'gl_contract_audit', 'gl_contract_receipts', 'gl_contract_reports', 'gl_contract_instalments', 'gl_contract_projects', 'gl_contracts', 'gl_donors',
        'gl_payroll_splits', 'gl_payroll_lines', 'gl_payrolls', 'gl_staff', 'gl_recurring_payments',
        'gl_revenue_lines', 'gl_revenue_items', 'gl_revenue_sheets',
        'gl_budget_line_notes', 'gl_program_projects', 'gl_programs',
        'gl_import_issues', 'gl_import_batches', 'gl_legacy_map',
        'web_notifications', 'web_assistant_proposals', 'web_assistant_messages', 'web_form_drafts',
    ];

    public function handle(): int
    {
        $plan = $this->plan();
        $this->table(['Table', 'Rows to delete'], array_map(fn (string $table, int $rows): array => [$table, number_format($rows)], array_keys($plan), $plan));

        if (! $this->option('force')) {
            $this->components->info('Nothing was deleted. Take a backup, then run again with --force.');

            return self::SUCCESS;
        }

        $database = (string) DB::connection()->getDatabaseName();
        if ($this->ask("Type the database name ({$database}) to delete these rows") !== $database) {
            $this->components->error('The name did not match. Nothing was deleted.');

            return self::FAILURE;
        }

        Schema::withoutForeignKeyConstraints(function (): void {
            DB::transaction(function (): void {
                foreach (self::EMPTIED as $table) {
                    if (Schema::hasTable($table)) {
                        DB::table($table)->delete();
                    }
                }

                foreach ($this->projectRows() as $query) {
                    $query->delete();
                }
            });
        });

        $this->components->info('Done. The chart of accounts and the users are kept; enter the opening balances next.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, int> each table and how many of its rows would go
     */
    public function plan(): array
    {
        $plan = [];
        foreach (self::EMPTIED as $table) {
            if (Schema::hasTable($table)) {
                $plan[$table] = DB::table($table)->count();
            }
        }

        foreach ($this->projectRows() as $table => $query) {
            $plan[$table] = $query->count();
        }

        return $plan;
    }

    /**
     * The rows of the projects that go (every project but the general city accounts), their budget lines, and the
     * projects given to users that no longer exist.
     *
     * @return array<string, \Illuminate\Database\Query\Builder>
     */
    private function projectRows(): array
    {
        $kept = DB::table('gl_projects')->where('is_general_account', true)->select('code');

        // Budget lines before their projects, which they point at.
        $rows = [
            'gl_budget_lines' => DB::table('gl_budget_lines')->whereNotIn('project_code', $kept),
            'gl_projects' => DB::table('gl_projects')->where('is_general_account', false),
        ];

        if (Schema::hasTable('web_user_projects')) {
            $rows['web_user_projects'] = DB::table('web_user_projects')->whereNotIn('project_code', $kept);
        }

        return $rows;
    }
}
