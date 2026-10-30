<?php

use App\Models\GlBudgetLine;
use App\Services\Ledger\OperationEntry;
use App\Services\Ledger\PostVoucher;
use Database\Seeders\ChartOfAccountsSeeder;

beforeEach(function () {
    createFinanceSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (require database_path('migrations/2026_10_25_090000_add_contract_dates_to_gl_projects.php'))->up();
    (new ChartOfAccountsSeeder)->run();
});

function operation(array $with = []): array
{
    return $with + ['kind' => 'income', 'date' => '2026-03-10', 'project' => 'F2F', 'cash' => '1111', 'account' => '4101', 'amount' => '250'];
}

function entryPost(array $operation)
{
    $built = app(OperationEntry::class)->build($operation, 7);

    expect($built->problems)->toBe([])->and($built->draft)->not->toBeNull();

    return app(PostVoucher::class)->post($built->draft);
}

it('turns an income into a receipt: debit the cash account, credit the income account', function () {
    $voucher = entryPost(operation(['description' => 'Grant']));

    expect($voucher->type->value)->toBe('CRV')
        ->and($voucher->description)->toBe('Grant')
        ->and($voucher->lines->map(fn ($line) => [$line->account_code, $line->project_code, $line->debit_usd, $line->credit_usd])->all())
        ->toBe([['1111', 'F2F', '250.00', '0.00'], ['4101', 'F2F', '0.00', '250.00']]);
});

it('books a pound income at the typed rate', function () {
    $voucher = entryPost(operation(['cash' => '1112', 'amount' => '1500000', 'rate' => '15000']));

    expect($voucher->lines[0]->debit_orig)->toBe('1500000.00')
        ->and($voucher->lines[0]->debit_usd)->toBe('100.00')
        ->and($voucher->lines[1]->credit_usd)->toBe('100.00');
});

it('turns an expense into a payment: a budget line fills the expense account and the project', function () {
    $line = GlBudgetLine::query()->orderBy('key')->firstOrFail();

    $voucher = entryPost(operation(['kind' => 'expense', 'budget_line' => $line->key, 'project' => '', 'account' => '', 'amount' => '40', 'document' => 'invoice 12']));

    expect($voucher->type->value)->toBe('CPV')
        ->and($voucher->description)->toBe('invoice 12')
        ->and($voucher->lines[0]->account_code)->toBe($line->account_code)
        ->and($voucher->lines[0]->project_code)->toBe($line->project_code)
        ->and($voucher->lines[0]->debit_usd)->toBe('40.00')
        ->and($voucher->lines[1]->account_code)->toBe('1111')
        ->and($voucher->lines[1]->project_code)->toBe($line->project_code);
});

it('exchanges dollars for pounds at the typed rate: pounds = dollars x rate', function () {
    $voucher = entryPost(operation(['kind' => 'exchange', 'cash' => '1111', 'to_cash' => '1112', 'amount' => '100', 'rate' => '15000', 'account' => '']));

    expect($voucher->type->value)->toBe('EXG')
        ->and($voucher->lines->map(fn ($line) => [$line->account_code, $line->debit_orig, $line->credit_orig, $line->debit_usd, $line->credit_usd])->all())
        ->toBe([['1112', '1500000.00', '0.00', '100.00', '0.00'], ['1111', '0.00', '100.00', '0.00', '100.00']]);
});

it('exchanges pounds for dollars: dollars = pounds / rate, the cents going to 5503', function () {
    $voucher = entryPost(operation(['kind' => 'exchange', 'cash' => '1112', 'to_cash' => '1111', 'amount' => '1000000', 'rate' => '15001', 'account' => '']));

    expect($voucher->lines[0]->account_code)->toBe('1111')
        ->and($voucher->lines[0]->debit_orig)->toBe('66.66')
        ->and($voucher->lines[1]->account_code)->toBe('1112')
        ->and($voucher->lines[1]->credit_usd)->toBe('66.66');
});

it('turns an advance into a payment to 1201 with the person as contact, and a repayment into a receipt', function () {
    $advance = entryPost(operation(['kind' => 'advance', 'account' => '', 'contact' => 'Maya', 'amount' => '60']));
    $repayment = entryPost(operation(['kind' => 'repayment', 'account' => '', 'contact' => 'Maya', 'amount' => '60']));

    expect($advance->type->value)->toBe('CPV')
        ->and($advance->lines[0]->account_code)->toBe('1201')
        ->and($advance->lines[0]->contact)->toBe('Maya')
        ->and($repayment->type->value)->toBe('CRV')
        ->and($repayment->lines[1]->account_code)->toBe('1201');
});

it('reports a bad kind, a cash account that is not cash, and a bad exchange instead of building', function () {
    $entry = app(OperationEntry::class);

    expect($entry->build(operation(['kind' => 'gift']), 7)->problems)->toBe([__('ledger.entry.bad_kind')])
        ->and($entry->build(operation(['cash' => '4101']), 7)->problems)->toBe([__('ledger.entry.bad_cash')])
        ->and($entry->build(operation(['cash' => '']), 7)->draft)->toBeNull()
        ->and($entry->build(operation(['kind' => 'exchange', 'to_cash' => '1121', 'rate' => '15000']), 7)->problems)->toBe([__('ledger.entry.bad_exchange')])
        ->and($entry->build(operation(['kind' => 'exchange', 'to_cash' => '1112', 'rate' => '']), 7)->problems)->toBe([__('ledger.entry.bad_exchange_amount')]);
});

it('leaves the rules to the posting service: a missing tag is a violation, not a crash', function () {
    $built = app(OperationEntry::class)->build(operation(['project' => '']), 7);

    expect($built->problems)->toBe([])
        ->and(collect(app(PostVoucher::class)->check($built->draft))->pluck('rule')->all())->toContain(5);
});
