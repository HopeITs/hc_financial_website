<?php

namespace App\Http\Controllers;

use App\Enums\ScreenAction;
use App\Models\GlProject;
use App\Models\RevenueSheet;
use App\Models\RevenueTicket;
use App\Models\User;
use App\Services\Entries\PointOfSale;
use App\Services\Projects\ProjectAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

/**
 * The point of sale's papers (finance request of 2026-11-27): an order's receipt, on a narrow till-roll page, and the
 * shift's end-of-day report. The route's `access` middleware checks the revenues screen; a person limited to their
 * projects sees only those.
 */
class PointOfSalePrintController extends Controller
{
    public function receipt(int $order): View
    {
        $found = RevenueTicket::query()->with(['lines', 'sheet'])->findOrFail($order);
        $shift = $found->sheet;
        abort_unless($shift instanceof RevenueSheet, 404);
        $this->mayOpen($shift);

        if ($found->printed_at === null) {
            $found->update(['printed_at' => now()]);
        }

        return view('point-of-sale.receipt', [
            'order' => $found,
            'shift' => $shift,
            'project' => GlProject::query()->find($shift->project_code),
        ]);
    }

    public function report(int $shift, PointOfSale $pointOfSale): View
    {
        $found = RevenueSheet::query()->findOrFail($shift);
        $this->mayOpen($found);

        return view('point-of-sale.report', [
            'shift' => $found,
            'project' => GlProject::query()->find($found->project_code),
            'report' => $pointOfSale->report($found),
            'cashier' => User::query()->find($found->created_by)?->name ?? '',
        ]);
    }

    private function mayOpen(RevenueSheet $shift): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->canOnScreen('revenues', ScreenAction::View), 403);
        abort_unless(app(ProjectAccess::class)->canOpen($user, $shift->project_code), 404);
    }
}
