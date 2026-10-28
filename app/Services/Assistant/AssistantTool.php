<?php

namespace App\Services\Assistant;

use App\Models\User;

/**
 * Something the assistant may do for a person. A tool is offered only to people whose screen permissions cover it
 * (`allows`), and is checked again when it runs, so the assistant never sees or does more than the person could.
 */
interface AssistantTool
{
    public function name(): string;

    public function allows(User $user): bool;

    /** @return array{description: string, inputSchema: array<string, mixed>} */
    public function definition(): array;

    /**
     * @param  array<string, mixed>  $input  as the model sent it, not yet validated
     * @return array<string, mixed> the result, sent back to the model as JSON
     *
     * @throws ToolInputException when the input cannot be used
     */
    public function run(User $user, array $input, string $conversationId): array;
}
