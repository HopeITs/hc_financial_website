<?php

namespace App\Http\Controllers;

use App\Models\MicroPurchase;
use App\Models\MicroQuote;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sends a micro-project file's quote or invoice scan from the private disk. The route's `screen` middleware checks that
 * the person may view the micro-project files.
 */
class MicroFileDocumentController extends Controller
{
    public function __invoke(string $kind, int $id): StreamedResponse
    {
        $document = match ($kind) {
            'quote' => MicroQuote::query()->findOrFail($id),
            'invoice' => MicroPurchase::query()->findOrFail($id),
            default => abort(404),
        };

        abort_if($document->stored_path === null || ! Storage::disk('local')->exists($document->stored_path), 404);

        return Storage::disk('local')->download($document->stored_path, $document->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }
}
