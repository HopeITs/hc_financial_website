<?php

namespace App\Services\Assistant;

use App\Enums\Locale;
use App\Enums\RoleId;
use App\Models\AssistantMessage;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The AI assistant: answers a person's questions about the ledger with the tools their screens allow, and prepares
 * entries for them to save. Each conversation is stored whole, in the API's own shape, so it replays unchanged and
 * doubles as the log of what was asked and done.
 */
class Assistant
{
    private const INSTRUCTIONS = <<<'TEXT'
        You are the assistant inside HOPE CENTER Financial, the web ledger of a humanitarian organisation working in Syria.
        You help the finance team (accountants, the financial manager, cashiers, project managers) find figures and prepare entries.

        Figures
        - Every number you give comes from a tool result in this conversation. Never estimate, round away or invent a figure; if no tool can give it, say so.
        - Balances, statements, budgets and the treasury count approved vouchers only. Say so when it matters, and mention pending vouchers when the person may be expecting them.
        - Name the currency of every amount (USD, SYP, EUR). SYP is Syrian pounds; a rate is pounds per dollar.
        - Give account and project codes next to names so the person can find them on the screens.

        Entries
        - To record an operation, call propose_entry. It never saves: the person sees a card with the voucher lines and presses Save. Never say an entry is saved or posted until a note in the conversation says the person saved it.
        - Before proposing, make sure you know the kind, date, cash account, amount, the income or expense account (or a budget line) and a description. Look codes up with the tools; ask the person for anything you cannot find instead of guessing. The person's words are the only source of amounts and dates.
        - When propose_entry returns problems, fix what you can and try again, or explain them to the person in plain words.
        - One proposal per operation. You cannot approve, reject, amend or delete vouchers; the approval screens do that.

        Manner
        - Reply in the person's language: Arabic when they write Arabic, English when they write English.
        - Be short and concrete. Use a small table when listing several figures.
        - The tools show only what this person may see. If a tool is missing or refuses, the person's role does not allow it; say so plainly.
        TEXT;

    public function __construct(
        private readonly ClaudeGateway $gateway,
        private readonly Toolbox $toolbox,
    ) {}

    /** Whether the person has anything the assistant could do for them. */
    public function availableFor(User $user): bool
    {
        return $this->toolbox->for($user) !== [];
    }

    /**
     * Asks one question and runs the model's tool calls until it answers.
     *
     * @throws AssistantUnavailable
     */
    public function ask(User $user, string $conversationId, string $question): void
    {
        $question = trim($question);

        if ($question === '') {
            return;
        }

        $limiter = 'assistant:'.$user->getAuthIdentifier();
        if (RateLimiter::tooManyAttempts($limiter, (int) config('assistant.per_minute'))) {
            throw new AssistantUnavailable(__('assistant.errors.too_many'));
        }
        RateLimiter::hit($limiter);

        $this->store($user, $conversationId, 'user', mb_substr($question, 0, 4000));

        for ($round = 0; $round < (int) config('assistant.max_rounds'); $round++) {
            $reply = $this->gateway->send($this->system($user), $this->history($user, $conversationId), $this->toolbox->definitions($user));
            $this->store($user, $conversationId, 'assistant', $reply->content);

            if ($reply->stopReason !== 'tool_use') {
                if (in_array($reply->stopReason, ['refusal', 'max_tokens'], true)) {
                    $this->note($user, $conversationId, __('assistant.notes.'.$reply->stopReason));
                }

                return;
            }

            $results = [];
            foreach ($reply->content as $block) {
                if (($block['type'] ?? null) !== 'tool_use') {
                    continue;
                }

                $input = is_array($block['input'] ?? null) ? $block['input'] : [];
                $result = $this->toolbox->run($user, (string) ($block['name'] ?? ''), $input, $conversationId);
                $results[] = ['type' => 'tool_result', 'tool_use_id' => $block['id'] ?? '', 'content' => $result['content'], 'is_error' => $result['is_error']];
            }

            $this->store($user, $conversationId, 'user', $results);
        }

        $this->note($user, $conversationId, __('assistant.notes.too_long'));
    }

    /**
     * Tells the model, on its next turn, something that happened outside the chat (an entry saved or cancelled).
     */
    public function note(User $user, string $conversationId, string $text): void
    {
        $this->store($user, $conversationId, 'note', $text);
    }

    /**
     * What the panel shows: the person's questions, the model's text, the entry cards and the notes, in order.
     *
     * @return list<array{type: string, text?: string, proposal?: int}>
     */
    public function transcript(User $user, string $conversationId): array
    {
        $items = [];

        foreach ($this->messages($user, $conversationId) as $message) {
            $blocks = $message->blocks();

            if (is_string($blocks)) {
                $items[] = ['type' => $message->role === 'note' ? 'note' : 'user', 'text' => $blocks];

                continue;
            }

            foreach ($blocks as $block) {
                $type = $block['type'] ?? null;

                if ($type === 'text' && trim((string) ($block['text'] ?? '')) !== '') {
                    $items[] = ['type' => 'assistant', 'text' => (string) $block['text']];
                } elseif ($type === 'tool_result' && ($block['is_error'] ?? false) !== true) {
                    $result = json_decode(is_string($block['content'] ?? null) ? $block['content'] : '', true);

                    if (is_array($result) && ($result['proposed'] ?? false) === true && is_int($result['proposal_id'] ?? null)) {
                        $items[] = ['type' => 'proposal', 'proposal' => $result['proposal_id']];
                    }
                }
            }
        }

        return $items;
    }

    /**
     * The conversation as the API takes it. A note is sent as the person's turn, marked as coming from the app.
     *
     * @return list<array{role: string, content: string|list<array<string, mixed>>}>
     */
    private function history(User $user, string $conversationId): array
    {
        $history = [];

        foreach ($this->messages($user, $conversationId) as $message) {
            $blocks = $message->blocks();

            $history[] = $message->role === 'note'
                ? ['role' => 'user', 'content' => '[Note from the app, not typed by the person] '.(is_string($blocks) ? $blocks : '')]
                : ['role' => $message->role, 'content' => $blocks];
        }

        return $history;
    }

    /**
     * Only the person's own messages, so a conversation id from the browser can never reach someone else's.
     *
     * @return iterable<AssistantMessage>
     */
    private function messages(User $user, string $conversationId): iterable
    {
        return AssistantMessage::query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', (int) $user->getAuthIdentifier())
            ->orderBy('id')
            ->get();
    }

    /** @param  string|list<array<string, mixed>>  $content */
    private function store(User $user, string $conversationId, string $role, string|array $content): void
    {
        AssistantMessage::query()->create([
            'user_id' => (int) $user->getAuthIdentifier(),
            'conversation_id' => $conversationId,
            'role' => $role,
            'content' => (string) json_encode($content, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION),
        ]);
    }

    /**
     * The fixed instructions first (cached across calls), then what changes per person and day.
     *
     * @return list<array<string, mixed>>
     */
    private function system(User $user): array
    {
        $roles = implode(', ', array_map(fn (RoleId $role) => $role->name, $user->roleIds()));

        return [
            ['type' => 'text', 'text' => self::INSTRUCTIONS, 'cache_control' => ['type' => 'ephemeral']],
            ['type' => 'text', 'text' => sprintf(
                'Today is %s. You are helping %s (roles: %s). The interface is in %s.',
                now()->toDateString(), $user->name, $roles !== '' ? $roles : 'none', Locale::current() === Locale::Arabic ? 'Arabic' : 'English',
            )],
        ];
    }
}
