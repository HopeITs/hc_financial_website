<?php

namespace App\Services\Assistant;

use App\Enums\ScreenAction;
use App\Models\AssistantProposal;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Ledger\BuiltVoucher;
use App\Services\Ledger\LedgerRuleException;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\OperationEntry;
use App\Services\Ledger\PostVoucher;
use Illuminate\Support\Facades\DB;

/**
 * The entries the assistant prepares. The model only proposes: the operation goes through the single entry screen's
 * own rule (OperationEntry) and the posting rules, and waits as a proposal. It becomes a voucher, pending approval like
 * any other, only when its person presses Save; it is then built and checked again, so nothing stale is posted.
 */
class EntryProposals
{
    public const FIELDS = ['kind', 'date', 'project', 'budget_line', 'cash', 'account', 'to_cash', 'amount', 'rate', 'contact', 'fund_source', 'document', 'description'];

    public function __construct(
        private readonly OperationEntry $entry,
        private readonly PostVoucher $posting,
    ) {}

    public static function mayEnter(User $user): bool
    {
        return $user->canOnScreen('single', ScreenAction::Create);
    }

    /**
     * Builds and checks the operation; when it is good, keeps it as a proposal waiting for Save.
     *
     * @param  array<string, string>  $operation
     * @return array<string, mixed> what the model is told: the proposal and its lines, or the problems to fix
     */
    public function propose(User $user, string $conversationId, array $operation): array
    {
        $operation = array_intersect_key($operation, array_flip(self::FIELDS));
        $built = $this->entry->build($operation, (int) $user->getAuthIdentifier());
        $problems = $this->problems($built);

        if ($problems !== []) {
            return ['proposed' => false, 'problems' => $problems];
        }

        $proposal = AssistantProposal::query()->create([
            'user_id' => (int) $user->getAuthIdentifier(),
            'conversation_id' => $conversationId,
            'operation' => $operation,
            'status' => AssistantProposal::PENDING,
        ]);

        return ['proposed' => true, 'proposal_id' => $proposal->id, 'saved' => false] + $this->preview($built);
    }

    /**
     * What a proposal will post, for its card.
     *
     * @return array<string, mixed>
     */
    public function previewOf(AssistantProposal $proposal): array
    {
        $built = $this->entry->build($proposal->operation, $proposal->user_id);

        return $this->preview($built) + ['problems' => $this->problems($built)];
    }

    /**
     * Saves the proposal as a pending voucher prepared by its person.
     *
     * @throws ProposalRefused
     */
    public function confirm(User $user, int $proposalId): Voucher
    {
        if (! self::mayEnter($user)) {
            throw new ProposalRefused([__('assistant.proposal.not_allowed')]);
        }

        return DB::transaction(function () use ($user, $proposalId) {
            $proposal = $this->pending($user, $proposalId);
            $built = $this->entry->build($proposal->operation, (int) $user->getAuthIdentifier());
            $problems = $this->problems($built);

            if ($problems !== [] || $built->draft === null) {
                throw new ProposalRefused($problems);
            }

            try {
                $voucher = $this->posting->post($built->draft);
            } catch (LedgerRuleException $e) {
                throw new ProposalRefused(array_map(fn ($violation) => $violation->message, $e->violations));
            }

            $proposal->update(['status' => AssistantProposal::SAVED, 'voucher_id' => $voucher->id]);

            return $voucher;
        });
    }

    /** @throws ProposalRefused */
    public function cancel(User $user, int $proposalId): void
    {
        $this->pending($user, $proposalId)->update(['status' => AssistantProposal::CANCELLED]);
    }

    /** @throws ProposalRefused */
    private function pending(User $user, int $proposalId): AssistantProposal
    {
        $proposal = AssistantProposal::query()
            ->whereKey($proposalId)
            ->where('user_id', (int) $user->getAuthIdentifier())
            ->where('status', AssistantProposal::PENDING)
            ->lockForUpdate()
            ->first();

        if ($proposal === null) {
            throw new ProposalRefused([__('assistant.proposal.gone')]);
        }

        return $proposal;
    }

    /** @return list<string> */
    private function problems(BuiltVoucher $built): array
    {
        if ($built->problems !== []) {
            return $built->problems;
        }

        if ($built->draft === null) {
            return [__('ledger.builder.no_lines')];
        }

        return array_map(fn ($violation) => $violation->message, $this->posting->check($built->draft));
    }

    /** @return array<string, mixed> */
    private function preview(BuiltVoucher $built): array
    {
        $draft = $built->draft;

        if ($draft === null) {
            return [];
        }

        return [
            'type' => $draft->type->value,
            'date' => $draft->date->toDateString(),
            'description' => $draft->description,
            'lines' => array_map(fn (LineDraft $line) => [
                'account' => $line->accountCode,
                'project' => $line->projectCode,
                'budget_line' => $line->budgetLineKey,
                'currency' => $line->currency,
                'debit' => $line->debitOrig,
                'credit' => $line->creditOrig,
                'rate' => $line->rate,
                'debit_usd' => $line->debitUsd,
                'credit_usd' => $line->creditUsd,
            ], $draft->lines),
        ];
    }
}
