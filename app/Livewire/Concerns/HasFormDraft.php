<?php

namespace App\Livewire\Concerns;

use App\Support\FormDrafts;
use Illuminate\Support\Facades\Gate;

/**
 * Save as draft for an entry form (U-04). The person presses Save as draft; when they come back to the screen they are
 * offered the draft to restore or delete. Saving the entry for real deletes the draft. Uploaded files are never kept
 * in a draft. Only the fields the component names are saved and restored, and only with the type they have.
 */
trait HasFormDraft
{
    /** When the person's earlier draft of this screen was saved, while it is offered to restore. */
    public ?string $draftSavedAt = null;

    public ?string $draftNotice = null;

    /** @return list<string> the properties a draft keeps */
    abstract protected function draftFields(): array;

    /** The key of the draft, one per person and screen. */
    abstract protected function draftScreen(): string;

    /** @return array{0: string, 1: string} the screen slug and action that saving the entry needs */
    abstract protected function draftPermission(): array;

    public function mountHasFormDraft(): void
    {
        $draft = app(FormDrafts::class)->find($this->draftOwner(), $this->draftScreen());
        $this->draftSavedAt = $draft === null ? null : $draft['saved_at']->toIso8601String();
    }

    public function saveDraft(): void
    {
        Gate::authorize('screen', $this->draftPermission());

        if (! app(FormDrafts::class)->save($this->draftOwner(), $this->draftScreen(), $this->only($this->draftFields()))) {
            $this->draftNotice = __('drafts.too_big');

            return;
        }

        $this->draftSavedAt = null;
        $this->draftNotice = __('drafts.saved', ['time' => now()->format('H:i')]);
        $this->dispatch('form-clean');
    }

    public function restoreDraft(): void
    {
        $draft = app(FormDrafts::class)->find($this->draftOwner(), $this->draftScreen());
        $this->draftSavedAt = null;

        if ($draft === null) {
            return;
        }

        foreach ($this->draftFields() as $field) {
            $value = $draft['payload'][$field] ?? null;

            if ($value !== null && get_debug_type($value) === get_debug_type($this->{$field})) {
                $this->{$field} = $value;
            }
        }

        $this->restoredDraft($draft['payload']);
        $this->draftNotice = __(property_exists($this, 'attachments') ? 'drafts.restored_files' : 'drafts.restored');
        $this->dispatch('form-clean');
    }

    public function discardDraft(): void
    {
        app(FormDrafts::class)->forget($this->draftOwner(), $this->draftScreen());
        $this->draftSavedAt = null;
        $this->draftNotice = __('drafts.discarded');
    }

    /**
     * Lets a form rebuild a field from the raw draft when its type alone says too little, such as a grid of lines.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function restoredDraft(array $payload): void {}

    /** Called once the entry itself is saved. */
    protected function forgetDraft(): void
    {
        app(FormDrafts::class)->forget($this->draftOwner(), $this->draftScreen());
    }

    private function draftOwner(): int
    {
        $user = auth()->user();
        abort_if($user === null, 403);

        return (int) $user->getAuthIdentifier();
    }
}
