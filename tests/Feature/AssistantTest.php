<?php

use Anthropic\Beta\Messages\MessageCreateParams;
use Anthropic\Core\Exceptions\APIStatusException;
use App\Enums\RoleId;
use App\Livewire\AssistantPanel;
use App\Models\AssistantMessage;
use App\Models\AssistantProposal;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Assistant\AnthropicGateway;
use App\Services\Assistant\Assistant;
use App\Services\Assistant\AssistantUnavailable;
use App\Services\Assistant\ClaudeGateway;
use App\Services\Assistant\EntryProposals;
use App\Services\Assistant\GatewayReply;
use App\Services\Assistant\ProposalRefused;
use App\Services\Assistant\Toolbox;
use App\Services\Ledger\PostVoucher;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * Stands in for Claude: answers with the replies it was given, in order, and keeps every request it received.
 */
class ScriptedGateway implements ClaudeGateway
{
    /** @var list<array{system: array<mixed>, messages: array<mixed>, tools: array<mixed>}> */
    public array $requests = [];

    /** @param  list<GatewayReply>  $replies */
    public function __construct(public array $replies = []) {}

    public function send(array $system, array $messages, array $tools): GatewayReply
    {
        $this->requests[] = ['system' => $system, 'messages' => $messages, 'tools' => $tools];

        return array_shift($this->replies) ?? new GatewayReply('end_turn', [['type' => 'text', 'text' => 'Done.']]);
    }
}

beforeEach(function () {
    createFinanceSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (require database_path('migrations/2026_10_25_090000_add_contract_dates_to_gl_projects.php'))->up();
    (new ChartOfAccountsSeeder)->run();
    config(['assistant.api_key' => 'test-key']);
    RateLimiter::clear('assistant:1');
});

function assistantUser(RoleId $role = RoleId::Accountant, string $name = 'Ann'): User
{
    return User::query()->findOrFail(createFinanceUser($name, 'secret pass', $role->name, $role->value));
}

function scripted(GatewayReply ...$replies): ScriptedGateway
{
    $gateway = new ScriptedGateway(array_values($replies));
    app()->instance(ClaudeGateway::class, $gateway);

    return $gateway;
}

/** @param  array<string, mixed>  $input */
function toolCall(string $name, array $input, string $id = 'toolu_1'): GatewayReply
{
    return new GatewayReply('tool_use', [
        ['type' => 'thinking', 'thinking' => '', 'signature' => 'SIG-'.$id],
        ['type' => 'tool_use', 'id' => $id, 'name' => $name, 'input' => $input],
    ]);
}

function answer(string $text): GatewayReply
{
    return new GatewayReply('end_turn', [['type' => 'text', 'text' => $text]]);
}

/** @return array{content: string, is_error: bool, tool_use_id: string} */
function lastToolResult(ScriptedGateway $gateway): array
{
    $messages = end($gateway->requests)['messages'];

    return end($messages)['content'][0];
}

const INCOME = ['kind' => 'income', 'date' => '2026-03-10', 'project' => 'F2F', 'cash' => '1111', 'account' => '4101', 'amount' => '250', 'description' => 'Grant'];

it('offers each role only the tools its screens allow', function () {
    $names = fn (RoleId $role, string $who) => array_map(fn ($tool) => $tool->name(), app(Toolbox::class)->for(assistantUser($role, $who)));

    expect($names(RoleId::Accountant, 'Ann'))->toContain('propose_entry', 'account_statement', 'find_vouchers', 'treasury_position')
        ->and($names(RoleId::Cashier, 'Cal'))->toContain('propose_entry', 'treasury_position', 'get_voucher')->not->toContain('account_statement', 'account_balances')
        ->and($names(RoleId::FinancialManager, 'Fay'))->toContain('treasury_position', 'account_statement')->not->toContain('propose_entry')
        ->and($names(RoleId::Employee, 'Eve'))->toBe([])
        ->and(app(Assistant::class)->availableFor(assistantUser(RoleId::Donor, 'Don')))->toBeFalse();
});

it('sends the tools in the shape the API takes, through the SDK', function () {
    $definitions = app(Toolbox::class)->definitions(assistantUser());

    [$body] = MessageCreateParams::parseRequest(MessageCreateParams::with(
        maxTokens: 10, model: 'claude-opus-5-5', messages: [['role' => 'user', 'content' => 'hi']], tools: $definitions,
    ), null);

    $tool = collect($body['tools'])->firstWhere('name', 'propose_entry');

    expect($tool['type'])->toBe('custom')
        ->and($tool)->toHaveKey('input_schema')->not->toHaveKey('inputSchema')
        ->and($tool['input_schema']['required'])->toContain('kind', 'amount');
});

it('runs the model\'s tool calls and replays the conversation unchanged', function () {
    $user = assistantUser();
    app(PostVoucher::class)->post(ledgerReceipt(['preparedBy' => (int) $user->getAuthIdentifier()]));
    $gateway = scripted(toolCall('find_vouchers', ['status' => 'pending_approval']), answer('One voucher is waiting.'));
    $conversation = (string) Str::uuid();

    app(Assistant::class)->ask($user, $conversation, 'What is waiting?');

    $result = lastToolResult($gateway);
    $replayed = $gateway->requests[1]['messages'][1];

    expect($gateway->requests)->toHaveCount(2)
        ->and($result['is_error'])->toBeFalse()
        ->and(json_decode($result['content'], true)['vouchers'])->toHaveCount(1)
        ->and($replayed['role'])->toBe('assistant')
        ->and($replayed['content'][0])->toBe(['type' => 'thinking', 'thinking' => '', 'signature' => 'SIG-toolu_1'])
        ->and(app(Assistant::class)->transcript($user, $conversation))->toBe([
            ['type' => 'user', 'text' => 'What is waiting?'],
            ['type' => 'assistant', 'text' => 'One voucher is waiting.'],
        ]);
});

it('refuses a tool the person may not use even when the model asks for it', function () {
    $gateway = scripted(toolCall('account_statement', ['account' => '1111']), answer('I cannot see statements.'));

    app(Assistant::class)->ask(assistantUser(RoleId::Cashier, 'Cal'), (string) Str::uuid(), 'Statement of 1111?');

    expect(lastToolResult($gateway)['is_error'])->toBeTrue()
        ->and(lastToolResult($gateway)['content'])->toContain('not available');
});

it('tells the model what is wrong with tool input instead of failing', function () {
    $gateway = scripted(toolCall('account_statement', ['account' => '1111', 'from' => 'last March']));

    app(Assistant::class)->ask(assistantUser(), (string) Str::uuid(), 'Statement?');

    expect(lastToolResult($gateway)['is_error'])->toBeTrue()
        ->and(lastToolResult($gateway)['content'])->toContain('YYYY-MM-DD');
});

it('prepares an entry without saving it, then saves it as a pending voucher on Save', function () {
    $user = assistantUser();
    $conversation = (string) Str::uuid();
    $gateway = scripted(toolCall('propose_entry', INCOME), answer('Here is the entry.'));

    app(Assistant::class)->ask($user, $conversation, 'Record a 250 USD grant into 1111');

    $result = json_decode(lastToolResult($gateway)['content'], true);
    $proposal = AssistantProposal::query()->sole();

    expect(Voucher::query()->count())->toBe(0)
        ->and($result['proposed'])->toBeTrue()
        ->and($result['type'])->toBe('CRV')
        ->and($result['lines'])->toHaveCount(2)
        ->and(app(Assistant::class)->transcript($user, $conversation))->toContain(['type' => 'proposal', 'proposal' => $proposal->id]);

    $voucher = app(EntryProposals::class)->confirm($user, $proposal->id);

    expect($voucher->status->value)->toBe('pending_approval')
        ->and($voucher->prepared_by)->toBe((int) $user->getAuthIdentifier())
        ->and($proposal->fresh()->status)->toBe(AssistantProposal::SAVED)
        ->and($proposal->fresh()->voucher_id)->toBe($voucher->id);
});

it('keeps no proposal for an entry the posting rules refuse', function () {
    $gateway = scripted(toolCall('propose_entry', ['project' => ''] + INCOME));

    app(Assistant::class)->ask(assistantUser(), (string) Str::uuid(), 'Record it');

    $result = json_decode(lastToolResult($gateway)['content'], true);

    expect($result['proposed'])->toBeFalse()
        ->and($result['problems'])->not->toBeEmpty()
        ->and(AssistantProposal::query()->count())->toBe(0);
});

it('lets only its person save a proposal, and only once', function () {
    $owner = assistantUser();
    $other = assistantUser(RoleId::Accountant, 'Bob');
    $proposals = app(EntryProposals::class);
    $id = $proposals->propose($owner, (string) Str::uuid(), INCOME)['proposal_id'];

    expect(fn () => $proposals->confirm($other, $id))->toThrow(ProposalRefused::class);

    $proposals->confirm($owner, $id);

    expect(fn () => $proposals->confirm($owner, $id))->toThrow(ProposalRefused::class)
        ->and(Voucher::query()->count())->toBe(1);
});

it('refuses to save for a role that may not enter operations', function () {
    $id = app(EntryProposals::class)->propose(assistantUser(), (string) Str::uuid(), INCOME)['proposal_id'];
    AssistantProposal::query()->whereKey($id)->update(['user_id' => (int) assistantUser(RoleId::FinancialManager, 'Fay')->getAuthIdentifier()]);

    expect(fn () => app(EntryProposals::class)->confirm(User::query()->where('Username', 'Fay')->sole(), $id))->toThrow(ProposalRefused::class)
        ->and(Voucher::query()->count())->toBe(0);
});

it('never sends another person\'s conversation to the model', function () {
    $conversation = (string) Str::uuid();
    scripted(answer('Secret answer.'));
    app(Assistant::class)->ask(assistantUser(), $conversation, 'Private question');

    $gateway = scripted(answer('Hello.'));
    app(Assistant::class)->ask(assistantUser(RoleId::Accountant, 'Bob'), $conversation, 'Hi');

    expect($gateway->requests[0]['messages'])->toBe([['role' => 'user', 'content' => 'Hi']]);
});

it('limits how many questions a person sends per minute', function () {
    config(['assistant.per_minute' => 1]);
    $user = assistantUser();
    scripted(answer('One.'));

    app(Assistant::class)->ask($user, (string) Str::uuid(), 'First');

    expect(fn () => app(Assistant::class)->ask($user, (string) Str::uuid(), 'Second'))->toThrow(AssistantUnavailable::class);
});

it('says the assistant is not set up when there is no API key', function () {
    config(['assistant.api_key' => null]);

    Livewire::actingAs(assistantUser())->test(AssistantPanel::class)
        ->set('open', true)
        ->assertSee(__('assistant.errors.not_set_up'))
        ->assertSeeHtml('data-test="assistant-input"');
});

it('shows nothing to a person the assistant cannot help', function () {
    Livewire::actingAs(assistantUser(RoleId::Employee, 'Eve'))->test(AssistantPanel::class)
        ->assertDontSeeHtml('data-test="assistant"');
});

it('answers in the panel and saves the entry from its card', function () {
    $user = assistantUser();
    scripted(toolCall('propose_entry', INCOME), answer('Check the card and press Save.'));

    $panel = Livewire::actingAs($user)->test(AssistantPanel::class)
        ->set('open', true)
        ->set('question', 'Record a 250 USD grant')
        ->call('send')
        ->assertSet('question', '')
        ->assertSee('Check the card and press Save.')
        ->assertSeeHtml('data-test="assistant-save"');

    $proposal = AssistantProposal::query()->sole();

    $panel->call('save', $proposal->id)->assertSet('error', null);

    $voucher = Voucher::query()->sole();

    $panel->assertSee(__('assistant.proposal.open_voucher', ['number' => $voucher->id]));

    expect(AssistantMessage::query()->where('role', 'note')->count())->toBe(1);
});

it('tells the person when the Anthropic account is out of credit, and says unreachable for other failures', function () {
    $failure = fn (int $status, string $message) => APIStatusException::from(
        new GuzzleHttp\Psr7\Request('POST', 'https://api.anthropic.com/v1/messages'),
        new GuzzleHttp\Psr7\Response($status, ['Content-Type' => 'application/json'], json_encode([
            'type' => 'error', 'error' => ['type' => 'invalid_request_error', 'message' => $message],
        ])),
    );

    $gateway = new AnthropicGateway;

    expect($gateway->unavailable($failure(400, 'Your credit balance is too low to access the Anthropic API.'))->getMessage())
        ->toBe(__('assistant.errors.credit'))
        ->and($gateway->unavailable($failure(500, 'Internal error'))->getMessage())
        ->toBe(__('assistant.errors.unreachable'));
});

it('shows the error in the panel when the model cannot be reached', function () {
    app()->instance(ClaudeGateway::class, new class implements ClaudeGateway
    {
        public function send(array $system, array $messages, array $tools): GatewayReply
        {
            throw new AssistantUnavailable(__('assistant.errors.unreachable'));
        }
    });

    Livewire::actingAs(assistantUser())->test(AssistantPanel::class)
        ->set('open', true)
        ->set('question', 'Hello')
        ->call('send')
        ->assertSet('error', __('assistant.errors.unreachable'))
        ->assertSet('question', 'Hello');
});
