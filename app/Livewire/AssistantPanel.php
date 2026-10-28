<?php

namespace App\Livewire;

use App\Models\AssistantProposal;
use App\Models\User;
use App\Services\Assistant\Assistant;
use App\Services\Assistant\AssistantUnavailable;
use App\Services\Assistant\EntryProposals;
use App\Services\Assistant\ProposalRefused;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Session;
use Livewire\Component;

/**
 * The AI assistant's chat panel, on every page for the people it can help. The person asks; the assistant answers
 * with the tools their screens allow and shows each entry it prepared as a card, which the person saves or cancels.
 */
class AssistantPanel extends Component
{
    #[Session(key: 'assistant.open')]
    public bool $open = false;

    #[Session(key: 'assistant.conversation')]
    public string $conversation = '';

    public string $question = '';

    public ?string $error = null;

    public ?string $notice = null;

    public function mount(): void
    {
        if (! Str::isUuid($this->conversation)) {
            $this->conversation = (string) Str::uuid();
        }
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function send(Assistant $assistant): void
    {
        $this->reset('error', 'notice');
        $this->validate(['question' => ['required', 'string', 'max:4000']]);

        // A question can take several model calls and tool runs. Only ever raise the limit: where there is none (the
        // command line, tests) setting one would cap the rest of the process.
        $limit = (int) ini_get('max_execution_time');
        if ($limit !== 0 && $limit < 180) {
            set_time_limit(180);
        }

        try {
            $assistant->ask($this->user(), $this->conversation, $this->question);
            $this->question = '';
        } catch (AssistantUnavailable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function save(int $proposal, EntryProposals $proposals, Assistant $assistant): void
    {
        $this->reset('error', 'notice');

        try {
            $voucher = $proposals->confirm($this->user(), $proposal);
        } catch (ProposalRefused $e) {
            $this->error = implode(' ', $e->messages);

            return;
        }

        $this->notice = __('assistant.proposal.saved_notice', ['number' => $voucher->id]);
        $assistant->note($this->user(), $this->conversation, __('assistant.notes.saved', ['proposal' => $proposal, 'number' => $voucher->id]));
    }

    public function cancel(int $proposal, EntryProposals $proposals, Assistant $assistant): void
    {
        $this->reset('error', 'notice');

        try {
            $proposals->cancel($this->user(), $proposal);
        } catch (ProposalRefused $e) {
            $this->error = implode(' ', $e->messages);

            return;
        }

        $assistant->note($this->user(), $this->conversation, __('assistant.notes.cancelled', ['proposal' => $proposal]));
    }

    public function startOver(): void
    {
        $this->reset('question', 'error', 'notice');
        $this->conversation = (string) Str::uuid();
    }

    /** Whether the key for the chosen AI service is set. */
    private function hasKey(): bool
    {
        $key = config(config('assistant.provider') === 'gemini' ? 'assistant.gemini.api_key' : 'assistant.api_key');

        return is_string($key) && $key !== '';
    }

    public function render(Assistant $assistant, EntryProposals $proposals): View
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $assistant->availableFor($user)) {
            return view('livewire.assistant-panel', ['available' => false]);
        }

        $items = $this->open ? $assistant->transcript($user, $this->conversation) : [];
        $ids = array_values(array_filter(array_map(fn (array $item) => $item['proposal'] ?? null, $items)));

        $cards = AssistantProposal::query()->whereIn('id', $ids)->where('user_id', (int) $user->getAuthIdentifier())->get()
            ->mapWithKeys(fn (AssistantProposal $proposal) => [$proposal->id => [
                'proposal' => $proposal,
                'preview' => $proposal->status === AssistantProposal::PENDING ? $proposals->previewOf($proposal) : [],
            ]]);

        return view('livewire.assistant-panel', [
            'available' => true,
            'configured' => $this->hasKey(),
            'items' => $items,
            'cards' => $cards,
            'hasHistory' => $items !== [],
        ]);
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
