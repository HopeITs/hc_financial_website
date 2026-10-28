<?php

namespace App\Livewire;

use App\Enums\ProgramStatus;
use App\Livewire\Concerns\HasFormDraft;
use App\Models\GlProgram;
use App\Services\Programs\ProgramList;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Programs of the Windows program (read only) beside those added here, with their active projects and balances.
 * A new program is saved in `gl_programs`; a project is filed under either kind in `gl_program_projects`.
 */
#[Layout('components.layouts.app')]
class Programs extends Component
{
    use HasFormDraft;

    public bool $showForm = false;

    public string $nameEn = '';

    public string $nameAr = '';

    public string $description = '';

    public string $status = 'active';

    public ?string $linkProject = null;

    public ?string $linkProgram = null;

    public ?string $notice = null;

    public bool $duplicateWarning = false;

    public function openForm(): void
    {
        Gate::authorize('screen', ['programs', 'create']);

        $this->resetValidation();
        $this->reset('nameEn', 'nameAr', 'description', 'status', 'notice', 'duplicateWarning');
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->dispatch('form-clean');
    }

    public function save(ProgramList $programs): void
    {
        Gate::authorize('screen', ['programs', 'create']);

        $data = $this->validate([
            'nameEn' => ['required', 'string', 'max:255'],
            'nameAr' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::enum(ProgramStatus::class)],
        ]);

        // A name already used by a program (legacy or web) is refused.
        if ($programs->nameExists($data['nameEn']) || $programs->nameExists($data['nameAr'])) {
            $this->duplicateWarning = true;
            $this->addError('nameEn', __('programs.duplicate_warning'));

            return;
        }

        $this->duplicateWarning = false;

        GlProgram::query()->create([
            'name_en' => trim($data['nameEn']),
            'name_ar' => trim($data['nameAr']),
            'description' => $data['description'] !== null && $data['description'] !== '' ? $data['description'] : null,
            'status' => $data['status'],
            'created_by' => auth()->id(),
        ]);

        $this->notice = __('programs.saved');
        $this->forgetDraft();
        $this->dispatch('form-clean');
        $this->showForm = false;
    }

    public function link(ProgramList $programs): void
    {
        Gate::authorize('screen', ['programs', 'create']);

        $this->validate([
            'linkProject' => ['required', 'integer', Rule::exists('legacy.subproject', 'ID')],
            'linkProgram' => ['required', Rule::in($programs->options()->keys()->all())],
        ]);

        [$source, $id] = explode(':', (string) $this->linkProgram);

        DB::table('gl_program_projects')->upsert(
            [['sub_project_id' => (int) $this->linkProject, 'program_source' => $source, 'program_id' => (int) $id, 'linked_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]],
            ['sub_project_id'],
            ['program_source', 'program_id', 'linked_by', 'updated_at'],
        );

        $this->notice = __('programs.linked');
        $this->reset('linkProject', 'linkProgram');
    }

    /**
     * Back to the program the Windows program files the project under.
     */
    public function unlink(int $subProjectId): void
    {
        Gate::authorize('screen', ['programs', 'create']);

        DB::table('gl_program_projects')->where('sub_project_id', $subProjectId)->delete();

        $this->notice = __('programs.unlinked');
    }

    protected function draftFields(): array
    {
        return ['showForm', 'nameEn', 'nameAr', 'description', 'status'];
    }

    protected function draftScreen(): string
    {
        return 'programs';
    }

    protected function draftPermission(): array
    {
        return ['programs', 'create'];
    }

    public function render(ProgramList $programs): View
    {
        return view('livewire.programs', [
            'rows' => $programs->rows(),
            'options' => $programs->options(),
            'projects' => $programs->projects(),
            'canCreate' => Gate::allows('screen', ['programs', 'create']),
            'statuses' => ProgramStatus::cases(),
        ]);
    }
}
