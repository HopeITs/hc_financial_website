<?php

namespace App\Services\Assistant;

use App\Models\User;
use App\Services\Assistant\Tools\AccountBalancesTool;
use App\Services\Assistant\Tools\AccountStatementTool;
use App\Services\Assistant\Tools\FindAccounts;
use App\Services\Assistant\Tools\FindProjects;
use App\Services\Assistant\Tools\FindVouchers;
use App\Services\Assistant\Tools\GetVoucher;
use App\Services\Assistant\Tools\ProjectBudgetTool;
use App\Services\Assistant\Tools\ProposeEntry;
use App\Services\Assistant\Tools\TreasuryTool;
use Throwable;

/**
 * The assistant's tools, each limited to the people its screens allow. A tool the person may not use is neither
 * offered to the model nor run if the model asks for it anyway.
 */
class Toolbox
{
    /** In name order, so the list sent to the model stays the same from call to call (and its cache holds). */
    public const TOOLS = [
        AccountBalancesTool::class,
        AccountStatementTool::class,
        FindAccounts::class,
        FindProjects::class,
        FindVouchers::class,
        GetVoucher::class,
        ProjectBudgetTool::class,
        ProposeEntry::class,
        TreasuryTool::class,
    ];

    /** @return list<AssistantTool> */
    public function for(User $user): array
    {
        $tools = array_map(fn (string $class): AssistantTool => app($class), self::TOOLS);

        return array_values(array_filter($tools, fn (AssistantTool $tool) => $tool->allows($user)));
    }

    /**
     * The tools in the API's own shape. The SDK picks the tool variant by `type` and maps only snake_case keys, so a
     * camelCase `inputSchema` would reach the API unmapped.
     *
     * @return list<array<string, mixed>>
     */
    public function definitions(User $user): array
    {
        return array_map(function (AssistantTool $tool) {
            $definition = $tool->definition();

            return ['type' => 'custom', 'name' => $tool->name(), 'description' => $definition['description'], 'input_schema' => $definition['inputSchema']];
        }, $this->for($user));
    }

    /**
     * Runs one tool call of the model.
     *
     * @param  array<string, mixed>  $input
     * @return array{content: string, is_error: bool}
     */
    public function run(User $user, string $name, array $input, string $conversationId): array
    {
        $tool = collect($this->for($user))->first(fn (AssistantTool $tool) => $tool->name() === $name);

        if ($tool === null) {
            return ['content' => "The tool {$name} is not available to this person.", 'is_error' => true];
        }

        try {
            return ['content' => (string) json_encode($tool->run($user, $input, $conversationId), JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION), 'is_error' => false];
        } catch (ToolInputException $e) {
            return ['content' => $e->getMessage(), 'is_error' => true];
        } catch (Throwable $e) {
            report($e);

            return ['content' => 'The tool failed; tell the person it could not be done now.', 'is_error' => true];
        }
    }
}
