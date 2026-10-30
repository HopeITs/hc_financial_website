<?php

namespace App\Livewire;

use App\Models\GlAccount;
use App\Models\GlBudgetLine;
use App\Services\Ledger\AdvanceSettlement;
use App\Services\Ledger\LedgerRuleException;
use App\Services\Ledger\VoucherDraft;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Throwable;

/**
 * Advance settlement (F-12), the CPV mode of the Operations screen: pick the person and see what is open on their advance,
 * list the bills on budget lines, and see the vouchers the program writes (the bills, and the cash difference) before saving.
 */
class AdvanceSettlementForm extends Component
{
    public string $date = '';

    public string $person = '';

    public string $cash = '';

    public string $description = '';

    /** @var array<int, array{budget_line: string, amount: string, note: string}> */
    public array $bills = [];

    /** @var list<string> */
    public array $failures = [];

    public function mount(): void
    {
        $this->date = now()->toDateString();
        $this->bills = [['budget_line' => '', 'amount' => '', 'note' => '']];
    }

    public function addBill(): void
    {
        $this->bills[] = ['budget_line' => '', 'amount' => '', 'note' => ''];
    }

    public function removeBill(int $index): void
    {
        unset($this->bills[$index]);
        $this->bills = array_values($this->bills) ?: [['budget_line' => '', 'amount' => '', 'note' => '']];
    }

    public function save(AdvanceSettlement $settlement): void
    {
        Gate::authorize('screen', ['single', 'create']);
        $this->failures = [];

        $result = $this->result($settlement);
        $problems = [...$result['problems'], ...($result['problems'] === [] ? $settlement->violations($result['drafts']) : [])];

        if ($problems !== []) {
            $this->failures = $problems;

            return;
        }

        try {
            $numbers = $settlement->post($result['drafts']);
        } catch (LedgerRuleException $e) {
            $this->failures = array_map(fn ($violation) => (string) $violation->message, $e->violations);

            return;
        } catch (Throwable $e) {
            report($e);
            $this->failures = [__('ledger.builder.save_failed')];

            return;
        }

        session()->flash('notice', __('ledger.settlement.saved', ['numbers' => implode(', ', $numbers)]));
        $this->redirectRoute('entries.vouchers', ['status' => 'pending_approval'], navigate: true);
    }

    public function render(AdvanceSettlement $settlement): View
    {
        $result = $this->person !== '' ? $this->result($settlement) : null;

        return view('livewire.advance-settlement-form', [
            'people' => $settlement->openAdvances(),
            'budgetLines' => GlBudgetLine::query()->orderBy('key')->get(['key', 'project_code']),
            'cashAccounts' => GlAccount::query()->where('is_cash', true)->where('level', 'posting')->where('is_active', true)->where('currency', 'USD')->orderBy('code')->get(),
            'result' => $result,
            'violations' => $result !== null && $result['problems'] === [] ? $settlement->violations($result['drafts']) : [],
        ]);
    }

    /** @return array{drafts: list<VoucherDraft>, problems: array<int, string>, open: string, billed: string, difference: string} */
    private function result(AdvanceSettlement $settlement): array
    {
        $user = auth()->user();
        abort_if($user === null, 403);

        return $settlement->build($this->person, $this->bills, $this->cash, $this->date, $this->description, (int) $user->getAuthIdentifier());
    }
}
