<?php

namespace App\Livewire;

use App\Models\User;
use App\Models\WebNotification;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The bell in the top bar (U-01): the person's unread count and latest notifications, checked every few seconds; a new one pops up at once, without reloading the page. Opening
 * one marks it read and goes to its voucher or request; the notifications page lists them all. A person only ever
 * sees and changes their own.
 */
class NotificationBell extends Component
{
    public const SHOWN = 10;

    /** The newest notification the person was already shown, so only later ones pop up (finance request of 2026-11-26). */
    #[Locked]
    public ?int $newestSeen = null;

    public function open(int $id): void
    {
        $notification = $this->mine()->findOrFail($id);
        $notification->markRead();

        $this->redirect($notification->url(), navigate: true);
    }

    public function markAllRead(): void
    {
        $this->mine()->whereNull('read_at')->update(['read_at' => now()]);
        $this->dispatch('notifications-read');
    }

    /** The notifications page marked some read: show the new count straight away. */
    #[On('notifications-read')]
    public function refreshCount(): void {}

    public function render(): View
    {
        $this->announceNew();

        return view('livewire.notification-bell', [
            'unread' => $this->mine()->whereNull('read_at')->count(),
            'latest' => $this->mine()->latest()->latest('id')->limit(self::SHOWN)->get(),
        ]);
    }

    /**
     * Pops up what arrived since the bell last looked, without reloading the page. The first look only remembers
     * where things stand, so opening a page never pops up old notifications.
     */
    private function announceNew(): void
    {
        $newest = (int) ($this->mine()->max('id') ?? 0);

        if ($this->newestSeen !== null && $newest > $this->newestSeen) {
            $arrived = $this->mine()->where('id', '>', $this->newestSeen)->whereNull('read_at')->latest('id')->get();
            $first = $arrived->first();

            if ($first instanceof WebNotification) {
                Flux::toast(
                    text: $arrived->count() > 1 ? __('notifications.arrived_more', ['count' => $arrived->count() - 1]).' '.$first->message() : $first->message(),
                    heading: $first->title(),
                );
                $this->dispatch('notification-arrived', count: $arrived->count());
            }
        }

        $this->newestSeen = max($newest, $this->newestSeen ?? 0);
    }

    /** @return Builder<WebNotification> */
    private function mine(): Builder
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return WebNotification::query()->for($user);
    }
}
