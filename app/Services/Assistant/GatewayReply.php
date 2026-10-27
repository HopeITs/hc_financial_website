<?php

namespace App\Services\Assistant;

/**
 * What the model answered: why it stopped (`end_turn`, `tool_use`, `max_tokens`, `refusal`) and its content blocks in
 * the API's own shape.
 */
final readonly class GatewayReply
{
    /** @param  list<array<string, mixed>>  $content */
    public function __construct(
        public string $stopReason,
        public array $content,
    ) {}
}
