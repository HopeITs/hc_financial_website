<?php

namespace App\Http\Controllers;

use App\Models\LedgerRequest;
use App\Models\RequestQuote;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Sends a price quote from the private disk to a person who may see its request. */
class RequestQuoteController extends Controller
{
    public function __invoke(RequestQuote $quote): StreamedResponse
    {
        $request = LedgerRequest::query()->findOrFail($quote->request_id);
        Gate::authorize('view', $request);

        abort_unless(Storage::disk('local')->exists($quote->stored_path), 404);

        return Storage::disk('local')->download($quote->stored_path, $quote->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }
}
