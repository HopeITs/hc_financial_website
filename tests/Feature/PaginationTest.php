<?php

use App\Enums\RoleId;
use App\Livewire\ReferenceList;
use App\Livewire\Users;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    createLegacyHomeSchema();
    createMasterDataSchema();

    DB::table('role')->insertOrIgnore(['ID' => 4, 'Name' => 'Financial Manager']);
});

function pagingUser(string $name = 'Ann'): User
{
    return User::query()->findOrFail(createFinanceUser($name, 'secret pass', RoleId::HeadOffice->name, RoleId::HeadOffice->value));
}

it('shows the rest of a reference list on page 2', function () {
    foreach (range(1, 30) as $id) {
        DB::connection('legacy')->table('donor')->insert(['ID' => $id, 'Name' => 'Donor '.str_pad((string) $id, 2, '0', STR_PAD_LEFT), 'Abb_Name' => null]);
    }

    $page = Livewire::actingAs(pagingUser())->test(ReferenceList::class, ['screen' => 'donors']);

    expect($page->viewData('page')->lastPage())->toBe(2);

    $page->call('gotoPage', 2);

    expect($page->viewData('page')->currentPage())->toBe(2)
        ->and($page->viewData('page')->count())->toBe(5);
});

it('goes back to the first page when a reference list is searched', function () {
    foreach (range(1, 30) as $id) {
        DB::connection('legacy')->table('donor')->insert(['ID' => $id, 'Name' => 'Donor '.str_pad((string) $id, 2, '0', STR_PAD_LEFT), 'Abb_Name' => null]);
    }

    $page = Livewire::actingAs(pagingUser())->test(ReferenceList::class, ['screen' => 'donors'])
        ->call('gotoPage', 2)
        ->set('search', 'Donor');

    expect($page->viewData('page')->currentPage())->toBe(1);
});

it('pages the users directory and returns to page 1 when filtering', function () {
    $head = pagingUser('Aaa');
    foreach (range(1, 29) as $n) {
        createFinanceUser('User'.str_pad((string) $n, 2, '0', STR_PAD_LEFT), 'secret pass', RoleId::Accountant->name, RoleId::Accountant->value);
    }

    $page = Livewire::actingAs($head)->test(Users::class);

    expect($page->viewData('people')->total())->toBe(30)
        ->and($page->viewData('people')->count())->toBe(25);

    $page->call('gotoPage', 2);

    expect($page->viewData('people')->currentPage())->toBe(2)
        ->and($page->viewData('people')->count())->toBe(5);

    $page->set('search', 'User');

    expect($page->viewData('people')->currentPage())->toBe(1);
});
