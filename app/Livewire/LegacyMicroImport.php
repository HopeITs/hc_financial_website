<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\Excel\ExcelException;
use App\Services\Loans\LegacyMicroImport as Importer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The import of the old micro projects for follow-up (finance request of 2026-11-27), on the loans screen. The file is
 * big (thousands of projects and tens of thousands of instalments), so it stays on the server and only its summary and
 * the first problems are kept on the page; saving reads it again.
 */
class LegacyMicroImport extends Component
{
    use WithFileUploads;

    private const FOLDER = 'legacy-micro';

    public bool $show = false;

    public ?TemporaryUploadedFile $upload = null;

    /** Where the uploaded file waits on the server, under the private disk. */
    #[Locked]
    public string $storedPath = '';

    public string $fileName = '';

    /** @var array{projects: int, new: int, skipped: int, instalments: int, owed: array<string, string>, problems: list<string>, problem_count: int}|null */
    #[Locked]
    public ?array $summary = null;

    /** @var list<string> */
    public array $problems = [];

    public function template(Importer $importer): StreamedResponse
    {
        $this->authorizeImport();
        $path = $importer->template(app()->getLocale() === 'ar');

        return response()->streamDownload(function () use ($path): void {
            readfile($path);
            @unlink($path);
        }, 'old-micro-projects-template.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function open(): void
    {
        $this->authorizeImport();
        $this->forget();
        $this->resetErrorBag();
        $this->show = true;
    }

    public function close(): void
    {
        $this->forget();
        $this->show = false;
    }

    public function updatedUpload(Importer $importer): void
    {
        $this->authorizeImport();
        $this->forget(keepUpload: true);
        $this->validate(['upload' => ['required', 'file', 'mimes:xlsx', 'max:20480']]);

        $this->storedPath = $this->upload->storeAs(self::FOLDER, Str::uuid()->toString().'.xlsx', 'local');
        $this->fileName = $this->upload->getClientOriginalName();
        $this->upload = null;

        try {
            $this->summary = $importer->review(Storage::disk('local')->path($this->storedPath));
        } catch (InvalidArgumentException $failed) {
            $this->problems = [$failed->getMessage()];
            $this->forget();
        }
    }

    public function save(Importer $importer): void
    {
        $this->authorizeImport();

        if ($this->storedPath === '' || ! str_starts_with($this->storedPath, self::FOLDER.'/') || ! Storage::disk('local')->exists($this->storedPath)) {
            $this->problems = [__('legacy_micro.no_file')];

            return;
        }

        try {
            $saved = $importer->save(Storage::disk('local')->path($this->storedPath), $this->user());
        } catch (ExcelException $refused) {
            $this->problems = $refused->problems;

            return;
        } catch (InvalidArgumentException $failed) {
            $this->problems = [$failed->getMessage()];

            return;
        }

        $this->close();
        $this->dispatch('excel-imported', kind: 'legacy_micro', message: trans_choice('legacy_micro.saved', $saved, ['count' => $saved]));
    }

    public function render(): View
    {
        return view('livewire.legacy-micro-import', [
            'canImport' => Gate::allows('screen', ['payments', 'create']),
        ]);
    }

    /** Drops the waiting file and what was read from it. */
    private function forget(bool $keepUpload = false): void
    {
        if ($this->storedPath !== '' && str_starts_with($this->storedPath, self::FOLDER.'/')) {
            Storage::disk('local')->delete($this->storedPath);
        }

        $this->storedPath = '';
        $this->fileName = '';
        $this->summary = null;
        $this->problems = [];

        if (! $keepUpload) {
            $this->upload = null;
        }
    }

    private function authorizeImport(): void
    {
        Gate::authorize('screen', ['payments', 'create']);
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
