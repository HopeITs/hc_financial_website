<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\ContractReport;
use App\Models\User;
use App\Services\Projects\ProjectAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A file kept with a funding contract: the signed contract, the donor's approval of the closing, or a report sent to
 * the donor. The route's `access:contracts` middleware checks the screen; a project manager only reaches the
 * contracts of their own projects.
 */
class ContractFileController extends Controller
{
    public function __invoke(Request $request, Contract $contract, string $kind, ProjectAccess $access, ?ContractReport $report = null): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        abort_unless(Contract::query()->whereKey($contract->id)->visibleTo($access->limitedTo($user))->exists(), 404);

        [$path, $name] = match ($kind) {
            'signed' => [$contract->signed_file_path, $contract->signed_file_name],
            'closing' => [$contract->closing_file_path, $contract->closing_file_name],
            'report' => $report !== null && $report->contract_id === $contract->id ? [$report->file_path, $report->file_name] : [null, null],
            default => [null, null],
        };

        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, (string) $name);
    }
}
