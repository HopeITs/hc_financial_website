<?php

use App\Enums\RoleId;
use App\Livewire\AuditLog;
use App\Models\User;
use App\Services\Audit\AuditLog as AuditLogQuery;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (require database_path('migrations/2026_10_25_090000_create_request_module_tables.php'))->up();
    DB::statement('PRAGMA foreign_keys = OFF');

    DB::table('gl_vouchers')->insert(['id' => 'CRV-2026-0001', 'type' => 'CRV', 'date' => '2026-03-10', 'status' => 'approved', 'prepared_by' => 5, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('gl_voucher_audit')->insert([
        ['voucher_id' => 'CRV-2026-0001', 'action' => 'create', 'changed_by' => 5, 'created_at' => '2026-03-10 09:00:00'],
        ['voucher_id' => 'CRV-2026-0001', 'action' => 'approve', 'changed_by' => 9, 'created_at' => '2026-03-11 10:30:00'],
    ]);
    DB::table('gl_request_audit')->insert(['request_id' => 12, 'action' => 'return', 'changed_by' => 9, 'reason' => 'Add a quote', 'created_at' => '2026-03-12 08:00:00']);
    DB::table('log')->insert([
        ['form_name' => 'Web_Login', 'title' => 'LOGIN', 'affected_table' => '', 'affected_id' => '', 'details' => '', 'additionaldetails' => '', 'query' => '', 'date' => '2026-03-13', 'user' => 'ann'],
        ['form_name' => 'Other', 'title' => 'X', 'affected_table' => '', 'affected_id' => '', 'details' => '', 'additionaldetails' => '', 'query' => '', 'date' => '2026-03-13', 'user' => 'ann'],
    ]);
});

it('lists voucher audits, request audits and web logins in one list, newest first, and ignores other log rows', function () {
    $rows = app(AuditLogQuery::class)->query()->get();

    expect($rows->pluck('source')->all())->toBe(['login', 'request', 'voucher', 'voucher'])
        ->and($rows[1]->reason)->toBe('Add a quote')
        ->and($rows[1]->subject)->toBe('12')
        ->and($rows[0]->username)->toBe('ann');
});

it('filters by person, action, source and date', function () {
    $log = app(AuditLogQuery::class);

    expect($log->query(['person' => '9|nobody'])->pluck('action')->all())->toBe(['return', 'approve'])
        ->and($log->query(['person' => '0|ann'])->pluck('source')->all())->toBe(['login'])
        ->and($log->query(['action' => 'create'])->count())->toBe(1)
        ->and($log->query(['source' => 'request'])->count())->toBe(1)
        ->and($log->query(['from' => '2026-03-11', 'to' => '2026-03-12'])->count())->toBe(2)
        ->and($log->actions())->toBe(['approve', 'create', 'login', 'return']);
});

it('opens for the Financial Manager and Head Office, shows the rows with links, exports CSV, and is closed to an accountant', function () {
    $fm = User::query()->findOrFail(createFinanceUser('fatima', 'secret pass', RoleId::FinancialManager->name, RoleId::FinancialManager->value));
    $head = User::query()->findOrFail(createFinanceUser('hana', 'secret pass', RoleId::HeadOffice->name, RoleId::HeadOffice->value));
    $accountant = User::query()->findOrFail(createFinanceUser('ann', 'secret pass', RoleId::Accountant->name, RoleId::Accountant->value));

    $this->actingAs($fm)->get('/people/audit-log')->assertOk();
    $this->actingAs($accountant)->get('/people/audit-log')->assertForbidden();

    Livewire::actingAs($head)->test(AuditLog::class)
        ->assertSee('CRV-2026-0001')
        ->assertSee('Add a quote')
        ->assertSeeHtml('data-test="audit-rows"')
        ->set('source', 'request')->assertDontSee('CRV-2026-0001')
        ->call('export')->assertFileDownloaded('audit-log.csv');
});
