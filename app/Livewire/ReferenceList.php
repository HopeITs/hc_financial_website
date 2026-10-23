<?php

namespace App\Livewire;

use App\Services\MasterData\ReferenceData;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * One screen for the master data that stays a read-only reference from the Windows program (see ReferenceData), told
 * apart by the route's screen slug: a searchable list, with a date range on the log.
 */
#[Layout('components.layouts.app')]
class ReferenceList extends Component
{
    use WithPagination;

    public string $screen = '';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function mount(ReferenceData $data, string $screen): void
    {
        abort_unless($data->has($screen), 404);
        $this->screen = $screen;
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['search', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function render(ReferenceData $data): View
    {
        $day = fn (string $value): ?string => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;

        return view('livewire.reference-list', [
            'columns' => $data->columns($this->screen),
            'page' => $data->page($this->screen, $this->search, $day($this->from), $day($this->to)),
        ]);
    }
}
