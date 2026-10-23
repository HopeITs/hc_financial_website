<?php

namespace Database\Seeders;

use App\Enums\FundSource;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\Voucher;
use App\Models\VoucherAudit;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\PostVoucher;
use App\Services\Ledger\VoucherDraft;
use Carbon\CarbonImmutable;
use Database\Seeders\Concerns\GuardsDemoData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Demo vouchers to try the vouchers screen, the approvals and the account statement: eight pending (one above the
 * financial manager's limit, one prepared by each of the manager and head office), two approved and one rejected.
 * They are posted through the real rules, prepared by real users of the database (the first active accountant, financial
 * manager and head office user), and every description starts with "[DEMO]".
 *
 * Run it by name, never from DatabaseSeeder, and only on a test copy (the guard refuses anything else):
 *   php artisan db:seed --class=DemoVouchersSeeder
 * Running it again does nothing while demo vouchers exist. RemoveDemoVouchersSeeder takes them out.
 */
class DemoVouchersSeeder extends Seeder
{
    use GuardsDemoData;

    public const MARK = '[DEMO] ';

    private PostVoucher $posting;

    public function run(PostVoucher $posting): void
    {
        $this->assertTestDatabase();

        if (Voucher::withTrashed()->where('description', 'like', self::MARK.'%')->exists()) {
            $this->command?->info('Demo vouchers already exist; nothing added.');

            return;
        }

        $accountant = $this->activeUser(5);
        $manager = $this->activeUser(4);
        $head = $this->activeUser(2);
        $preparer = $accountant ?? $head ?? $manager ?? throw new RuntimeException('There is no active accountant, financial manager or head office user to prepare the demo vouchers.');
        $manager ??= $preparer;
        $head ??= $preparer;

        $this->posting = $posting;

        DB::transaction(function () use ($preparer, $manager, $head) {
            $voucher = $this->post(...);
            $usd = fn (string $account, ?int $debit, ?int $credit, string $project) => new LineDraft(
                $account, 'USD', debit: $debit, credit: $credit, projectCode: $project, fundSource: FundSource::DonorGrant,
            );
            $income = fn (string $account, int $amount, string $project, ?string $contact = null) => new LineDraft(
                $account, 'USD', credit: $amount, projectCode: $project, contact: $contact,
            );

            // Approved, so the account statement has something to show.
            $this->approve($voucher($preparer, VoucherType::Receipt, '2026-08-15', 'ACN grant, August', [$usd('1111', 4000, null, 'F2F'), $income('4101', 4000, 'F2F')]), $manager);
            $this->approve($voucher($preparer, VoucherType::Payment, '2026-08-20', 'Cash to the Latakia safe, August', [$usd('1121', 250, null, 'F2F'), $usd('1111', null, 250, 'F2F')]), $manager);

            // Pending approval.
            $voucher($preparer, VoucherType::Receipt, '2026-09-02', 'ACN grant, first instalment', [$usd('1111', 5000, null, 'F2F'), $income('4101', 5000, 'F2F')]);
            $voucher($preparer, VoucherType::Receipt, '2026-09-05', 'Study Zone tuition, September', [$usd('1111', 800, null, 'SZ'), $income('4301', 800, 'SZ')]);
            $voucher($preparer, VoucherType::Receipt, '2026-09-08', 'منحة كبيرة فوق حدّ المدير المالي', [$usd('1111', 9000, null, 'F2F'), $income('4101', 9000, 'F2F')]);
            $voucher($preparer, VoucherType::Transfer, '2026-09-10', 'تحويل إلى صندوق اللاذقية', [$usd('1121', 1200, null, 'F2F'), $usd('1111', null, 1200, 'F2F')]);
            $voucher($preparer, VoucherType::Payment, '2026-09-12', 'Maintenance, September', [
                new LineDraft('5405', 'USD', debit: 350, projectCode: 'F2F', budgetLineKey: 'F2F|Maintenance', center: 'Aleppo'),
                $usd('1111', null, 350, 'F2F'),
            ]);
            $voucher($preparer, VoucherType::Transfer, '2026-09-15', 'شراء ليرات سورية للمشروع', [
                new LineDraft('1112', 'SYP', debit: 7500000, rate: 15000, debitUsd: 500, projectCode: 'F2F', fundSource: FundSource::DonorGrant),
                $usd('1111', null, 500, 'F2F'),
            ]);
            $voucher($manager, VoucherType::Transfer, '2026-09-18', 'Prepared by the financial manager', [$usd('1121', 80, null, 'SZ'), $usd('1111', null, 80, 'SZ')]);
            $voucher($head, VoucherType::Transfer, '2026-09-19', 'Prepared by head office', [$usd('1121', 60, null, 'SZ'), $usd('1111', null, 60, 'SZ')]);

            // Rejected: soft deleted, keeps its number, the reason is in its history.
            $rejected = $voucher($preparer, VoucherType::Receipt, '2026-09-01', 'Duplicate entry', [$usd('1111', 100, null, 'F2F'), $income('4101', 100, 'F2F')]);
            VoucherAudit::query()->create(['voucher_id' => $rejected->id, 'action' => 'reject', 'changed_by' => $manager, 'reason' => self::MARK.'Entered twice']);
            $rejected->delete();
        });

        $this->command?->info('Eleven demo vouchers added: 8 pending, 2 approved, 1 rejected.');
    }

    /** @param  list<LineDraft>  $lines */
    private function post(int $by, VoucherType $type, string $date, string $description, array $lines): Voucher
    {
        return $this->posting->post(new VoucherDraft(
            type: $type,
            date: CarbonImmutable::parse($date),
            preparedBy: $by,
            lines: $lines,
            description: self::MARK.$description,
        ));
    }

    private function approve(Voucher $voucher, int $by): void
    {
        $voucher->update(['status' => VoucherStatus::Approved, 'approved_by' => $by, 'approved_at' => now()]);
        VoucherAudit::query()->create(['voucher_id' => $voucher->id, 'action' => 'approve', 'changed_by' => $by, 'reason' => self::MARK.'demo data']);
    }

    /** The `user`.`ID` of the first active user with the legacy role, or null. */
    private function activeUser(int $role): ?int
    {
        $id = DB::table('user')->where('Role', $role)->where('State', 1)->orderBy('ID')->value('ID');

        return is_numeric($id) ? (int) $id : null;
    }
}
