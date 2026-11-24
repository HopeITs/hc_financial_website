<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\PurchaseQuote;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Sends a supplier's quote scan from the private disk to a person who may see its purchase file's project. */
class PurchaseQuoteController extends Controller
{
    public function __invoke(PurchaseQuote $quote): StreamedResponse
    {
        $purchase = Purchase::query()->findOrFail($quote->purchase_id);
        PurchasePrintController::authorizeProject($purchase);

        abort_if($quote->stored_path === null || ! Storage::disk('local')->exists($quote->stored_path), 404);

        return Storage::disk('local')->download($quote->stored_path, $quote->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }
}
