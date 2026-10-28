<?php

namespace App\Services\Assistant;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Gemini through its REST API. The rest of the assistant speaks in Anthropic's block shape (text, tool_use,
 * tool_result), so this gateway translates at the edge: the conversation goes out as Gemini `contents` and the reply
 * comes back as the same blocks, which means stored conversations and the tool loop work unchanged.
 */
class GeminiGateway implements ClaudeGateway
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    /** Finish reasons where Gemini declined to answer, as opposed to simply finishing. */
    private const DECLINED = ['SAFETY', 'PROHIBITED_CONTENT', 'BLOCKLIST', 'SPII', 'RECITATION', 'IMAGE_SAFETY'];

    public function send(array $system, array $messages, array $tools): GatewayReply
    {
        $key = config('assistant.gemini.api_key');

        if (! is_string($key) || $key === '') {
            throw new AssistantUnavailable(__('assistant.errors.not_set_up'));
        }

        $body = [
            'systemInstruction' => ['parts' => [['text' => implode("\n\n", array_map(fn (array $block) => (string) ($block['text'] ?? ''), $system))]]],
            'contents' => $this->contents($messages),
            'generationConfig' => ['maxOutputTokens' => (int) config('assistant.max_tokens')],
        ];

        if ($tools !== []) {
            $body['tools'] = [['functionDeclarations' => array_map($this->declaration(...), $tools)]];
        }

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $key])
                ->acceptJson()
                ->timeout(120)
                ->post(sprintf(self::ENDPOINT, (string) config('assistant.gemini.model')), $body);
        } catch (ConnectionException $e) {
            report($e);
            throw new AssistantUnavailable(__('assistant.errors.unreachable'), previous: $e);
        }

        if ($response->failed()) {
            throw $this->unavailable($response);
        }

        return $this->reply($response);
    }

    /**
     * The message the person sees for a failed call. Gemini reports a bad key as a 400, so it is recognised by its wording.
     */
    public function unavailable(Response $response): AssistantUnavailable
    {
        $detail = (string) ($response->json('error.message') ?? '');

        if ($response->status() === 429) {
            return new AssistantUnavailable(__('assistant.errors.busy'));
        }

        report(new \RuntimeException('Gemini HTTP '.$response->status().': '.Str::limit($response->body(), 500)));

        if (in_array($response->status(), [401, 403], true) || str_contains(strtolower($detail), 'api key')) {
            return new AssistantUnavailable(__('assistant.errors.key'));
        }

        return new AssistantUnavailable(__('assistant.errors.unreachable'));
    }

    private function reply(Response $response): GatewayReply
    {
        $candidate = $response->json('candidates.0');

        if (! is_array($candidate)) {
            if ($response->json('promptFeedback.blockReason') !== null) {
                return new GatewayReply('refusal', []);
            }

            throw new AssistantUnavailable(__('assistant.errors.unreachable'));
        }

        $content = [];
        $calls = false;

        foreach ((array) ($candidate['content']['parts'] ?? []) as $part) {
            if (! is_array($part) || ($part['thought'] ?? false) === true) {
                continue;
            }

            if (isset($part['functionCall']) && is_array($part['functionCall'])) {
                $calls = true;
                $block = [
                    'type' => 'tool_use',
                    'id' => 'call_'.Str::random(16),
                    'name' => (string) ($part['functionCall']['name'] ?? ''),
                    'input' => is_array($part['functionCall']['args'] ?? null) ? $part['functionCall']['args'] : [],
                ];

                // Newer Gemini models want this echoed back with the call, or they refuse the next turn.
                if (isset($part['thoughtSignature'])) {
                    $block['thought_signature'] = $part['thoughtSignature'];
                }

                $content[] = $block;
            } elseif (isset($part['text']) && is_string($part['text'])) {
                $content[] = ['type' => 'text', 'text' => $part['text']];
            }
        }

        $finish = (string) ($candidate['finishReason'] ?? 'STOP');

        $stop = match (true) {
            $calls => 'tool_use',
            $finish === 'MAX_TOKENS' => 'max_tokens',
            in_array($finish, self::DECLINED, true) => 'refusal',
            default => 'end_turn',
        };

        if ($content === [] && $stop === 'end_turn') {
            throw new AssistantUnavailable(__('assistant.errors.unreachable'));
        }

        return new GatewayReply($stop, $content);
    }

    /**
     * The conversation as Gemini `contents`: same-role turns merged, thinking blocks dropped, and each tool result
     * labelled with the tool's name (Gemini matches by name, not by id).
     *
     * @param  list<array{role: string, content: string|list<array<string, mixed>>}>  $messages
     * @return list<array{role: string, parts: list<array<string, mixed>>}>
     */
    private function contents(array $messages): array
    {
        $names = [];
        $contents = [];

        foreach ($messages as $message) {
            $parts = [];

            if (is_string($message['content'])) {
                $parts[] = ['text' => $message['content']];
            } else {
                foreach ($message['content'] as $block) {
                    $type = $block['type'] ?? null;

                    if ($type === 'text' && trim((string) ($block['text'] ?? '')) !== '') {
                        $parts[] = ['text' => (string) $block['text']];
                    } elseif ($type === 'tool_use') {
                        $names[(string) ($block['id'] ?? '')] = (string) ($block['name'] ?? '');
                        $part = ['functionCall' => ['name' => (string) ($block['name'] ?? ''), 'args' => (object) (is_array($block['input'] ?? null) ? $block['input'] : [])]];

                        if (isset($block['thought_signature'])) {
                            $part['thoughtSignature'] = $block['thought_signature'];
                        }

                        $parts[] = $part;
                    } elseif ($type === 'tool_result') {
                        $result = (string) ($block['content'] ?? '');
                        $parts[] = ['functionResponse' => [
                            'name' => $names[(string) ($block['tool_use_id'] ?? '')] ?? 'tool',
                            'response' => ($block['is_error'] ?? false) === true ? ['error' => $result] : ['result' => $result],
                        ]];
                    }
                }
            }

            if ($parts === []) {
                continue;
            }

            $role = $message['role'] === 'assistant' ? 'model' : 'user';
            $last = array_key_last($contents);

            if ($last !== null && $contents[$last]['role'] === $role) {
                $contents[$last]['parts'] = [...$contents[$last]['parts'], ...$parts];
            } else {
                $contents[] = ['role' => $role, 'parts' => $parts];
            }
        }

        return $contents;
    }

    /**
     * @param  array<string, mixed>  $tool
     * @return array<string, mixed>
     */
    private function declaration(array $tool): array
    {
        $declaration = ['name' => (string) $tool['name'], 'description' => (string) ($tool['description'] ?? '')];
        $schema = $this->schema(is_array($tool['input_schema'] ?? null) ? $tool['input_schema'] : []);

        if (! empty((array) ($schema['properties'] ?? []))) {
            $declaration['parameters'] = $schema;
        }

        return $declaration;
    }

    /**
     * Gemini takes only a subset of JSON Schema (no additionalProperties and the like), so keep what it knows.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function schema(array $schema): array
    {
        $out = [];
        $type = $schema['type'] ?? null;

        if (is_array($type)) {
            $nullable = in_array('null', $type, true);
            $type = collect($type)->first(fn ($candidate) => $candidate !== 'null');
            $nullable && $out['nullable'] = true;
        }

        if (is_string($type)) {
            $out['type'] = $type;
        }

        foreach (['description', 'minimum', 'maximum'] as $key) {
            if (isset($schema[$key])) {
                $out[$key] = $schema[$key];
            }
        }

        if (isset($schema['enum']) && is_array($schema['enum'])) {
            $out['enum'] = array_map('strval', array_values($schema['enum']));
        }

        if (isset($schema['properties']) && is_array($schema['properties'])) {
            $out['properties'] = (object) array_map(fn ($property) => $this->schema(is_array($property) ? $property : []), $schema['properties']);

            $required = array_values(array_filter((array) ($schema['required'] ?? []), fn ($name) => isset($schema['properties'][$name])));
            $required !== [] && $out['required'] = $required;
        }

        if (isset($schema['items']) && is_array($schema['items'])) {
            $out['items'] = $this->schema($schema['items']);
        }

        return $out;
    }
}
