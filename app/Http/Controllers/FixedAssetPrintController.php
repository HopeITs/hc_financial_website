<?php

namespace App\Http\Controllers;

use App\Models\AssetCount;
use App\Models\AssetMovement;
use App\Models\FixedAsset;
use App\Models\GlProject;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Assets\FixedAssets;
use App\Services\Projects\ProjectAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

/**
 * The papers of the fixed assets (finance request of 2026-11-28): a movement's note (receiving, handover, return,
 * transfer, repair, write-off, sale) to sign, and a count sheet. The route's `access` middleware checks the assets
 * screen; a person limited to their projects sees only theirs.
 */
class FixedAssetPrintController extends Controller
{
    public function movement(int $movement, FixedAssets $assets): View
    {
        $found = AssetMovement::query()->with('asset.category')->findOrFail($movement);
        $asset = $found->asset;
        abort_unless($asset instanceof FixedAsset, 404);
        $this->mayOpen($asset->project_code);

        return view('fixed-assets.movement', [
            'movement' => $found,
            'asset' => $asset,
            'project' => GlProject::query()->find($asset->project_code),
            'from' => $found->from_warehouse_id ? Warehouse::query()->find($found->from_warehouse_id) : null,
            'to' => $found->to_warehouse_id ? Warehouse::query()->find($found->to_warehouse_id) : null,
            'bookValue' => $assets->bookValue($asset),
            'preparer' => User::query()->find($found->created_by)?->name ?? '',
        ]);
    }

    public function count(int $count): View
    {
        $found = AssetCount::query()->with(['lines.asset', 'warehouse'])->findOrFail($count);
        $limit = $this->limit();

        return view('fixed-assets.count', [
            'count' => $found,
            'lines' => $found->lines->filter(fn ($line): bool => $line->asset !== null && ($limit === null || in_array($line->asset->project_code, $limit, true)))->values(),
            'counter' => User::query()->find($found->created_by)?->name ?? '',
        ]);
    }

    private function mayOpen(string $project): void
    {
        $limit = $this->limit();
        abort_unless($limit === null || in_array($project, $limit, true), 404);
    }

    /** @return list<string>|null */
    private function limit(): ?array
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return app(ProjectAccess::class)->limitedTo($user);
    }
}
