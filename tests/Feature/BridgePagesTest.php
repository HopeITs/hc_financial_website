<?php

use App\Enums\RoleId;
use App\Livewire\Bridge;
use App\Models\User;
use App\Models\WebUserRole;
use App\Services\Bridge\DecisionsLog;
use App\Services\Bridge\PostingRuleCatalogue;
use App\Services\Ledger\Rules\PostingRule;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    createLegacyHomeSchema();
});

function bridgeUser(RoleId $role, string $name = 'Ann'): User
{
    $legacy = $role->isWebOnly() ? RoleId::Employee : $role;
    $id = createFinanceUser($name, 'secret pass', $legacy->name, $legacy->value);

    if ($role->isWebOnly()) {
        WebUserRole::create(['user_id' => $id, 'role_id' => $role]);
    }

    return User::query()->findOrFail($id);
}

function decisionsFixture(): string
{
    $file = tempnam(sys_get_temp_dir(), 'decisions');
    file_put_contents($file, implode("\n", [
        '# Decisions log',
        '',
        'Intro text that is not part of a section.',
        '',
        '## 1. Round 1',
        '',
        '| ID | Decision | Owner | Status |',
        '|----|----------|-------|--------|',
        '| R1-1 | Two general accounts per city, `GEN-xxx-USD` | FM | Decided |',
        '| R1-2 | A pipe \| inside a cell | FM | Open: waiting for the FM |',
        '| R1-3 | Arabic name: عمولة شركة الحوالات | FM | Default applied |',
        '',
        'A note under the table with **bold** and <script>alert(1)</script>.',
        '',
        '- First input',
        '- Second input',
        '',
        '## 7. Sign-off',
        '',
        '| Name | Role |',
        '|------|------|',
        '| | Financial Manager |',
    ]));

    return $file;
}

it('opens the three bridge pages for Head Office and the Financial Manager only', function () {
    foreach (['/bridge/decisions', '/bridge/mapping', '/bridge/posting-rules'] as $url) {
        $this->actingAs(bridgeUser(RoleId::HeadOffice, 'Ho'.md5($url)))->get($url)->assertOk();
        $this->actingAs(bridgeUser(RoleId::FinancialManager, 'Fm'.md5($url)))->get($url)->assertOk();

        foreach ([RoleId::Accountant, RoleId::ProjectManager, RoleId::Cashier, RoleId::Employee, RoleId::Donor, RoleId::GeneralManager, RoleId::ProgramManager] as $role) {
            $this->actingAs(bridgeUser($role, $role->name.md5($url)))->get($url)->assertForbidden();
        }
    }
});

it('reads the decisions log into sections, keeps cells escaped and leaves the sign-off out', function () {
    $log = new DecisionsLog(decisionsFixture());
    $sections = $log->sections();

    expect($sections)->toHaveCount(1)
        ->and($sections[0]['title'])->toBe('1. Round 1');

    [$table, $note, $list] = $sections[0]['blocks'];

    expect($table['head'])->toBe(['ID', 'Decision', 'Owner', 'Status'])
        ->and($table['status'])->toBe(3)
        ->and($table['rows'])->toHaveCount(3)
        ->and($table['rows'][0][1]['html'])->toContain('<code>GEN-xxx-USD</code>')
        ->and($table['rows'][1][1]['text'])->toBe('A pipe | inside a cell')
        ->and($table['rows'][2][1]['text'])->toBe('Arabic name: عمولة شركة الحوالات')
        ->and($note['html'])->toContain('<strong>bold</strong>')->not->toContain('<script>')
        ->and($list['items'])->toBe(['First input', 'Second input'])
        ->and($log->summary($sections))->toBe(['decided' => 1, 'open' => 1, 'other' => 1]);
});

it('shows the real decisions log and searches it', function () {
    $page = Livewire::actingAs(bridgeUser(RoleId::FinancialManager))->test(Bridge::class, ['screen' => 'decisions'])
        ->assertSee('R3-4')
        ->assertSee('D11')
        ->assertDontSee('Sign-off');

    $page->set('search', 'Jouliana')->assertSee('Q-b')->assertDontSee('R3-4');
    $page->set('search', 'nothing like this anywhere')->assertSee(__('bridge.decisions.no_match'));
});

it('shows the mapping tables', function () {
    Livewire::actingAs(bridgeUser(RoleId::HeadOffice))->test(Bridge::class, ['screen' => 'mapping'])
        ->assertSee(__('bridge.mapping.concepts_title'))
        ->assertSee('Income account 4101 to 4602')
        ->assertSee('CPV');
});

it('lists every posting rule with a status taken from the code', function () {
    $rules = collect(app(PostingRuleCatalogue::class)->rules())->keyBy('number');

    expect($rules->keys()->all())->toBe(range(1, 15))
        ->and($rules[1]['state'])->toBe(PostingRuleCatalogue::LIVE)
        ->and($rules[11]['state'])->toBe(PostingRuleCatalogue::LIVE)
        ->and($rules[9]['state'])->toBe(PostingRuleCatalogue::CODE_ONLY)
        ->and($rules[13]['state'])->toBe(PostingRuleCatalogue::CODE_ONLY)
        ->and($rules->pluck('state')->unique()->all())->not->toContain(PostingRuleCatalogue::DESCRIBED);

    Livewire::actingAs(bridgeUser(RoleId::FinancialManager))->test(Bridge::class, ['screen' => 'rules'])
        ->assertSee(__('bridge.rules.items.4.title'))
        ->assertSee(__('bridge.rules.state.code'));
});

it('keeps each rule class on the number the catalogue gives it', function () {
    foreach (PostingRuleCatalogue::RULES as $number => [$class]) {
        $reflection = new ReflectionClass($class);

        if ($reflection->hasMethod('number')) {
            expect($reflection->newInstanceWithoutConstructor()->number())->toBe($number);
        }
    }

    expect(is_subclass_of(PostingRuleCatalogue::RULES[1][0], PostingRule::class))->toBeTrue();
});

it('refuses an unknown bridge page', function () {
    Livewire::actingAs(bridgeUser(RoleId::HeadOffice))->test(Bridge::class, ['screen' => 'nope'])->assertNotFound();
});

it('has every bridge text in English and Arabic', function () {
    $keys = fn (string $locale) => collect(require lang_path("$locale/bridge.php"))->dot()->keys()->sort()->values()->all();

    expect($keys('ar'))->toBe($keys('en'));
});
