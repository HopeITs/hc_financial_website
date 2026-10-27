<?php

namespace App\Services\Assistant;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;

/**
 * Claude through the official Anthropic SDK. Server-side fallback is on: when the model declines a request for policy
 * reasons the API retries it on its default substitute model within the same call.
 */
class AnthropicGateway implements ClaudeGateway
{
    private ?Client $client = null;

    public function send(array $system, array $messages, array $tools): GatewayReply
    {
        try {
            // The blocks are in the API's own (snake_case) shape, which the SDK sends as they are; its PHPDoc shapes
            // describe only its camelCase form (see AssistantTest, "sends the tools in the shape the API takes").
            $message = $this->client()->beta->messages->create(
                maxTokens: (int) config('assistant.max_tokens'),
                messages: $messages, // @phpstan-ignore argument.type
                model: (string) config('assistant.model'),
                system: $system, // @phpstan-ignore argument.type
                tools: $tools, // @phpstan-ignore argument.type
                outputConfig: ['effort' => (string) config('assistant.effort')], // @phpstan-ignore argument.type
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (AuthenticationException $e) {
            report($e);
            throw new AssistantUnavailable(__('assistant.errors.key'), previous: $e);
        } catch (RateLimitException $e) {
            throw new AssistantUnavailable(__('assistant.errors.busy'), previous: $e);
        } catch (APIStatusException|APIConnectionException $e) {
            report($e);
            throw new AssistantUnavailable(__('assistant.errors.unreachable'), previous: $e);
        }

        /** @var list<array<string, mixed>> $content */
        $content = json_decode((string) json_encode($message->content), true);

        return new GatewayReply((string) $message->stopReason, $content);
    }

    private function client(): Client
    {
        $key = config('assistant.api_key');

        if (! is_string($key) || $key === '') {
            throw new AssistantUnavailable(__('assistant.errors.not_set_up'));
        }

        return $this->client ??= new Client(apiKey: $key, requestOptions: ['timeout' => 120]);
    }
}
