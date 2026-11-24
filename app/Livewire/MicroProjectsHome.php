<?php

namespace App\Livewire;

use App\Enums\ScreenAction;
use App\Models\User;
use App\Services\Loans\MicroOverview;
use App\Support\Navigation;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The micro-projects section in one place (finance request of 2026-11-23): the steps of a file with how many are at
 * each, what waits for the person, the loans' instalments owed and late, the funds' value, and the way to every screen
 * of the section.
 */
#[Layout('components.layouts.app')]
class MicroProjectsHome extends Component
{
    public function render(MicroOverview $overview): View
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->canOnScreen('microhome', ScreenAction::View), 403);
        $today = CarbonImmutable::today();
        $instalments = $overview->instalments($today, $user);
        $fundsValue = $overview->fundsValue($today);
        $amounts = fn (array $byCurrency): array => array_map(fn (string $amount): string => number_format((float) $amount, 2), $byCurrency);

        return view('livewire.micro-projects-home', [
            'steps' => $overview->steps($user),
            'waiting' => $overview->waitingFor($user),
            'loans' => $instalments['loans'],
            'owed' => $amounts($instalments['owed']),
            'late' => $amounts($instalments['late']),
            'lateLoans' => array_map(fn (array $row): array => $row + ['amount_label' => number_format((float) $row['amount'], 2)], $instalments['late_loans']),
            'fundsValue' => $fundsValue !== null ? number_format((float) $fundsValue, 2) : '—',
            'can' => [
                'files' => $user->canOnScreen('microfiles', ScreenAction::View),
                'newFile' => $user->canOnScreen('microfiles', ScreenAction::Create),
                'loans' => $user->canOnScreen('payments', ScreenAction::View),
                'receive' => $user->canOnScreen('payments', ScreenAction::Create),
                'funds' => $user->canOnScreen('loanfunds', ScreenAction::View),
                'report' => $user->canOnScreen('loans', ScreenAction::View),
            ],
            'routes' => [
                'files' => route(Navigation::routeName('microfiles')),
                'loans' => route(Navigation::routeName('payments')),
                'funds' => route(Navigation::routeName('loanfunds')),
                'report' => route(Navigation::routeName('loans')),
            ],
        ]);
    }
}
