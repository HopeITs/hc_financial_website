<?php

namespace App\Livewire;

use App\Enums\ScreenAction;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherAudit;
use App\Services\Treasury\CashCounts;
use App\Support\Navigation;
use App\Support\UserPreferences;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "My till" (role review of 2026-11-26): the cashier's day in one page. The cash and bank accounts of their city with
 * their book balance and whether they were counted today, today's moves on them, the vouchers the person prepared
 * lately with their state (a rejected one with its reason), and the buttons of their daily work. The city chosen is
 * remembered for the person.
 */
#[Layout('components.layouts.app')]
class MyTill extends Component
{
    /** How far back the person's own vouchers go. */
    public const MY_DAYS = 30;

    private const PREFERENCE = 'my_till.city';

    public string $city = '';

    public function mount(CashCounts $counts, UserPreferences $preferences): void
    {
        $remembered = (string) $preferences->get((int) $this->user()->getAuthIdentifier(), self::PREFERENCE);
        $this->city = in_array($remembered, $counts->cities(), true) ? $remembered : ($counts->cities()[0] ?? '');
    }

    public function updatedCity(CashCounts $counts, UserPreferences $preferences): void
    {
        if (in_array($this->city, $counts->cities(), true)) {
            $preferences->set((int) $this->user()->getAuthIdentifier(), self::PREFERENCE, $this->city);
        }
    }

    public function render(CashCounts $counts): View
    {
        $user = $this->user();
        abort_unless($user->canOnScreen('mytill', ScreenAction::View), 403);
        $today = CarbonImmutable::today();
        $accounts = $counts->rows($today, $this->city);

        return view('livewire.my-till', [
            'cities' => $counts->cities(),
            'accounts' => $accounts,
            'moves' => $this->moves($today, array_column($accounts, 'code')),
            'mine' => $this->mine($user),
            'actions' => $this->actions($user),
        ]);
    }

    /**
     * Today's voucher lines on the city's cash accounts, newest first.
     *
     * @param  list<string>  $codes
     * @return list<array{voucher: string, description: string, account: string, currency: string, in: string, out: string, status: string}>
     */
    private function moves(CarbonImmutable $today, array $codes): array
    {
        if ($codes === []) {
            return [];
        }

        return DB::table('gl_voucher_lines as l')
            ->join('gl_vouchers as v', 'v.id', '=', 'l.voucher_id')
            ->whereIn('l.account_code', $codes)
            ->whereDate('v.date', $today->toDateString())
            ->whereNull('v.deleted_at')
            ->where('v.status', '!=', 'replaced')
            ->orderByDesc('v.id')
            ->limit(100)
            ->get(['v.id', 'v.description', 'v.status', 'l.account_code', 'l.currency', 'l.debit_orig', 'l.credit_orig'])
            ->map(fn (object $row): array => [
                'voucher' => (string) $row->id,
                'description' => (string) $row->description,
                'account' => (string) $row->account_code,
                'currency' => (string) $row->currency,
                'in' => (string) $row->debit_orig,
                'out' => (string) $row->credit_orig,
                'status' => (string) $row->status,
            ])->all();
    }

    /**
     * The vouchers the person prepared lately, a rejected one with the reason it was given.
     *
     * @return list<array{voucher: Voucher, badge: string, reason: string|null}>
     */
    private function mine(User $user): array
    {
        $vouchers = Voucher::withTrashed()
            ->where('prepared_by', (int) $user->getAuthIdentifier())
            ->whereDate('date', '>=', CarbonImmutable::today()->subDays(self::MY_DAYS)->toDateString())
            ->orderByDesc('date')->orderByDesc('id')
            ->limit(30)
            ->get();
        $reasons = VoucherAudit::query()->whereIn('voucher_id', $vouchers->filter(fn (Voucher $voucher): bool => $voucher->trashed())->pluck('id')->all())
            ->where('action', 'reject')->orderBy('id')->pluck('reason', 'voucher_id');

        return $vouchers->map(fn (Voucher $voucher): array => [
            'voucher' => $voucher,
            'badge' => $voucher->trashed() ? 'rejected' : $voucher->status->value,
            'reason' => $voucher->trashed() ? ($reasons[$voucher->id] ?? null) : null,
        ])->all();
    }

    /** @return list<array{slug: string, icon: string, url: string}> the daily work the person may do, as buttons */
    private function actions(User $user): array
    {
        $buttons = [
            'tills' => ['calculator', ScreenAction::Create],
            'single' => ['arrow-up-circle', ScreenAction::Create],
            'receipt' => ['arrow-down-tray', ScreenAction::Create],
            'advance' => ['hand-raised', ScreenAction::Create],
            'payments' => ['banknotes', ScreenAction::Create],
            'revenues' => ['arrow-down-circle', ScreenAction::Create],
        ];
        $actions = [];

        foreach ($buttons as $slug => [$icon, $action]) {
            if ($user->canOnScreen($slug, $action)) {
                $actions[] = ['slug' => $slug, 'icon' => $icon, 'url' => route(Navigation::routeName($slug))];
            }
        }

        return $actions;
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
