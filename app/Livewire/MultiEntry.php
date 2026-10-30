<?php

namespace App\Livewire;

use App\Livewire\Concerns\HasFormDraft;
use App\Services\Ledger\LedgerRuleException;
use App\Services\Ledger\OperationSheet;
use App\Services\Ledger\OperationTemplate;
use App\Support\Navigation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/** Multi entry: operations pasted from Excel, checked row by row and saved as pending vouchers all together or not at all. */
#[Layout('components.layouts.app')]
class MultiEntry extends Component
{
    use HasFormDraft;
    use WithFileUploads;

    public string $sheet = '';

    public ?TemporaryUploadedFile $upload = null;

    public string $uploadedName = '';

    /** @var list<string> */
    public array $failures = [];

    /** Ctrl+Enter (U-07): after saving, open a fresh form here instead of the saved voucher. */
    public bool $thenNew = false;

    /** The program-made Excel file: the entry sheet and the lists to copy codes from. */
    public function template(OperationTemplate $template): StreamedResponse
    {
        Gate::authorize('screen', ['multi', 'create']);

        $path = $template->write(app()->getLocale() === 'ar');

        return response()->streamDownload(function () use ($path) {
            readfile($path);
            @unlink($path);
        }, __('ledger.template.filename').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** The uploaded file replaces the text in the box, so the preview and the checks are the same as for pasted rows. */
    public function updatedUpload(OperationTemplate $template): void
    {
        Gate::authorize('screen', ['multi', 'create']);
        $this->failures = [];

        if ($this->upload === null) {
            return;
        }

        $this->validate(['upload' => ['file', 'max:5120', 'mimes:xlsx']], [], ['upload' => __('ledger.template.upload')]);

        try {
            $this->sheet = $template->read((string) $this->upload->getRealPath());
            $this->uploadedName = $this->upload->getClientOriginalName();
        } catch (InvalidArgumentException $e) {
            $this->failures = [$e->getMessage()];
        }

        $this->upload = null;
    }

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

        $this->forgetDraft();
        session()->flash('notice', __('ledger.multi.saved', ['count' => count($vouchers), 'first' => $vouchers[0]->id]));
        if ($this->thenNew) {
            $this->redirectRoute(Navigation::routeName('multi'), navigate: true);

            return;
        }

        $this->redirectRoute('entries.vouchers', ['status' => 'pending_approval'], navigate: true);
    }

    protected function draftFields(): array
    {
        return ['sheet'];
    }

    protected function draftScreen(): string
    {
        return 'multi';
    }

    protected function draftPermission(): array
    {
        return ['multi', 'create'];
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
