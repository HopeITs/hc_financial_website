<?php

namespace Database\Seeders;

use App\Models\Voucher;
use App\Models\VoucherAttachment;
use App\Models\VoucherAudit;
use App\Models\VoucherLine;
use Database\Seeders\Concerns\GuardsDemoData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Takes out the demo vouchers (every voucher whose description starts with "[DEMO]") with their lines, history and
 * files, for a test copy only. Real vouchers are never touched. Voucher numbers already given are not given again.
 *
 *   php artisan db:seed --class=RemoveDemoVouchersSeeder
 */
class RemoveDemoVouchersSeeder extends Seeder
{
    use GuardsDemoData;

    public function run(): void
    {
        $this->assertTestDatabase();

        $ids = Voucher::withTrashed()->where('description', 'like', DemoVouchersSeeder::MARK.'%')->pluck('id')->all();

        DB::transaction(function () use ($ids) {
            VoucherAttachment::withTrashed()->whereIn('voucher_id', $ids)->forceDelete();
            VoucherAudit::query()->whereIn('voucher_id', $ids)->delete();
            VoucherLine::query()->whereIn('voucher_id', $ids)->delete();
            Voucher::withTrashed()->whereIn('id', $ids)->forceDelete();
        });

        $this->command?->info(count($ids).' demo vouchers removed.');
    }
}
