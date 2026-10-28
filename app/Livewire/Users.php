<?php

namespace App\Livewire;

use App\Enums\ScreenAction;
use App\Models\Role;
use App\Models\User;
use App\Support\FinancePassword;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The people who can sign in, read from the old program's `user` table. The one thing that can be done here is to change a
 * password, and it is written the way the old program writes it (FinancePassword::make), so the person signs in to both
 * programs. Roles and states stay in the old program. A change is noted in the `log` table, without the password.
 */
#[Layout('components.layouts.app')]
class Users extends Component
{
    use WithPagination;

    public const PER_PAGE = 25;

    public const MIN_LENGTH = 8;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(except: '')]
    public string $role = '';

    #[Url(except: '')]
    public string $status = '';

    public ?int $editing = null;

    public string $password = '';

    public string $confirmation = '';

    public ?string $notice = null;

    /** @var list<string> */
    public array $failures = [];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRole(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function edit(int $id): void
    {
        Gate::authorize('screen', ['users', 'edit']);

        abort_if(User::query()->whereKey($id)->doesntExist(), 404);

        $this->editing = $id;
        $this->password = '';
        $this->confirmation = '';
        $this->failures = [];
        $this->notice = null;
    }

    public function cancel(): void
    {
        $this->editing = null;
        $this->password = '';
        $this->confirmation = '';
        $this->failures = [];
    }

    public function save(): void
    {
        Gate::authorize('screen', ['users', 'edit']);
        $this->failures = [];

        $person = $this->editing !== null ? User::query()->whereKey($this->editing)->first() : null;

        if ($person === null) {
            $this->failures = [__('master.users.unknown')];

            return;
        }

        if (mb_strlen($this->password) < self::MIN_LENGTH) {
            $this->failures = [__('master.users.too_short', ['min' => self::MIN_LENGTH])];

            return;
        }

        if ($this->password !== $this->confirmation) {
            $this->failures = [__('master.users.mismatch')];

            return;
        }

        DB::transaction(function () use ($person) {
            // Only the password column is written, and only through the old program's hash.
            User::query()->whereKey($person->getKey())->update(['Password' => FinancePassword::make($this->password)]);

            DB::table('log')->insert([
                'form_name' => 'Web_Users',
                'title' => 'PASSWORD_CHANGED',
                'affected_table' => 'user',
                'affected_id' => (string) $person->getKey(),
                'details' => $person->username,
                'additionaldetails' => '',
                'query' => '',
                'date' => now()->toDateString(),
                'user' => auth()->user() instanceof User ? auth()->user()->username : '',
            ]);
        });

        $this->notice = __('master.users.changed', ['name' => $person->username]);
        $this->cancel();
    }

    public function render(): View
    {
        $user = auth()->user();
        $term = trim($this->search);

        $people = User::query()->with('roleRecord')
            ->when($term !== '', fn ($query) => $query->where('Username', 'like', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%'))
            ->when($this->role !== '', fn ($query) => $query->where('Role', (int) $this->role))
            ->when($this->status === 'active', fn ($query) => $query->where('State', 1))
            ->when($this->status === 'inactive', fn ($query) => $query->where(fn ($inner) => $inner->where('State', '!=', 1)->orWhereNull('State')))
            ->orderBy('Username')
            ->paginate(self::PER_PAGE);

        return view('livewire.users', [
            'people' => $people,
            'roles' => Role::query()->orderBy('Name')->get(),
            'canEdit' => $user instanceof User && $user->canOnScreen('users', ScreenAction::Edit),
            'editingName' => $this->editing !== null ? User::query()->whereKey($this->editing)->first()?->username : null,
            'min' => self::MIN_LENGTH,
        ]);
    }
}
