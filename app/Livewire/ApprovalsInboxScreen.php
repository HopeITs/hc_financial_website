<?php

namespace App\Livewire;

use App\Enums\ScreenAction;
use App\Models\User;
use App\Services\Home\ApprovalsInbox;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "Waiting for my approval" (finance decision of 2026-11-25): vouchers, requests, purchase orders, payrolls and
 * micro-project payment orders in one list, each approved with one click through its own rules, or all of a kind at
 * once. To send one back or reject it, the person opens it on its own screen.
 */
#[Layout('components.layouts.app')]
class ApprovalsInboxScreen extends Component
{
    public ?string $notice = null;

    /** @var list<string> */
    public array $problems = [];

    public function approve(ApprovalsInbox $inbox, string $kind, string $id): void
    {
        $this->clearFeedback();
        $refused = $inbox->approve($this->user(), $kind, $id);

        if ($refused !== []) {
            $this->problems = $refused;

            return;
        }

        $this->notice = __('approvals.approved_one');
    }

    /** Approves every item of a kind; those a rule refuses stay, with the reason. */
    public function approveAll(ApprovalsInbox $inbox, string $kind): void
    {
        $this->clearFeedback();
        $done = 0;

        foreach ($inbox->for($this->user())[$kind] ?? [] as $item) {
            $refused = $inbox->approve($this->user(), $kind, $item['id']);

            if ($refused === []) {
                $done++;
            } else {
                $this->problems = [...$this->problems, ...array_map(fn (string $reason): string => $item['title'].': '.$reason, $refused)];
            }
        }

        $this->notice = trans_choice('approvals.approved_many', $done, ['count' => $done]);
    }

    public function render(ApprovalsInbox $inbox): View
    {
        abort_unless($this->user()->canOnScreen('inbox', ScreenAction::View), 403);

        return view('livewire.approvals-inbox', ['inbox' => array_filter($inbox->for($this->user()))]);
    }

    private function clearFeedback(): void
    {
        $this->notice = null;
        $this->problems = [];
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
