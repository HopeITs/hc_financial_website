<?php

namespace App\Http\Controllers;

use App\Models\GlProject;
use App\Models\Purchase;
use App\Models\User;
use App\Services\Projects\ProjectAccess;
use App\Services\Purchasing\PurchaseFiles;
use Illuminate\Contracts\View\View;

/**
 * The procurement forms of a purchase file, filled from the program (finance request of 2026-11-24), on the
 * organisation's letterhead: purchase request, request for quotation, competitive bid analysis, purchase order, goods
 * or service receiving note, and request for direct payment. The route's `screen` middleware checks that the person
 * may view purchase files; this checks the project.
 */
class PurchasePrintController extends Controller
{
    public const FORMS = ['pr', 'rfq', 'cba', 'po', 'grn', 'srn', 'rdp'];

    public function __invoke(int $purchase, string $form, PurchaseFiles $files): View
    {
        abort_unless(in_array($form, self::FORMS, true), 404);
        $found = Purchase::query()->with(['items.budgetLine', 'request', 'project', 'awardedQuote', 'quotes'])->findOrFail($purchase);
        self::authorizeProject($found);

        $names = [];
        foreach (User::query()->whereIn('ID', [$found->created_by, ...array_column($found->approvals ?? [], 'user')])->get() as $person) {
            $names[(int) $person->getAuthIdentifier()] = $person->name;
        }

        $orderTotal = $found->awarded_quote_id !== null ? $files->orderTotal($found) : null;

        return view('purchases.print', [
            'purchase' => $found,
            'form' => $form,
            'estimated' => $files->estimatedTotal($found),
            'comparison' => $files->comparison($found),
            'orderLines' => $found->awarded_quote_id !== null ? $files->orderLines($found) : [],
            'orderTotal' => $orderTotal,
            'orderUsd' => $orderTotal !== null ? $files->usd($found, $orderTotal) : null,
            'names' => $names,
        ]);
    }

    /** Refuses a person limited to other projects than the file's. */
    public static function authorizeProject(Purchase $purchase): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);
        $accountId = GlProject::query()->where('code', $purchase->project_code)->value('legacy_account_id');
        $limit = app(ProjectAccess::class)->limitedTo($user);

        abort_unless($limit === null || ($accountId !== null && in_array((int) $accountId, $limit, true)), 403);
    }
}
