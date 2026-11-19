<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\Ledger\LedgerRuleException;
use App\Services\Ledger\VoucherExport;
use App\Services\Ledger\VoucherImport;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Vouchers from Excel (finance decision of 2026-11-20), in place of the old multi entry: download the template, fill it
 * (or edit an export), upload it, see every voucher with what is wrong in it, and save them all, or none.
 */
#[Layout('components.layouts.app')]
class VoucherImportScreen extends Component
{
    use WithFileUploads;

    public ?TemporaryUploadedFile $upload = null;

    /** @var list<array<string, string>> the rows read from the file */
    public array $rows = [];

    public string $fileName = '';

    /** @var list<string> */
    public array $problems = [];

    public function mount(): void
    {
        Gate::authorize('screen', ['multi', 'view']);
    }

    public function template(VoucherExport $export): StreamedResponse
    {
        Gate::authorize('screen', ['multi', 'create']);
        $path = $export->template(app()->getLocale() === 'ar');

        return response()->streamDownload(function () use ($path): void {
            readfile($path);
            @unlink($path);
        }, 'vouchers-template.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function updatedUpload(VoucherImport $import): void
    {
        Gate::authorize('screen', ['multi', 'create']);
        $this->reset('rows', 'problems');
        $this->validate(['upload' => ['required', 'file', 'mimes:xlsx', 'max:5120']]);

        try {
            $this->rows = $import->read($this->upload->getRealPath());
            $this->fileName = $this->upload->getClientOriginalName();
        } catch (InvalidArgumentException $failed) {
            $this->problems = [$failed->getMessage()];
        }

        $this->upload = null;
    }

    public function clear(): void
    {
        $this->reset('rows', 'problems', 'fileName', 'upload');
    }

    public function save(VoucherImport $import): void
    {
        Gate::authorize('screen', ['multi', 'create']);

        try {
            $saved = $import->save($this->rows, (int) $this->user()->getAuthIdentifier());
        } catch (LedgerRuleException $refused) {
            $this->problems = array_map(fn ($violation): string => (string) $violation->message, $refused->violations);

            return;
        }

        session()->flash('notice', trans_choice('vouchers.excel.saved', count($saved), ['count' => count($saved)]));
        $this->redirectRoute('entries.vouchers', ['status' => 'pending_approval'], navigate: true);
    }

    public function render(VoucherImport $import): View
    {
        $vouchers = $this->rows !== [] ? $import->review($this->rows, (int) $this->user()->getAuthIdentifier()) : [];

        return view('livewire.voucher-import', [
            'vouchers' => $vouchers,
            'broken' => count(array_filter($vouchers, fn (array $voucher): bool => $voucher['problems'] !== [])),
            'canCreate' => Gate::allows('screen', ['multi', 'create']),
        ]);
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
