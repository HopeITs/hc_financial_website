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

    /** @var list<string> the ticked items, as "kind|id" (role review of 2026-11-26) */
    public array $selected = [];

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

    /** Approves the ticked items of every kind; those a rule refuses stay ticked, with the reason. */
    public function approveSelected(ApprovalsInbox $inbox): void
    {
        $this->approveMany($inbox, array_values(array_filter($this->listed($inbox), fn (array $item): bool => in_array($item['kind'].'|'.$item['id'], $this->selected, true))));
    }

    /** Approves everything waiting, of every kind. */
    public function approveEverything(ApprovalsInbox $inbox): void
    {
        $this->approveMany($inbox, $this->listed($inbox));
    }

    /** @param  list<array{kind: string, id: string, title: string}>  $items */
    private function approveMany(ApprovalsInbox $inbox, array $items): void
    {
        $this->clearFeedback();
        $done = 0;
        $left = [];

        foreach ($items as $item) {
            $refused = $inbox->approve($this->user(), $item['kind'], $item['id']);

            if ($refused === []) {
                $done++;
            } else {
                $left[] = $item['kind'].'|'.$item['id'];
                $this->problems = [...$this->problems, ...array_map(fn (string $reason): string => $item['title'].': '.$reason, $refused)];
            }
        }

        $this->selected = $left;
        $this->notice = trans_choice('approvals.approved_many', $done, ['count' => $done]);
    }

    /** @return list<array{kind: string, id: string, title: string}> everything waiting, as the list shows it */
    private function listed(ApprovalsInbox $inbox): array
    {
        $items = [];

        foreach ($inbox->for($this->user()) as $kind => $rows) {
            foreach ($rows as $row) {
                $items[] = ['kind' => $kind, 'id' => $row['id'], 'title' => $row['title']];
            }
        }

        return $items;
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
