<?php

namespace App\Livewire;

use App\Models\User;
use App\Models\WebNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The bell in the top bar (U-01): the person's unread count and latest notifications, checked every minute. Opening
 * one marks it read and goes to its voucher or request; the notifications page lists them all. A person only ever
 * sees and changes their own.
 */
class NotificationBell extends Component
{
    public const SHOWN = 10;

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
        return view('livewire.notification-bell', [
            'unread' => $this->mine()->whereNull('read_at')->count(),
            'latest' => $this->mine()->latest()->latest('id')->limit(self::SHOWN)->get(),
        ]);
    }

    /** @return Builder<WebNotification> */
    private function mine(): Builder
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return WebNotification::query()->for($user);
    }
}
