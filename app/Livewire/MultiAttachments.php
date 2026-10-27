<?php

namespace App\Livewire;

use App\Models\Voucher;
use App\Services\Ledger\VoucherAttachments;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Multi attachments: many files attached to saved vouchers at once. A file belongs to the voucher whose number its name
 * starts with (CPV-2026-0012_invoice.pdf). All files are attached, or none if one has no voucher.
 */
#[Layout('components.layouts.app')]
class MultiAttachments extends Component
{
    use WithFileUploads;

    public const MAX_FILES = 50;

    /** @var array<int, TemporaryUploadedFile> */
    public array $files = [];

    /** @var list<string> */
    public array $failures = [];

    public function updatedFiles(VoucherAttachments $attachments): void
    {
        $this->validate($attachments->rules('files', self::MAX_FILES), $attachments->messages('files'));
    }

    public function removeFile(int $index): void
    {
        unset($this->files[$index]);
        $this->files = array_values($this->files);
    }

    public function save(VoucherAttachments $attachments): void
    {
        Gate::authorize('screen', ['multiatt', 'create']);
        $this->failures = [];

        $this->validate($attachments->rules('files', self::MAX_FILES), $attachments->messages('files'));

        if ($this->files === []) {
            $this->failures = [__('ledger.multiatt.empty')];

            return;
        }

        try {
            $count = $attachments->attachByName($this->files, $this->user());
        } catch (Throwable $e) {
            report($e);
            $this->failures = [__('ledger.builder.save_failed')];

            return;
        }

        if ($count === 0) {
            $this->failures = [__('ledger.multiatt.not_saved')];

            return;
        }

        $this->files = [];
        session()->flash('notice', __('ledger.multiatt.saved', ['count' => $count]));
        $this->redirectRoute('entries.vouchers', navigate: true);
    }

    public function render(VoucherAttachments $attachments): View
    {
        $rows = [];

        foreach ($this->files as $index => $file) {
            $id = $attachments->voucherOf($file->getClientOriginalName());
            $rows[] = [
                'index' => $index,
                'name' => $file->getClientOriginalName(),
                'voucher' => $id,
                'found' => $id !== null && Voucher::query()->whereKey($id)->exists(),
            ];
        }

        return view('livewire.multi-attachments', [
            'rows' => $rows,
            'bad' => count(array_filter($rows, fn (array $row) => ! $row['found'])),
        ]);
    }

    private function user(): int
    {
        $user = auth()->user();
        abort_if($user === null, 403);

        return (int) $user->getAuthIdentifier();
    }
}
