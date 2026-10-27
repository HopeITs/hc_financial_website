<?php

namespace App\Livewire;

use App\Services\Ledger\LedgerRuleException;
use App\Services\Ledger\OperationSheet;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/** Multi entry: operations pasted from Excel, checked row by row and saved as pending vouchers all together or not at all. */
#[Layout('components.layouts.app')]
class MultiEntry extends Component
{
    public string $sheet = '';

    /** @var list<string> */
    public array $failures = [];

    public function save(OperationSheet $operations): void
    {
        Gate::authorize('screen', ['multi', 'create']);
        $this->failures = [];

        $rows = $operations->parse($this->sheet);

        if ($rows === []) {
            $this->failures = [__('ledger.multi.empty')];

            return;
        }

        if (count($rows) > OperationSheet::MAX_ROWS) {
            $this->failures = [__('ledger.multi.too_many', ['max' => OperationSheet::MAX_ROWS])];

            return;
        }

        try {
            $vouchers = $operations->postAll($rows, $this->preparer());
        } catch (LedgerRuleException $e) {
            $this->failures = array_map(fn ($violation) => $violation->message, $e->violations);

            return;
        } catch (Throwable $e) {
            report($e);
            $this->failures = [__('ledger.builder.save_failed')];

            return;
        }

        if ($vouchers === []) {
            $this->failures = [__('ledger.multi.not_saved')];

            return;
        }

        session()->flash('notice', __('ledger.multi.saved', ['count' => count($vouchers), 'first' => $vouchers[0]->id]));
        $this->redirectRoute('entries.vouchers', ['status' => 'pending_approval'], navigate: true);
    }

    public function render(OperationSheet $operations): View
    {
        $rows = $this->sheet !== '' ? $operations->review($operations->parse($this->sheet), $this->preparer()) : [];
        $bad = count(array_filter($rows, fn (array $row) => $row['messages'] !== [] || $row['draft'] === null));

        return view('livewire.multi-entry', [
            'rows' => $rows,
            'bad' => $bad,
            'columns' => OperationSheet::COLUMNS,
            'truncated' => $this->sheet !== '' && count($operations->parse($this->sheet)) > OperationSheet::MAX_ROWS,
        ]);
    }

    private function preparer(): int
    {
        $user = auth()->user();
        abort_if($user === null, 403);

        return (int) $user->getAuthIdentifier();
    }
}
