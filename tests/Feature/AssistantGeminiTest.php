<?php

use App\Enums\RoleId;
use App\Models\User;
use App\Services\Assistant\AssistantUnavailable;
use App\Services\Assistant\ClaudeGateway;
use App\Services\Assistant\GeminiGateway;
use App\Services\Assistant\Toolbox;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['assistant.provider' => 'gemini', 'assistant.gemini.api_key' => 'test-key', 'assistant.gemini.model' => 'gemini-test']);
});

function geminiReply(array $parts, string $finish = 'STOP'): array
{
    return ['candidates' => [['content' => ['role' => 'model', 'parts' => $parts], 'finishReason' => $finish]]];
}

it('is the assistant\'s gateway when the provider is gemini', function () {
    app()->forgetInstance(ClaudeGateway::class);

    expect(app(ClaudeGateway::class))->toBeInstanceOf(GeminiGateway::class);
});

it('says the assistant is not set up when there is no Gemini key', function () {
    config(['assistant.gemini.api_key' => '']);
    Http::fake();

    expect(fn () => (new GeminiGateway)->send([['type' => 'text', 'text' => 'Be brief.']], [['role' => 'user', 'content' => 'Hi']], []))
        ->toThrow(AssistantUnavailable::class, __('assistant.errors.not_set_up'));

    Http::assertNothingSent();
});

it('returns the answer as text blocks', function () {
    Http::fake(['*' => Http::response(geminiReply([['text' => 'Two vouchers are waiting.']]))]);

    $reply = (new GeminiGateway)->send([['type' => 'text', 'text' => 'Be brief.']], [['role' => 'user', 'content' => 'What is waiting?']], []);

    expect($reply->stopReason)->toBe('end_turn')
        ->and($reply->content)->toBe([['type' => 'text', 'text' => 'Two vouchers are waiting.']]);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'gemini-test:generateContent')
        && $request->hasHeader('x-goog-api-key', 'test-key')
        && ! str_contains($request->url(), 'test-key')
        && $request['systemInstruction']['parts'][0]['text'] === 'Be brief.'
        && $request['contents'] === [['role' => 'user', 'parts' => [['text' => 'What is waiting?']]]]);
});

it('turns a function call into a tool_use block the tool loop understands', function () {
    Http::fake(['*' => Http::response(geminiReply([['functionCall' => ['name' => 'find_vouchers', 'args' => ['status' => 'pending_approval']], 'thoughtSignature' => 'SIG']]))]);

    $reply = (new GeminiGateway)->send([], [['role' => 'user', 'content' => 'What is waiting?']], []);

    expect($reply->stopReason)->toBe('tool_use')
        ->and($reply->content[0])->toMatchArray(['type' => 'tool_use', 'name' => 'find_vouchers', 'input' => ['status' => 'pending_approval'], 'thought_signature' => 'SIG'])
        ->and($reply->content[0]['id'])->toStartWith('call_');
});

it('sends earlier tool calls and their results back by tool name, and drops thinking blocks', function () {
    Http::fake(['*' => Http::response(geminiReply([['text' => 'Done.']]))]);

    (new GeminiGateway)->send([], [
        ['role' => 'user', 'content' => 'What is waiting?'],
        ['role' => 'assistant', 'content' => [
            ['type' => 'thinking', 'thinking' => '', 'signature' => 'x'],
            ['type' => 'tool_use', 'id' => 'call_1', 'name' => 'find_vouchers', 'input' => [], 'thought_signature' => 'SIG'],
        ]],
        ['role' => 'user', 'content' => [['type' => 'tool_result', 'tool_use_id' => 'call_1', 'content' => '{"vouchers":[]}', 'is_error' => false]]],
        ['role' => 'user', 'content' => '[Note from the app, not typed by the person] You saved entry #1.'],
    ], []);

    Http::assertSent(function (Request $request) {
        $contents = json_decode($request->body(), true)['contents'];

        return count($contents) === 3
            && $contents[1]['role'] === 'model'
            && $contents[1]['parts'][0]['functionCall']['name'] === 'find_vouchers'
            && $contents[1]['parts'][0]['thoughtSignature'] === 'SIG'
            && str_contains($request->body(), '"args":{}')
            && ! str_contains($request->body(), 'thinking')
            && $contents[2]['role'] === 'user'
            && $contents[2]['parts'][0] === ['functionResponse' => ['name' => 'find_vouchers', 'response' => ['result' => '{"vouchers":[]}']]]
            && str_contains($contents[2]['parts'][1]['text'], 'You saved entry #1.');
    });
});

it('gives Gemini the assistant\'s real tools in the schema subset it accepts', function () {
    Http::fake(['*' => Http::response(geminiReply([['text' => 'ok']]))]);
    createFinanceSchema();
    $user = User::query()->findOrFail(createFinanceUser("Ann", "secret pass", RoleId::Accountant->name, RoleId::Accountant->value));
    $tools = app(Toolbox::class)->definitions($user);

    (new GeminiGateway)->send([], [['role' => 'user', 'content' => 'Hi']], $tools);

    Http::assertSent(function (Request $request) use ($tools) {
        $declarations = collect(json_decode($request->body(), true)['tools'][0]['functionDeclarations']);
        $propose = $declarations->firstWhere('name', 'propose_entry');

        return $declarations->count() === count($tools)
            && ! str_contains($request->body(), 'additionalProperties')
            && $propose['parameters']['type'] === 'object'
            && isset($propose['parameters']['properties']['kind']['enum'])
            && in_array('amount', $propose['parameters']['required'], true);
    });
});

it('reports a declined request as a refusal', function () {
    Http::fake(['*' => Http::response(geminiReply([], 'SAFETY'))]);

    $reply = (new GeminiGateway)->send([], [['role' => 'user', 'content' => 'Hi']], []);

    expect($reply->stopReason)->toBe('refusal');
});

it('reports a cut-short answer as max_tokens', function () {
    Http::fake(['*' => Http::response(geminiReply([['text' => 'Long answer']], 'MAX_TOKENS'))]);

    expect((new GeminiGateway)->send([], [['role' => 'user', 'content' => 'Hi']], [])->stopReason)->toBe('max_tokens');
});

it('shows the right message for each kind of Gemini failure', function (int $status, array $body, string $message) {
    Http::fake(['*' => Http::response($body, $status)]);

    expect(fn () => (new GeminiGateway)->send([], [['role' => 'user', 'content' => 'Hi']], []))
        ->toThrow(AssistantUnavailable::class, $message);
})->with([
    'rate limit' => [429, ['error' => ['message' => 'Quota exceeded']], 'The assistant is busy. Try again in a minute.'],
    'bad key' => [400, ['error' => ['message' => 'API key not valid. Please pass a valid API key.']], 'The assistant\'s API key was refused. Ask the administrator to check it.'],
    'server error' => [500, ['error' => ['message' => 'Internal error']], 'The assistant could not be reached. Try again shortly.'],
]);
