<?php

namespace App\Livewire;

use App\Livewire\Concerns\DefersLoading;
use App\Livewire\Concerns\HasMonthFilter;
use App\Models\GlAccount;
use App\Models\GlProject;
use App\Services\Ledger\AccountStatement;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use SplFileObject;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The statement of one account: approved vouchers only, optional project and date range, an opening balance
 * and a running Dr/Cr balance, with a link to each voucher and a CSV export of what is on screen.
 */
#[Layout('components.layouts.app')]
class Statement extends Component
{
    use DefersLoading;
    use HasMonthFilter;

    #[Url(as: 'account')]
    public string $code = '';

    #[Url]
    public string $project = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function export(AccountStatement $statement): StreamedResponse
    {
        $account = $this->account();
        abort_if($account === null, 404);

        $data = $this->statement($statement);

        return response()->streamDownload(function () use ($data) {
            $out = new SplFileObject('php://output', 'w');
            $out->fwrite("\xEF\xBB\xBF");
            $out->fputcsv([__('ledger.statement.date'), __('ledger.statement.voucher'), __('ledger.statement.description'), __('ledger.statement.project'), __('ledger.statement.debit'), __('ledger.statement.credit'), __('ledger.statement.balance')]);
            $out->fputcsv(['', '', __('ledger.statement.opening'), '', '', '', $data['opening']]);
            foreach ($data['rows'] as $row) {
                $out->fputcsv([$row['date'], $row['voucher'], $row['description'], $row['project'], $row['debit'], $row['credit'], $row['balance']]);
            }
            $out->fputcsv(['', '', __('ledger.statement.closing'), '', $data['debit'], $data['credit'], $data['closing']]);
        }, 'statement-'.$account->code.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render(AccountStatement $statement): View
    {
        $account = $this->account();

        return view('livewire.statement', [
            'account' => $account,
            'accounts' => GlAccount::query()->orderBy('code')->get(),
            'projects' => GlProject::query()->orderBy('code')->get(['code', 'name']),
            'data' => $account !== null ? $this->statement($statement) : null,
        ]);
    }

    private function account(): ?GlAccount
    {
        return $this->code !== '' ? GlAccount::query()->where('code', $this->code)->first() : null;
    }

    /** @return array{opening: string, rows: list<array{date: string, voucher: string, type: string, description: string|null, project: string|null, debit: string, credit: string, balance: string}>, debit: string, credit: string, closing: string} */
    private function statement(AccountStatement $statement): array
    {
        $date = fn (string $value): ?string => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;

        return $statement->for($this->code, $this->project, $date($this->from), $date($this->to));
    }
}
