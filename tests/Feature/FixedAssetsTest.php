<?php

use App\Enums\RoleId;
use App\Livewire\FixedAssetsScreen;
use App\Models\AssetCategory;
use App\Models\AssetCount;
use App\Models\AssetMovement;
use App\Models\FixedAsset;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherLine;
use App\Services\Assets\FixedAssets;
use App\Services\Entries\EntryException;
use App\Services\Ledger\VoucherApprovals;
use Livewire\Livewire;

/*
 * The warehouses and fixed-asset register (finance request of 2026-11-28).
 */

beforeEach(function () {
    createFinanceSchema();
    seedChartWithTestProjects();
    (require database_path('migrations/2026_12_03_090000_create_fixed_assets.php'))->up();
    $this->head = User::query()->findOrFail(createFinanceUser('Hoa', 'secret pass'));
    $this->assets = app(FixedAssets::class);
    $this->store = $this->assets->saveWarehouse(null, ['name' => 'Aleppo store', 'city' => 'Aleppo', 'center' => 'Science Zone Aleppo', 'keeper' => 'Rami']);
    $this->computers = (string) AssetCategory::query()->where('account_code', '1403')->value('id');
});

function faUser(RoleId $role, string $name): User
{
    return User::query()->findOrFail(createFinanceUser($name, 'secret pass', $role->name, $role->value));
}

/** @return list<array{0: string, 1: string, 2: string}> */
function faLines(string $voucherId): array
{
    return VoucherLine::query()->where('voucher_id', $voucherId)->get()
        ->map(fn (VoucherLine $line) => [$line->account_code, $line->debit_orig, $line->credit_orig])->all();
}

it('registers an asset, hands it over and back, depreciates it monthly and sells it, putting it back when the sale is rejected', function () {
    $laptop = $this->assets->register($this->head, [
        'source' => 'opening', 'name' => 'Laptop', 'category' => $this->computers, 'project' => 'F2F', 'warehouse' => (string) $this->store->id,
        'acquired_on' => '2026-01-15', 'cost' => '1200', 'currency' => 'USD',
    ]);

    expect($laptop->tag)->toStartWith('FA-')
        ->and($laptop->center)->toBe('Science Zone Aleppo')
        ->and($laptop->life_months)->toBe(36)
        ->and($laptop->depreciation_from)->toBe('2026-02')
        ->and($laptop->voucher_id)->toBeNull();

    $laptop = $this->assets->move($this->head, $laptop, 'handover', ['date' => '2026-01-20', 'custodian' => 'Sami']);
    expect($laptop->status)->toBe(FixedAsset::IN_USE)->and($laptop->custodian)->toBe('Sami')
        ->and(fn () => $this->assets->move($this->head, $laptop, 'return', ['date' => '2026-01-21']))->toThrow(EntryException::class);
    $laptop = $this->assets->move($this->head, $laptop, 'return', ['date' => '2026-01-25', 'warehouse' => (string) $this->store->id]);
    expect($laptop->status)->toBe(FixedAsset::IN_STORE)->and($laptop->custodian)->toBeNull();

    // January is before it starts; February is 1200 / 36.
    expect($this->assets->depreciationFor('2026-01'))->toBe([]);
    $depreciation = $this->assets->depreciate($this->head, '2026-02');
    expect(faLines($depreciation->id))->toEqualCanonicalizing([['5703', '33.33', '0.00'], ['1490', '0.00', '33.33']])
        ->and(fn () => $this->assets->depreciate($this->head, '2026-02'))->toThrow(EntryException::class)
        ->and($this->assets->bookValue($laptop))->toBe('1166.67');

    // Sold for 1000: the loss is what was left of it less the price.
    $sale = $this->assets->sell($this->head, $laptop, ['date' => '2026-03-10', 'price' => '1000', 'cash_account' => '1111', 'buyer' => 'Omar']);
    expect(faLines($sale->id))->toEqualCanonicalizing([['1111', '1000.00', '0.00'], ['1490', '33.33', '0.00'], ['1403', '0.00', '1200.00'], ['5704', '166.67', '0.00']])
        ->and($laptop->refresh()->status)->toBe(FixedAsset::SOLD)
        ->and($this->assets->depreciationFor('2026-03'))->toBe([]);

    app(VoucherApprovals::class)->reject(Voucher::query()->findOrFail($sale->id), faUser(RoleId::FinancialManager, 'Fatima'), 'Wrong price');
    expect($laptop->refresh()->status)->toBe(FixedAsset::IN_STORE)
        ->and(AssetMovement::query()->where('asset_id', $laptop->id)->where('kind', 'sell')->exists())->toBeFalse();
});

it('books a gift in kind as income and writes off what is lost, with its count', function () {
    $desks = (string) AssetCategory::query()->where('account_code', '1402')->value('id');
    $desk = $this->assets->register($this->head, [
        'source' => 'donation', 'name' => 'Desk', 'category' => $desks, 'project' => 'F2F', 'warehouse' => (string) $this->store->id,
        'acquired_on' => '2026-02-01', 'cost' => '800', 'currency' => 'USD',
    ]);
    expect(faLines((string) $desk->voucher_id))->toEqualCanonicalizing([['1402', '800.00', '0.00'], ['4001', '0.00', '800.00']])
        ->and(fn () => $this->assets->register($this->head, ['source' => 'opening', 'name' => 'Chair', 'category' => $desks, 'project' => 'F2F', 'acquired_on' => '2026-02-01', 'cost' => '10']))
        ->toThrow(EntryException::class);

    // A count: every asset ticked before it closes.
    $count = $this->assets->openCount($this->head, $this->store, '2026-02-28', 'Rami');
    $line = $count->lines()->sole();
    expect(fn () => $this->assets->closeCount($this->head, $count))->toThrow(EntryException::class);
    $this->assets->markCount($line, false, 'broken', 'Not in the room');
    expect($this->assets->closeCount($this->head, $count->refresh())->status)->toBe(AssetCount::CLOSED)
        ->and(AssetMovement::query()->where('asset_id', $desk->id)->where('kind', 'count')->exists())->toBeTrue();

    $writeOff = $this->assets->writeOff($this->head, $desk->refresh(), ['date' => '2026-03-01', 'reason' => 'Lost']);
    expect(faLines($writeOff->id))->toEqualCanonicalizing([['5704', '800.00', '0.00'], ['1402', '0.00', '800.00']])
        ->and($desk->refresh()->status)->toBe(FixedAsset::WRITTEN_OFF)
        ->and(fn () => $this->assets->writeOff($this->head, $desk, ['date' => '2026-03-02', 'reason' => 'Again']))->toThrow(EntryException::class);
});

it('receives an asset and hands it over on the screen, and prints the handover note', function () {
    $page = Livewire::actingAs($this->head)->test(FixedAssetsScreen::class)
        ->set('tab', 'receive')->set('source', 'opening')
        ->set('form.name', 'Printer')->set('form.category', $this->computers)->set('form.project', 'F2F')
        ->set('form.warehouse', (string) $this->store->id)->set('form.cost', '900')->set('form.acquired_on', '2026-02-10')
        ->call('register')
        ->assertSet('problems', []);

    $printer = FixedAsset::query()->sole();
    $page->set('tab', 'register')->assertSeeHtml('data-test="asset-row-'.$printer->id.'"')
        ->call('openAsset', $printer->id)->assertSeeHtml('data-test="asset-card"')
        ->call('startAction', 'handover')->set('actionForm.custodian', 'Lina')->call('runAction')
        ->assertSet('problems', []);

    $handover = AssetMovement::query()->where('kind', 'handover')->sole();
    expect($printer->refresh()->custodian)->toBe('Lina');
    $this->actingAs($this->head)->get(route('operations.assets.movement', ['movement' => $handover->id]))->assertOk()->assertSee('Lina');

    // Those who do not keep the assets do not reach them.
    $this->actingAs(faUser(RoleId::Employee, 'Emp'))->get(route('operations.assets'))->assertForbidden();
    Livewire::actingAs(faUser(RoleId::Cashier, 'Carl'))->test(FixedAssetsScreen::class)->assertForbidden();
});
