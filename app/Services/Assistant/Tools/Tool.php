<?php

namespace App\Services\Assistant\Tools;

use App\Enums\ScreenAction;
use App\Models\GlProject;
use App\Models\User;
use App\Services\Assistant\AssistantTool;
use App\Services\Assistant\ToolInputException;
use App\Services\Projects\ProjectAccess;

/**
 * What the assistant's tools share: reading the model's input safely, the person's screen permissions and the
 * projects they are limited to.
 */
abstract class Tool implements AssistantTool
{
    /** @param  array<string, mixed>  $input */
    protected function text(array $input, string $key, int $max = 200): string
    {
        $value = $input[$key] ?? '';

        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }

        return is_string($value) ? mb_substr(trim($value), 0, $max) : '';
    }

    /** @param  array<string, mixed>  $input */
    protected function day(array $input, string $key): ?string
    {
        $value = $this->text($input, $key, 10);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            throw new ToolInputException("{$key} must be a date as YYYY-MM-DD.");
        }

        return $value;
    }

    /** @param  array<string, mixed>  $input */
    protected function flag(array $input, string $key): bool
    {
        return ($input[$key] ?? false) === true;
    }

    protected function can(User $user, string $screen, ScreenAction $action = ScreenAction::View): bool
    {
        return $user->canOnScreen($screen, $action);
    }

    /**
     * Project codes the person is limited to (a project manager's own projects), or null when not limited.
     *
     * @return list<string>|null
     */
    protected function projectLimit(User $user): ?array
    {
        $limit = app(ProjectAccess::class)->limitedTo($user);

        return $limit === null ? null : array_values(GlProject::query()->whereIn('legacy_account_id', $limit)->pluck('code')->all());
    }

    protected function mayOpenProject(User $user, string $code): bool
    {
        $limit = $this->projectLimit($user);

        return $limit === null || in_array($code, $limit, true);
    }

    protected function maxRows(): int
    {
        return (int) config('assistant.max_rows', 50);
    }
}
