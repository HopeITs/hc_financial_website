<?php

namespace App\Livewire;

use App\Livewire\Concerns\HasFormDraft;
use App\Models\GlAccount;
use App\Models\GlProject;
use App\Services\Ledger\BuiltOpening;
use App\Services\Ledger\LedgerRuleException;
use App\Services\Ledger\OpeningBalances as Openings;
use App\Services\Ledger\PostVoucher;
use App\Services\Ledger\TransferRates;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/**
 * Opening balances (F-05, decision R5-2): one opening voucher per city at go-live. The person picks the city and the date
 * and types what each asset and liability account holds for each owner; the program writes the net assets lines and
 * shows the voucher before it is saved as pending approval (see App\Services\Ledger\OpeningBalances).
 */
#[Layout('components.layouts.app')]
class OpeningBalances extends Component
{
    use HasFormDraft;

    private const BLANK = ['account' => '', 'project' => '', 'amount' => '', 'rate' => '', 'contact' => '', 'center' => ''];

    public string $city = '';

    public string $date = '';

    /** @var list<array{account: string, project: string, amount: string, rate: string, contact: string, center: string}> */
    public array $rows = [];

    /** @var list<string> */
    public array $failures = [];

    public function mount(TransferRates $rates): void
    {
        $this->city = $rates->cities()[0] ?? '';
        $this->date = now()->toDateString();
        $this->rows = [self::BLANK, self::BLANK, self::BLANK];
    }

    public function addRow(): void
    {
        $this->rows = [...$this->rows, self::BLANK];
    }

    public function removeRow(int $index): void
    {
        $this->rows = array_values(array_filter($this->rows, fn (array $row, int $n) => $n !== $index, ARRAY_FILTER_USE_BOTH));

        if ($this->rows === []) {
            $this->rows = [self::BLANK];
        }
    }

    public function save(Openings $openings, PostVoucher $posting): void
    {
        Gate::authorize('screen', ['opening', 'create']);
        $this->failures = [];

        $built = $this->built($openings);
        if ($built->draft === null) {
            $this->failures = $built->problems;

            return;
        }

        try {
            $voucher = $posting->post($built->draft);
        } catch (LedgerRuleException $e) {
            $this->failures = array_map(fn ($violation) => $violation->message, $e->violations);

            return;
        } catch (Throwable $e) {
            report($e);
            $this->failures = [__('ledger.builder.save_failed')];

            return;
        }

        $this->forgetDraft();
        session()->flash('notice', __('ledger.builder.saved', ['number' => $voucher->id]));
        $this->redirectRoute('entries.vouchers', ['voucher' => $voucher->id], navigate: true);
    }

    protected function draftFields(): array
    {
        return ['city', 'date', 'rows'];
    }

    protected function draftScreen(): string
    {
        return 'opening';
    }

    protected function draftPermission(): array
    {
        return ['opening', 'create'];
    }

    public function render(Openings $openings, PostVoucher $posting, TransferRates $rates): View
    {
        $typed = collect($this->rows)->contains(fn (array $row) => trim($row['amount']) !== '');
        $built = $typed ? $this->built($openings) : null;
        $violations = $built?->draft !== null ? $posting->check($built->draft) : [];
        $accounts = $this->city !== '' ? $openings->accounts($this->city) : collect();

        return view('livewire.opening-balances', [
            'cities' => $rates->cities(),
            'existing' => $this->city !== '' ? $openings->existing($this->city) : null,
            'accounts' => $accounts,
            'currencies' => $accounts->mapWithKeys(fn (GlAccount $account) => [$account->code => $account->currency])->all(),
            'projects' => GlProject::query()
                ->where(fn ($query) => $query->whereNull('city')->orWhere('city', '')->orWhere('city', $this->city))
                ->orderByDesc('is_general_account')->orderBy('code')->get(['code', 'name', 'restriction', 'is_general_account']),
            'built' => $built,
            'accountNames' => $built?->draft === null ? collect() : GlAccount::query()->whereIn('code', collect($built->draft->lines)->pluck('accountCode')->unique()->all())->get()->keyBy('code'),
            'messages' => [...($built->problems ?? []), ...array_map(fn ($violation) => $violation->message, $violations)],
            'ready' => $built?->draft !== null && $violations === [],
        ]);
    }

    private function built(Openings $openings): BuiltOpening
    {
        $user = auth()->user();
        abort_if($user === null, 403);

        return $openings->build($this->city, $this->date, $this->rows, (int) $user->getAuthIdentifier());
    }
}
