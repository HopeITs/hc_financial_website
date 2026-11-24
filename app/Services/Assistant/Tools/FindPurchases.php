<?php

namespace App\Services\Assistant\Tools;

use App\Models\Purchase;
use App\Models\User;
use App\Services\Purchasing\PurchaseFiles;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase files (PR → quotes → PO → receipt → payment), within the projects the person may see.
 */
class FindPurchases extends Tool
{
    public function __construct(private readonly PurchaseFiles $files) {}

    public function name(): string
    {
        return 'find_purchases';
    }

    public function allows(User $user): bool
    {
        return $this->can($user, 'purchases');
    }

    public function definition(): array
    {
        return [
            'description' => 'Purchase files, newest first: number (PR-/PO-0012), what is bought, project, stage (sourcing = collecting quotes, submitted = purchase order waiting for approval, ordered, received, paid, cancelled), goods or service, currency, estimated and ordered totals, quotes received and needed, the chosen supplier, the role whose approval it waits for, the payment method and the payment voucher.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'search' => ['type' => 'string', 'description' => 'Part of what is bought, a supplier, or the file number.'],
                    'stage' => ['type' => 'string', 'enum' => [...Purchase::STAGES, Purchase::CANCELLED]],
                    'project' => ['type' => 'string', 'description' => 'Exact project code.'],
                ],
            ],
        ];
    }

    public function run(User $user, array $input, string $conversationId): array
    {
        if (! Schema::hasTable('gl_purchases')) {
            return ['purchases' => []];
        }

        $term = $this->text($input, 'search', 80);
        $number = preg_replace('/\D/', '', $term);
        $stage = $this->text($input, 'stage', 20);
        $project = $this->text($input, 'project', 40);
        $limit = $this->projectLimit($user);

        $purchases = Purchase::query()->with(['items', 'quotes', 'awardedQuote'])
            ->when($limit !== null, fn ($query) => $query->whereIn('project_code', $limit ?? []))
            ->when($stage !== '', fn ($query) => $query->where('stage', $stage))
            ->when($project !== '', fn ($query) => $query->where('project_code', $project))
            ->when($term !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('title', 'like', '%'.$term.'%')
                ->orWhereHas('quotes', fn ($quotes) => $quotes->where('supplier', 'like', '%'.$term.'%'))
                ->when($number !== '', fn ($byId) => $byId->orWhere('id', (int) $number))))
            ->orderByDesc('id')
            ->limit($this->maxRows())
            ->get();

        return ['purchases' => $purchases->map(fn (Purchase $purchase): array => [
            'number' => $purchase->number('PR'),
            'title' => $purchase->title,
            'project' => $purchase->project_code,
            'stage' => $purchase->stage,
            'kind' => $purchase->kind,
            'currency' => $purchase->currency,
            'needed_by' => $purchase->needed_by?->toDateString(),
            'estimated_total' => $this->files->estimatedTotal($purchase),
            'ordered_total' => $purchase->awardedQuote !== null ? $this->files->orderTotal($purchase) : null,
            'quotes_received' => $purchase->quotes->count(),
            'quotes_needed' => $this->files->quotesNeeded($purchase),
            'supplier' => $purchase->awardedQuote?->supplier,
            'waits_for' => $this->files->nextRole($purchase)?->name,
            'return_reason' => $purchase->return_reason,
            'payment_method' => $purchase->payment_method,
            'voucher' => $purchase->voucher_id,
        ])->values()->all()];
    }
}
