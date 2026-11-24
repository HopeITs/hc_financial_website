<?php

namespace App\Livewire;

use App\Enums\ScreenAction;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The reports in one page (finance decision of 2026-11-25), in three parts: the financial statements, the projects'
 * reports and the follow-up reports. Each report shows only to those who may open it, with its one-line description.
 */
#[Layout('components.layouts.app')]
class ReportsHome extends Component
{
    /** The parts and their reports (screen slug => icon), in the order they show. */
    public const SECTIONS = [
        'financial' => ['tb' => 'scale', 'statements' => 'document-chart-bar', 'statement' => 'queue-list'],
        'projects' => ['rexp' => 'arrow-up-circle', 'rrev' => 'arrows-up-down', 'rfixed' => 'cube', 'rattach' => 'paper-clip', 'rdonor' => 'building-library', 'rdonorfin' => 'document-currency-dollar'],
        'followup' => ['recon' => 'arrows-right-left', 'uncomp' => 'exclamation-triangle', 'xreserve' => 'chart-bar', 'advances' => 'hand-raised', 'loans' => 'banknotes'],
    ];

    public function render(): View
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->canOnScreen('reports', ScreenAction::View), 403);
        $sections = [];

        foreach (self::SECTIONS as $section => $reports) {
            foreach ($reports as $slug => $icon) {
                if ($user->canOnScreen($slug, ScreenAction::View)) {
                    $sections[$section][$slug] = ['icon' => $icon, 'url' => route(Navigation::routeName($slug))];
                }
            }
        }

        return view('livewire.reports-home', ['sections' => $sections]);
    }
}
