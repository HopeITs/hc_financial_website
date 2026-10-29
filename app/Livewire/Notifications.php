<?php

namespace App\Livewire;

use App\Enums\NotificationKind;
use App\Models\User;
use App\Models\WebNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Every notification of the signed-in person (U-01), newest first and grouped by day, with filters for unread ones
 * and for vouchers or requests. Opening one marks it read and goes to its voucher or request, like the bell.
 */
#[Layout('components.layouts.app')]
class Notifications extends Component
{
    use WithPagination;

    public const PER_PAGE = 25;

    #[Url(as: 'show', except: 'all')]
    public string $show = 'all';

    #[Url(as: 'about', except: 'all')]
    public string $about = 'all';

    public function updatedShow(): void
    {
        $this->resetPage();
    }

    public function updatedAbout(): void
    {
        $this->resetPage();
    }

    public function open(int $id): void
    {
        $notification = $this->mine()->findOrFail($id);
        $notification->markRead();

        $this->redirect($notification->url(), navigate: true);
    }

    public function markRead(int $id): void
    {
        $this->mine()->findOrFail($id)->markRead();
        $this->dispatch('notifications-read');
    }

    public function markAllRead(): void
    {
        $this->mine()->whereNull('read_at')->update(['read_at' => now()]);
        $this->dispatch('notifications-read');
    }

    /** The bell marked them all read: show it here too. */
    #[On('notifications-read')]
    public function refreshList(): void {}

    public function render(): View
    {
        $query = $this->mine();

        if ($this->show === 'unread') {
            $query->whereNull('read_at');
        }

        $kinds = match ($this->about) {
            'vouchers' => NotificationKind::aboutVouchers(),
            'requests' => NotificationKind::aboutRequests(),
            default => null,
        };

        if ($kinds !== null) {
            $query->whereIn('kind', array_map(fn (NotificationKind $kind): string => $kind->value, $kinds));
        }

        $notifications = $query->latest()->latest('id')->paginate(self::PER_PAGE);

        return view('livewire.notifications', [
            'notifications' => $notifications,
            'days' => collect($notifications->items())->groupBy(fn (WebNotification $n): string => $this->dayLabel($n->created_at)),
            'unread' => $this->mine()->whereNull('read_at')->count(),
            'total' => $this->mine()->count(),
        ]);
    }

    private function dayLabel(Carbon $date): string
    {
        return match (true) {
            $date->isToday() => __('notifications.today'),
            $date->isYesterday() => __('notifications.yesterday'),
            default => $date->translatedFormat('l j F Y'),
        };
    }

    /** @return Builder<WebNotification> */
    private function mine(): Builder
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return WebNotification::query()->for($user);
    }
}
