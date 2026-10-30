<?php

namespace App\Services\Notifications;

use App\Enums\NotificationKind;
use App\Enums\RequestStatus;
use App\Enums\VoucherSource;
use App\Enums\VoucherStatus;
use App\Models\LedgerRequest;
use App\Models\RequestAudit;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherAudit;
use App\Models\WebNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Turns the audit trail of vouchers and requests into notifications in the bell (U-01). It reads what was written,
 * so it never changes the approval flow: a voucher or request waiting for a decision goes to the people the approval
 * rules let decide it now (role, limit, turn, never the preparer), and a decision goes back to whoever prepared it.
 * Vouchers migrated from the Windows program are history and notify no one.
 */
class Notifier
{
    public function voucherAudited(VoucherAudit $audit): void
    {
        $voucher = Voucher::withTrashed()->with('lines')->find($audit->voucher_id);

        if ($voucher === null || $voucher->source === VoucherSource::Migration) {
            return;
        }

        match ($audit->action) {
            'create', 'approve_step' => $voucher->status === VoucherStatus::PendingApproval && ! $voucher->trashed()
                ? $this->send($this->approvers($voucher, $audit->changed_by), NotificationKind::VoucherPending, $voucher->id, ['amount' => $voucher->approvalAmount()])
                : null,
            'approve' => $this->send([$voucher->prepared_by], NotificationKind::VoucherApproved, $voucher->id),
            'reject' => $this->send([$voucher->prepared_by], NotificationKind::VoucherRejected, $voucher->id, ['reason' => (string) $audit->reason]),
            default => null,
        };
    }

    public function requestAudited(RequestAudit $audit): void
    {
        $request = LedgerRequest::query()->find($audit->request_id);

        if ($request === null) {
            return;
        }

        $data = ['project' => $request->project_code, 'amount' => (string) $request->amount];

        match ($audit->action) {
            'submit', 'approve' => $request->state() === RequestStatus::Submitted
                ? $this->send($this->deciders($request, $audit->changed_by), NotificationKind::RequestPending, (string) $request->id, $data)
                : null,
            'approved' => $this->send([$request->requested_by], NotificationKind::RequestApproved, (string) $request->id, $data),
            'return' => $this->send([$request->requested_by], NotificationKind::RequestReturned, (string) $request->id, $data + ['reason' => (string) $audit->reason]),
            'reject' => $this->send([$request->requested_by], NotificationKind::RequestRejected, (string) $request->id, $data + ['reason' => (string) $audit->reason]),
            default => null,
        };
    }

    /** @return Collection<int, int> */
    private function approvers(Voucher $voucher, int $actor): Collection
    {
        return $this->people($actor)->filter(fn (User $user): bool => Gate::forUser($user)->allows('approve', $voucher))
            ->map(fn (User $user): int => (int) $user->getAuthIdentifier())->values();
    }

    /** @return Collection<int, int> */
    private function deciders(LedgerRequest $request, int $actor): Collection
    {
        return $this->people($actor)->filter(fn (User $user): bool => Gate::forUser($user)->allows('decide', $request))
            ->map(fn (User $user): int => (int) $user->getAuthIdentifier())->values();
    }

    /**
     * Everyone who might act, except the person whose action this is.
     *
     * @return Collection<int, User>
     */
    private function people(int $except): Collection
    {
        return User::query()->with('webRoles')->get()->reject(fn (User $user): bool => (int) $user->getAuthIdentifier() === $except);
    }

    /**
     * @param  iterable<int|null>  $userIds
     * @param  array<string, string>  $data
     */
    private function send(iterable $userIds, NotificationKind $kind, string $subject, array $data = []): void
    {
        $now = now();
        $rows = collect($userIds)->filter(fn (?int $id): bool => $id !== null && $id > 0)->unique()
            ->map(fn (int $id): array => [
                'user_id' => $id, 'kind' => $kind->value, 'subject' => $subject,
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'created_at' => $now,
            ])->values()->all();

        if ($rows !== []) {
            WebNotification::query()->insert($rows);
        }
    }
}
