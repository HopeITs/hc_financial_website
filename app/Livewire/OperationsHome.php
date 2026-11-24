<?php

namespace App\Livewire;

use App\Enums\RequestStatus;
use App\Enums\ScreenAction;
use App\Enums\VoucherStatus;
use App\Models\LedgerRequest;
use App\Models\Purchase;
use App\Models\User;
use App\Models\Voucher;
use App\Support\Navigation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The organisation's financial operations in one place (finance request of 2026-11-24), in the order money moves:
 * received from the donors, moved between the organisation's accounts, spent (purchases and running costs, micro
 * projects, salaries) and received back (beneficiaries' instalments, the projects' revenues). Each card opens its
 * screen and shows only to those who may see it; a count says what is on its way there.
 */
#[Layout('components.layouts.app')]
class OperationsHome extends Component
{
    /**
     * The sections and their cards: card key => the screen it opens, its icon and, for the revenues and other payments,
     * the action needed (they open a voucher screen kept out of the menu).
     */
    public const SECTIONS = [
        'donors' => [
            'donor_receipts' => ['screen' => 'contracts', 'icon' => 'building-library'],
        ],
        'moves' => [
            'transfers' => ['screen' => 'transfers', 'icon' => 'arrows-right-left'],
        ],
        'spending' => [
            'requests' => ['screen' => 'requests', 'icon' => 'clipboard-document-list'],
            'purchases' => ['screen' => 'purchases', 'icon' => 'shopping-cart'],
            'advances' => ['screen' => 'advance', 'icon' => 'hand-raised'],
            'micro' => ['screen' => 'microhome', 'icon' => 'briefcase'],
            'salaries' => ['screen' => 'salaries', 'icon' => 'user-group'],
            'other_payments' => ['screen' => 'single', 'icon' => 'arrow-up-circle', 'action' => ScreenAction::Create],
        ],
        'income' => [
            'instalments' => ['screen' => 'payments', 'icon' => 'banknotes'],
            'revenues' => ['screen' => 'receipt', 'icon' => 'arrow-down-circle', 'action' => ScreenAction::Create],
        ],
    ];

    public function render(): View
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->canOnScreen('operations', ScreenAction::View), 403);
        $counts = $this->counts();
        $sections = [];

        foreach (self::SECTIONS as $section => $cards) {
            foreach ($cards as $key => $card) {
                if ($user->canOnScreen($card['screen'], $card['action'] ?? ScreenAction::View)) {
                    $sections[$section][$key] = $card + ['url' => route(Navigation::routeName($card['screen'])), 'count' => $counts[$key] ?? null];
                }
            }
        }

        return view('livewire.operations-home', [
            'sections' => $sections,
            'pendingVouchers' => $user->canOnScreen('vouchers', ScreenAction::Approve) ? Voucher::query()->where('status', VoucherStatus::PendingApproval->value)->count() : 0,
            'approvalUrl' => route(Navigation::routeName('approval')),
        ]);
    }

    /** @return array<string, int> what is on its way, by card */
    private function counts(): array
    {
        $counts = [];

        if (Schema::hasTable('gl_requests')) {
            $counts['requests'] = LedgerRequest::query()->where('status', RequestStatus::Submitted->value)->count();
        }

        if (Schema::hasTable('gl_purchases')) {
            $counts['purchases'] = Purchase::query()->whereNotIn('stage', [Purchase::PAID, Purchase::CANCELLED])->count();
        }

        return $counts;
    }
}
