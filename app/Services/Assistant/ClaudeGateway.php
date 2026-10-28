<?php

namespace App\Services\Assistant;

/**
 * One call to the model. Content blocks go in and come out in the API's own (wire) shape, so a reply can be stored
 * and replayed unchanged. Tests bind a scripted gateway in its place.
 */
interface ClaudeGateway
{
    /**
     * @param  list<array<string, mixed>>  $system
     * @param  list<array{role: string, content: string|list<array<string, mixed>>}>  $messages
     * @param  list<array<string, mixed>>  $tools
     *
     * @throws AssistantUnavailable when the API cannot be reached or refuses the call
     */
    public function send(array $system, array $messages, array $tools): GatewayReply;
}
