<?php

namespace App\Livewire;

use App\Enums\RoleId;
use App\Enums\ScreenAction;
use App\Livewire\Concerns\HasFormDraft;
use App\Models\Role;
use App\Models\User;
use App\Models\WebUserPermission;
use App\Models\WebUserRole;
use App\Support\FinancePassword;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The directory of the people who can sign in, read from the old program's `user` table: who they are, which roles they hold
 * and what they may do on each screen, with the role list beside it.
 *
 * What is written, and where:
 *  - the password, the primary role (`Role`) and the state (`State`) in the old program's `user` table, the password the way the
 *    old program writes it (FinancePassword::make), so the person signs in to both programs;
 *  - further roles in `web_user_roles` and permissions given to one person in `web_user_permissions`, which only this app reads.
 * Adding and removing a person writes the `user` table too. Every change is noted in the `log` table, never with a password.
 * Each action authorizes itself through the `users` screen: view to look, edit to change, create to add, delete to remove.
 */
#[Layout('components.layouts.app')]
class Users extends Component
{
    use HasFormDraft;
    use WithPagination;

    public const PER_PAGE = 25;

    public const MIN_LENGTH = 8;

    #[Url(as: 'tab', except: 'users')]
    public string $tab = 'users';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $role = '';

    #[Url(except: '')]
    public string $status = '';

    /** The person picked in the list. */
    #[Locked]
    public ?int $editing = null;

    public ?int $primaryRoleId = null;

    /** @var array<int, int> */
    public array $extraRoleIds = [];

    public bool $active = true;

    /** @var array<int, string> `screen.action` */
    public array $permissions = [];

    public string $password = '';

    public string $confirmation = '';

    public ?string $notice = null;

    /** @var list<string> */
    public array $failures = [];

    public bool $showCreate = false;

    public string $newUsername = '';

    public string $newPassword = '';

    public ?int $newRoleId = null;

    public bool $showDelete = false;

    #[Locked]
    public ?int $deletingId = null;

    public string $deletingName = '';

    public ?string $deleteError = null;

    /** The role picked on the Roles tab. */
    #[Locked]
    public ?int $selectedRoleId = null;

    public function mount(): void
    {
        $this->selectedRoleId = RoleId::cases()[0]->value;
    }

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

    public function showTab(string $tab): void
    {
        abort_unless(in_array($tab, ['users', 'roles'], true), 404);

        $this->tab = $tab;
    }

    public function selectRole(int $id): void
    {
        abort_if(RoleId::tryFrom($id) === null, 404);

        $this->selectedRoleId = $id;
    }

    public function selectUser(int $id): void
    {
        $this->pick($id);
        $this->notice = null;
    }

    private function pick(int $id): void
    {
        Gate::authorize('screen', ['users', 'view']);

        $person = User::query()->with(['webRoles', 'webPermissions'])->findOrFail($id);
        $primary = RoleId::tryFrom((int) ($person->getAttribute('Role') ?? 0));

        $this->editing = $person->getKey();
        $this->primaryRoleId = $primary !== null && ! $primary->isWebOnly() ? $primary->value : null;
        $this->extraRoleIds = $person->webRoles->map(fn (WebUserRole $row): int => $row->role_id->value)->values()->all();
        $this->active = $person->isActive();
        $this->permissions = $person->webPermissions->map(fn (WebUserPermission $row): string => $row->screen.'.'.$row->action)->sort()->values()->all();
        $this->resetPasswordForm();
    }

    public function cancel(): void
    {
        $this->editing = null;
        $this->resetPasswordForm();
    }

    /**
     * Save the picked person's roles, state and permissions in one go.
     */
    public function saveAccess(): void
    {
        Gate::authorize('screen', ['users', 'edit']);
        $this->failures = [];

        $person = $this->picked();

        if ($person === null) {
            $this->failures = [__('master.users.unknown')];

            return;
        }

        $legacy = $this->legacyRoleIds();
        $extra = collect($this->extraRoleIds)->map(fn (mixed $id): int => (int) $id)->unique()
            ->reject(fn (int $id): bool => $id === $this->primaryRoleId)->values();
        $granted = collect($this->permissions)->map(fn (mixed $key): string => (string) $key)->unique()->values();

        if ($this->primaryRoleId === null || ! in_array($this->primaryRoleId, $legacy, true) || Role::query()->whereKey($this->primaryRoleId)->doesntExist()) {
            $this->failures[] = __('master.users.bad_role');
        }

        if ($extra->contains(fn (int $id): bool => RoleId::tryFrom($id) === null)) {
            $this->failures[] = __('master.users.bad_role');
        }

        if ($granted->contains(fn (string $key): bool => ! in_array($key, $this->permissionKeys(), true))) {
            $this->failures[] = __('master.users.bad_permission');
        }

        if ($person->getKey() === auth()->id() && ! $this->active) {
            $this->failures[] = __('master.users.not_yourself');
        }

        // Rights over people stay with those who may delete them: Head Office, or a person given that right.
        $handsOutUserRights = $this->primaryRoleId === RoleId::HeadOffice->value
            || $extra->contains(RoleId::HeadOffice->value)
            || $granted->contains(fn (string $key): bool => str_starts_with($key, 'users.'));

        if ($handsOutUserRights && ! $this->actor()?->canOnScreen('users', ScreenAction::Delete)) {
            $this->failures[] = __('master.users.admins_only');
        }

        if ($this->failures !== []) {
            return;
        }

        DB::transaction(function () use ($person, $extra, $granted) {
            $changes = [];

            if ((int) $person->getAttribute('Role') !== $this->primaryRoleId) {
                $changes['Role'] = $this->primaryRoleId;
            }

            if ($person->isActive() !== $this->active) {
                $changes['State'] = $this->active ? 1 : 0;
            }

            if ($changes !== []) {
                User::query()->whereKey($person->getKey())->update($changes);
            }

            WebUserRole::query()->where('user_id', $person->getKey())->delete();
            foreach ($extra as $roleId) {
                WebUserRole::query()->create(['user_id' => $person->getKey(), 'role_id' => $roleId]);
            }

            WebUserPermission::query()->where('user_id', $person->getKey())->delete();
            foreach ($granted as $key) {
                [$screen, $action] = explode('.', $key, 2);
                WebUserPermission::query()->create(['user_id' => $person->getKey(), 'screen' => $screen, 'action' => $action]);
            }

            $this->record('ACCESS_CHANGED', $person, $person->username);
        });

        $this->forgetDraft();
        $this->dispatch('form-clean');
        $this->pick($person->getKey());
        $this->notice = __('master.users.access_saved', ['name' => $person->username]);
    }

    public function save(): void
    {
        Gate::authorize('screen', ['users', 'edit']);
        $this->failures = [];

        $person = $this->picked();

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

            $this->record('PASSWORD_CHANGED', $person, $person->username);
        });

        $this->notice = __('master.users.changed', ['name' => $person->username]);
        $this->resetPasswordForm();
        $this->dispatch('form-clean');
    }

    public function openCreate(): void
    {
        Gate::authorize('screen', ['users', 'create']);

        $this->resetCreateForm();
        $this->showCreate = true;
    }

    public function closeCreate(): void
    {
        $this->showCreate = false;
        $this->resetCreateForm();
    }

    /**
     * Add a person to the old program's `user` table, active, with a first role and password.
     */
    public function createUser(): void
    {
        Gate::authorize('screen', ['users', 'create']);

        $username = trim($this->newUsername);

        $this->validate([
            'newUsername' => ['required', 'string', 'max:300', fn (string $attribute, mixed $value, \Closure $fail) => User::query()->where('Username', trim((string) $value))->exists() ? $fail(__('master.users.taken')) : null],
            'newPassword' => ['required', 'string', 'min:'.self::MIN_LENGTH],
            'newRoleId' => ['required', 'integer', 'in:'.implode(',', $this->legacyRoleIds())],
        ], [
            'newUsername.required' => __('master.users.name_required'),
            'newPassword.required' => __('master.users.too_short', ['min' => self::MIN_LENGTH]),
            'newPassword.min' => __('master.users.too_short', ['min' => self::MIN_LENGTH]),
            'newRoleId.required' => __('master.users.bad_role'),
            'newRoleId.in' => __('master.users.bad_role'),
        ]);

        abort_if($this->newRoleId === RoleId::HeadOffice->value && ! $this->actor()?->canOnScreen('users', ScreenAction::Delete), 403);
        abort_if(Role::query()->whereKey($this->newRoleId)->doesntExist(), 422);

        $id = DB::transaction(function () use ($username) {
            $id = (int) DB::table('user')->insertGetId([
                'Username' => $username,
                'Password' => FinancePassword::make($this->newPassword),
                'Role' => $this->newRoleId,
                'State' => 1,
            ], 'ID');

            DB::table('log')->insert($this->logRow('USER_CREATED', (string) $id, $username));

            return $id;
        });

        $this->forgetDraft();
        $this->dispatch('form-clean');
        $this->closeCreate();
        $this->pick($id);
        $this->notice = __('master.users.created', ['name' => $username]);
    }

    public function confirmDelete(int $id): void
    {
        Gate::authorize('screen', ['users', 'delete']);

        $person = User::query()->findOrFail($id);

        $this->deletingId = $person->getKey();
        $this->deletingName = $person->username;
        $this->deleteError = null;
        $this->showDelete = true;
    }

    public function closeDelete(): void
    {
        $this->showDelete = false;
        $this->deletingId = null;
        $this->deletingName = '';
        $this->deleteError = null;
    }

    public function deleteUser(): void
    {
        Gate::authorize('screen', ['users', 'delete']);

        $person = User::query()->findOrFail($this->deletingId);

        if ($person->getKey() === auth()->id()) {
            $this->deleteError = __('master.users.not_yourself');

            return;
        }

        try {
            DB::transaction(function () use ($person) {
                WebUserRole::query()->where('user_id', $person->getKey())->delete();
                WebUserPermission::query()->where('user_id', $person->getKey())->delete();
                User::query()->whereKey($person->getKey())->delete();
                DB::table('log')->insert($this->logRow('USER_DELETED', (string) $person->getKey(), $person->username));
            });
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }

            $this->deleteError = __('master.users.linked');

            return;
        }

        if ($this->editing === $person->getKey()) {
            $this->editing = null;
        }

        $name = $person->username;
        $this->closeDelete();
        $this->notice = __('master.users.deleted', ['name' => $name]);
    }

    /** Never a password or its confirmation. */
    protected function draftFields(): array
    {
        return ['editing', 'primaryRoleId', 'extraRoleIds', 'active', 'permissions', 'showCreate', 'newUsername', 'newRoleId'];
    }

    protected function draftScreen(): string
    {
        return 'users';
    }

    protected function draftPermission(): array
    {
        return ['users', $this->showCreate ? 'create' : 'edit'];
    }

    /**
     * The person, roles and permissions come back only as ones that still exist; the password fields stay empty.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function restoredDraft(array $payload): void
    {
        $role = fn (mixed $id): ?int => is_int($id) || (is_string($id) && ctype_digit($id)) ? RoleId::tryFrom((int) $id)?->value : null;
        $legacyRole = fn (mixed $id): ?int => in_array($role($id), $this->legacyRoleIds(), true) ? $role($id) : null;
        $editing = $payload['editing'] ?? null;

        $this->editing = is_int($editing) && User::query()->whereKey($editing)->exists() ? $editing : null;
        $this->primaryRoleId = $legacyRole($payload['primaryRoleId'] ?? null);
        $this->newRoleId = $legacyRole($payload['newRoleId'] ?? null);
        $this->extraRoleIds = array_values(array_unique(array_filter(
            array_map($role, is_array($payload['extraRoleIds'] ?? null) ? $payload['extraRoleIds'] : []),
            fn (?int $id): bool => $id !== null,
        )));
        $this->permissions = array_values(array_intersect(
            array_filter(is_array($payload['permissions'] ?? null) ? $payload['permissions'] : [], 'is_string'),
            $this->permissionKeys(),
        ));
        $this->resetPasswordForm();
        $this->newPassword = '';
    }

    public function render(): View
    {
        $user = $this->actor();
        $term = trim($this->search);

        $people = User::query()->with(['roleRecord', 'webRoles'])
            ->when($term !== '', fn ($query) => $query->where('Username', 'like', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%'))
            ->when($this->role !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('Role', (int) $this->role)
                ->orWhereIn('ID', WebUserRole::query()->where('role_id', (int) $this->role)->select('user_id'))))
            ->when($this->status === 'active', fn ($query) => $query->where('State', 1))
            ->when($this->status === 'inactive', fn ($query) => $query->where(fn ($inner) => $inner->where('State', '!=', 1)->orWhereNull('State')))
            ->orderBy('Username')
            ->paginate(self::PER_PAGE);

        $picked = $this->tab === 'users' ? $this->picked() : null;
        $heldRoles = collect([$this->primaryRoleId, ...$this->extraRoleIds])->filter()->map(fn (mixed $id): ?RoleId => RoleId::tryFrom((int) $id))->filter();

        return view('livewire.users', [
            'people' => $people,
            'roles' => Role::query()->orderBy('Name')->get(),
            'canEdit' => $user instanceof User && $user->canOnScreen('users', ScreenAction::Edit),
            'canCreate' => $user instanceof User && $user->canOnScreen('users', ScreenAction::Create),
            'canDelete' => $user instanceof User && $user->canOnScreen('users', ScreenAction::Delete),
            'picked' => $picked,
            'legacyRoles' => array_values(array_filter(RoleId::cases(), fn (RoleId $role): bool => ! $role->isWebOnly())),
            'groups' => $this->screenGroups(),
            'inherited' => $this->inheritedKeys($heldRoles->all()),
            'roleRows' => $this->tab === 'roles' ? $this->roleRows() : [],
            'roleDetail' => $this->tab === 'roles' ? $this->roleDetail() : null,
            'min' => self::MIN_LENGTH,
        ]);
    }

    /**
     * The people-and-permission summary of every role, for the Roles tab.
     *
     * @return list<array{role: RoleId, people: int, screens: int}>
     */
    private function roleRows(): array
    {
        $legacy = User::query()->selectRaw('Role as role_id, count(*) as total')->groupBy('Role')->pluck('total', 'role_id');
        $web = WebUserRole::query()->selectRaw('role_id, count(*) as total')->groupBy('role_id')->pluck('total', 'role_id');

        return array_map(fn (RoleId $role): array => [
            'role' => $role,
            'people' => (int) ($role->isWebOnly() ? 0 : ($legacy[$role->value] ?? 0)) + (int) ($web[$role->value] ?? 0),
            'screens' => count(config("permissions.roles.{$role->value}", [])),
        ], RoleId::cases());
    }

    /**
     * The picked role on the Roles tab: who holds it and what it allows.
     *
     * @return array{role: RoleId, people: array<int, string>, screens: array<string, array<int, string>>}|null
     */
    private function roleDetail(): ?array
    {
        $role = $this->selectedRoleId !== null ? RoleId::tryFrom($this->selectedRoleId) : null;

        if ($role === null) {
            return null;
        }

        $people = User::query()
            ->when(! $role->isWebOnly(), fn ($query) => $query->where('Role', $role->value)->orWhereIn('ID', WebUserRole::query()->where('role_id', $role->value)->select('user_id')),
                fn ($query) => $query->whereIn('ID', WebUserRole::query()->where('role_id', $role->value)->select('user_id')))
            ->orderBy('Username')->pluck('Username')->map(fn (mixed $name): string => (string) $name)->values()->all();

        return ['role' => $role, 'people' => $people, 'screens' => config("permissions.roles.{$role->value}", [])];
    }

    /**
     * Screens grouped as the navigation groups them (the first group holds the screens outside the menu).
     *
     * @return array<string, array<string, list<string>>> group => screen slug => actions that screen offers
     */
    private function screenGroups(): array
    {
        $groups = ['general' => ['home', 'project-money'], ...(require config_path('navigation.php'))['groups']];

        return array_map(fn (array $slugs): array => collect($slugs)->mapWithKeys(fn (string $slug): array => [$slug => $this->actionsOf($slug)])->all(), $groups);
    }

    /**
     * The actions a screen has: view, plus whatever the roles other than Head Office are given on it.
     *
     * @return list<string>
     */
    private function actionsOf(string $slug): array
    {
        $used = ['view'];

        foreach (RoleId::cases() as $role) {
            if ($role !== RoleId::HeadOffice) {
                $used = [...$used, ...config("permissions.roles.{$role->value}.{$slug}", [])];
            }
        }

        return array_values(array_filter(array_map(fn (ScreenAction $action): string => $action->value, ScreenAction::cases()), fn (string $action): bool => in_array($action, $used, true)));
    }

    /**
     * @return list<string>
     */
    private function permissionKeys(): array
    {
        $keys = [];

        foreach ($this->screenGroups() as $screens) {
            foreach ($screens as $slug => $actions) {
                foreach ($actions as $action) {
                    $keys[] = $slug.'.'.$action;
                }
            }
        }

        return $keys;
    }

    /**
     * What the roles already give, as `screen.action`: shown ticked and locked beside the permissions given to one person.
     *
     * @param  array<int, RoleId>  $roles
     * @return array<string, true>
     */
    private function inheritedKeys(array $roles): array
    {
        $keys = [];

        foreach ($roles as $role) {
            foreach (config("permissions.roles.{$role->value}", []) as $slug => $actions) {
                foreach ($actions as $action) {
                    $keys[$slug.'.'.$action] = true;
                }
            }
        }

        return $keys;
    }

    /**
     * @return list<int> The roles the old program's `user`.`Role` can hold.
     */
    private function legacyRoleIds(): array
    {
        return array_values(array_map(fn (RoleId $role): int => $role->value, array_filter(RoleId::cases(), fn (RoleId $role): bool => ! $role->isWebOnly())));
    }

    private function picked(): ?User
    {
        return $this->editing !== null ? User::query()->whereKey($this->editing)->first() : null;
    }

    private function actor(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    private function resetPasswordForm(): void
    {
        $this->password = '';
        $this->confirmation = '';
        $this->failures = [];
    }

    private function resetCreateForm(): void
    {
        $this->resetValidation();
        $this->newUsername = '';
        $this->newPassword = '';
        $this->newRoleId = null;
    }

    private function record(string $title, User $person, string $details): void
    {
        DB::table('log')->insert($this->logRow($title, (string) $person->getKey(), $details));
    }

    /**
     * @return array<string, string>
     */
    private function logRow(string $title, string $id, string $details): array
    {
        return [
            'form_name' => 'Web_Users',
            'title' => $title,
            'affected_table' => 'user',
            'affected_id' => $id,
            'details' => $details,
            'additionaldetails' => '',
            'query' => '',
            'date' => now()->toDateString(),
            'user' => (string) $this->actor()?->username,
        ];
    }
}
