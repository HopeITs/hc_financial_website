<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\Excel\ExcelDataset;
use App\Services\Excel\ExcelException;
use App\Services\Excel\ExcelKinds;
use App\Services\Excel\ExcelSheet;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The Excel buttons of a data screen (finance request of 2026-11-20): export what is there, download a template to
 * fill, and import a filled file. The import shows every row with what it would do and what is wrong in it, and saves
 * all of them or none. The screen hears `excel-imported` and shows the new data.
 *
 * Use: <livewire:excel-tools kind="donors" /> (a kind of ExcelKinds; `context` for one agreement or one day).
 */
class ExcelTools extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $kind = '';

    /** @var array<string, mixed> */
    #[Locked]
    public array $context = [];

    public bool $showImport = false;

    public ?TemporaryUploadedFile $upload = null;

    /** @var list<array{row: int, cells: list<string>}> the rows read from the file */
    public array $rows = [];

    public string $fileName = '';

    /** @var list<string> */
    public array $problems = [];

    public function mount(string $kind, array $context = []): void
    {
        $this->kind = $kind;
        $this->context = $context;
        $this->dataset();
    }

    public function export(ExcelSheet $sheet): StreamedResponse
    {
        $dataset = $this->dataset();
        Gate::authorize('screen', [$dataset->screen(), 'view']);

        return $this->download($sheet->write([
            __('excel.sheet.'.$this->kind) => ['headings' => $dataset->exportHeadings(), 'rows' => $dataset->exportRows($this->context)],
        ], $this->rtl()), $dataset->fileName().'-'.now()->format('Y-m-d').'.xlsx');
    }

    public function template(ExcelSheet $sheet): StreamedResponse
    {
        $dataset = $this->dataset();
        Gate::authorize('screen', [$dataset->screen(), $dataset->importAction()]);

        return $this->download($sheet->write([
            __('excel.sheet.'.$this->kind) => ['headings' => $dataset->headings(), 'rows' => $dataset->exampleRows($this->context)],
            ...$dataset->referenceSheets($this->context),
        ], $this->rtl()), $dataset->fileName().'-template.xlsx');
    }

    public function openImport(): void
    {
        $dataset = $this->dataset();
        Gate::authorize('screen', [$dataset->screen(), $dataset->importAction()]);
        $this->reset('rows', 'problems', 'fileName', 'upload');
        $this->resetErrorBag();
        $this->showImport = true;
    }

    public function closeImport(): void
    {
        $this->reset('rows', 'problems', 'fileName', 'upload', 'showImport');
    }

    public function updatedUpload(ExcelSheet $sheet): void
    {
        $dataset = $this->dataset();
        Gate::authorize('screen', [$dataset->screen(), $dataset->importAction()]);
        $this->reset('rows', 'problems');
        $this->validate(['upload' => ['required', 'file', 'mimes:xlsx', 'max:5120']]);

        try {
            $this->rows = $sheet->read($this->upload->getRealPath(), count($dataset->columns()), ExcelDataset::MAX_ROWS);
            $this->fileName = $this->upload->getClientOriginalName();
        } catch (InvalidArgumentException $failed) {
            $this->problems = [$failed->getMessage()];
        }

        $this->upload = null;
    }

    public function save(): void
    {
        $dataset = $this->dataset();
        Gate::authorize('screen', [$dataset->screen(), $dataset->importAction()]);

        try {
            $saved = $dataset->save($this->rows, $this->user(), $this->context);
        } catch (ExcelException $refused) {
            $this->problems = $refused->problems;

            return;
        }

        $this->closeImport();
        $this->dispatch('excel-imported', kind: $this->kind, message: trans_choice('excel.saved', $saved, ['count' => $saved]));
    }

    public function render(): View
    {
        $dataset = $this->dataset();
        $reviewed = $this->rows !== [] ? $dataset->review($this->rows, $this->user(), $this->context) : [];

        return view('livewire.excel-tools', [
            'columns' => $dataset->columns(),
            'headings' => $dataset->headings(),
            'reviewed' => $reviewed,
            'broken' => count(array_filter($reviewed, fn (array $row): bool => $row['problems'] !== [])),
            'canImport' => Gate::allows('screen', [$dataset->screen(), $dataset->importAction()]),
        ]);
    }

    private function dataset(): ExcelDataset
    {
        return app(ExcelKinds::class)->for($this->kind);
    }

    private function download(string $path, string $name): StreamedResponse
    {
        return response()->streamDownload(function () use ($path): void {
            readfile($path);
            @unlink($path);
        }, $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function rtl(): bool
    {
        return app()->getLocale() === 'ar';
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
