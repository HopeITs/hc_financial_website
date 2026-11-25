<?php

namespace App\Livewire;

use App\Enums\RoleId;
use App\Enums\ScreenAction;
use App\Livewire\Concerns\HasFormDraft;
use App\Models\GlCity;
use App\Models\GlProject;
use App\Models\Role;
use App\Models\User;
use App\Models\WebUserPermission;
use App\Models\WebUserDenial;
use App\Models\WebUserProfile;
use App\Services\Access\RolePermissions;
use App\Models\WebUserRole;
use App\Services\Projects\ProjectAccess;
use App\Support\FinancePassword;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
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
 *  - further roles in `web_user_roles` and permissions given to one person in `web_user_permissions`, which only this app reads;
 *  - the projects given to the person in `web_user_projects` (ProjectAccess), which limit what a project manager sees.
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

    /** @var array<int, string> the codes of the projects given to the person (finance decision of 2026-11-27) */
    public array $projectCodes = [];

    /** The picked person's sign-in name and details (finance request of 2026-11-29). */
    public string $username = '';

    public string $fullName = '';

    public string $jobTitle = '';

    public string $email = '';

    public string $phone = '';

    public string $city = '';

    public string $profileNotes = '';

    public string $newFullName = '';

    public string $newJobTitle = '';

    public string $newEmail = '';

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

    /** @var array<int, string> the picked role's permissions as edited, `screen.action` (finance request of 2026-11-29) */
    public array $rolePermissions = [];

    /** @var array<int, string> what the picked person's roles allow but is taken from them, `screen.action` */
    public array $denied = [];

    /** The picked person sees and enters only their projects, whatever their role (finance request of 2026-11-30). */
    public bool $limitProjects = false;

    public function mount(): void
    {
        $this->selectRole(RoleId::cases()[0]->value);
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
        $role = RoleId::tryFrom($id);
        abort_if($role === null, 404);

        $this->selectedRoleId = $id;
        $this->rolePermissions = $this->keysOf(app(RolePermissions::class)->of($role));
        $this->failures = [];
    }

    /**
     * Saves what the picked role allows (finance request of 2026-11-29). Only those who may delete users change roles;
     * Head Office always keeps the users screen, so nobody is locked out. Screens kept out of the list stay as they were.
     */
    public function saveRole(): void
    {
        Gate::authorize('screen', ['users', 'edit']);
        abort_unless($this->actor()?->canOnScreen('users', ScreenAction::Delete), 403);
        $role = $this->selectedRoleId !== null ? RoleId::tryFrom($this->selectedRoleId) : null;
        abort_if($role === null || $role === RoleId::SuperUser, 404);
        $this->failures = [];

        $roles = app(RolePermissions::class);
        abort_unless($roles->available(), 409);
        $shown = $this->permissionKeys();
        $chosen = collect($this->rolePermissions)->map(fn (mixed $key): string => (string) $key)->unique()->values();

        if ($chosen->diff($shown)->isNotEmpty()) {
            $this->failures = [__('master.users.bad_permission')];

            return;
        }

        if ($role === RoleId::HeadOffice) {
            $chosen = $chosen->merge(['users.view', 'users.edit', 'users.delete'])->unique()->values();
        }

        $hidden = array_values(array_diff($this->keysOf($roles->of($role)), $shown));
        $roles->replace($role, [...$chosen->all(), ...$hidden], (int) $this->actor()?->getAuthIdentifier());
        DB::table('log')->insert($this->logRow('ROLE_CHANGED', (string) $role->value, $role->label()));

        $this->selectRole($role->value);
        $this->notice = __('master.users.role_saved', ['role' => $role->label()]);
    }

    /** Gives the picked role back its permissions as the program set them. */
    public function resetRole(): void
    {
        Gate::authorize('screen', ['users', 'edit']);
        abort_unless($this->actor()?->canOnScreen('users', ScreenAction::Delete), 403);
        $role = $this->selectedRoleId !== null ? RoleId::tryFrom($this->selectedRoleId) : null;
        abort_if($role === null, 404);

        app(RolePermissions::class)->reset($role);
        DB::table('log')->insert($this->logRow('ROLE_RESET', (string) $role->value, $role->label()));

        $this->selectRole($role->value);
        $this->notice = __('master.users.role_reset', ['role' => $role->label()]);
    }

    /** Takes from the picked person, or gives back, an action one of their roles allows. Saved with their access. */
    public function toggleDenied(string $key): void
    {
        Gate::authorize('screen', ['users', 'edit']);
        abort_unless(in_array($key, $this->permissionKeys(), true), 404);

        $this->denied = in_array($key, $this->denied, true)
            ? array_values(array_diff($this->denied, [$key]))
            : [...$this->denied, $key];
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
        // Grants on screens that left the list are dropped, so saving the person never fails on them.
        // A plain collection: the model collection's intersect compares models by key, and these are strings.
        $this->permissions = $person->webPermissions->toBase()->map(fn (WebUserPermission $row): string => $row->screen.'.'.$row->action)
            ->intersect($this->permissionKeys())->sort()->values()->all();
        $this->projectCodes = app(ProjectAccess::class)->given((int) $person->getKey());
        $this->limitProjects = app(ProjectAccess::class)->isSwitchedOn((int) $person->getKey());
        $this->denied = User::denialsAvailable()
            ? $person->webDenials()->get()->toBase()->map(fn (WebUserDenial $row): string => $row->screen.'.'.$row->action)->sort()->values()->all()
            : [];
        $profile = $this->profilesAvailable() ? WebUserProfile::query()->find($person->getKey()) : null;
        $this->username = $person->username;
        $this->fullName = (string) $profile?->full_name;
        $this->jobTitle = (string) $profile?->job_title;
        $this->email = (string) $profile?->email;
        $this->phone = (string) $profile?->phone;
        $this->city = (string) $profile?->city;
        $this->profileNotes = (string) $profile?->notes;
        $this->resetValidation();
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

        $projects = collect($this->projectCodes)->map(fn (mixed $code): string => (string) $code)->unique()->values();
        if ($projects->diff(array_keys($this->projectChoices()))->isNotEmpty()) {
            $this->failures[] = __('master.users.bad_project');
        }

        if ($person->getKey() === auth()->id() && (! $this->active || collect($this->denied)->contains(fn (mixed $key): bool => str_starts_with((string) $key, 'users.')))) {
            $this->failures[] = __('master.users.not_yourself');
        }

        // Rights over people stay with those who may delete them: Head Office, or a person given that right.
        $handsOutUserRights = $this->primaryRoleId === RoleId::HeadOffice->value
            || $extra->contains(RoleId::HeadOffice->value)
            || $extra->contains(RoleId::SuperUser->value)
            || $granted->contains(fn (string $key): bool => str_starts_with($key, 'users.'));

        if ($handsOutUserRights && ! $this->actor()?->canOnScreen('users', ScreenAction::Delete)) {
            $this->failures[] = __('master.users.admins_only');
        }

        if ($this->failures !== []) {
            return;
        }

        DB::transaction(function () use ($person, $extra, $granted, $projects) {
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

            app(ProjectAccess::class)->give((int) $person->getKey(), $projects->all());
            app(ProjectAccess::class)->switchTo((int) $person->getKey(), $this->limitProjects);

            if (User::denialsAvailable()) {
                WebUserDenial::query()->where('user_id', $person->getKey())->delete();
                foreach (collect($this->denied)->map(fn (mixed $key): string => (string) $key)->unique()->intersect($this->permissionKeys()) as $key) {
                    [$screen, $action] = explode('.', $key, 2);
                    WebUserDenial::query()->create(['user_id' => $person->getKey(), 'screen' => $screen, 'action' => $action]);
                }
            }

            $this->record('ACCESS_CHANGED', $person, $person->username);
        });

        $this->forgetDraft();
        $this->dispatch('form-clean');
        $this->pick($person->getKey());
        $this->notice = __('master.users.access_saved', ['name' => $person->username]);
    }

    /**
     * Saves the picked person's sign-in name and details. A new sign-in name must not be taken; the person signs in
     * with it from now on, in both programs.
     */
    public function saveProfile(): void
    {
        Gate::authorize('screen', ['users', 'edit']);
        $this->failures = [];
        $person = $this->picked();

        if ($person === null) {
            $this->failures = [__('master.users.unknown')];

            return;
        }

        $this->username = trim($this->username);
        $this->validate([
            'username' => ['required', 'string', 'max:300', fn (string $attribute, mixed $value, \Closure $fail) => User::query()->where('Username', (string) $value)->whereKeyNot($person->getKey())->exists() ? $fail(__('master.users.taken')) : null],
            'fullName' => ['nullable', 'string', 'max:255'],
            'jobTitle' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'city' => ['nullable', 'string', 'max:255'],
            'profileNotes' => ['nullable', 'string', 'max:2000'],
        ], ['username.required' => __('master.users.name_required')]);

        DB::transaction(function () use ($person): void {
            if ($person->username !== $this->username) {
                User::query()->whereKey($person->getKey())->update(['Username' => $this->username]);
                $this->record('USER_RENAMED', $person, $person->username.' → '.$this->username);
            }

            if ($this->profilesAvailable()) {
                WebUserProfile::query()->updateOrCreate(['user_id' => $person->getKey()], [
                    'full_name' => $this->blankToNull($this->fullName), 'job_title' => $this->blankToNull($this->jobTitle),
                    'email' => $this->blankToNull($this->email), 'phone' => $this->blankToNull($this->phone),
                    'city' => $this->blankToNull($this->city), 'notes' => $this->blankToNull($this->profileNotes),
                ]);
                $this->record('PROFILE_CHANGED', $person, $this->username);
            }
        });

        $this->dispatch('form-clean');
        $this->pick((int) $person->getKey());
        $this->notice = __('master.users.profile_saved', ['name' => $this->username]);
    }

    /**
     * Gives or takes every action of a menu group's screens at once, leaving what the roles already give.
     */
    public function grantGroup(string $group, bool $on): void
    {
        Gate::authorize('screen', ['users', 'edit']);
        $screens = $this->screenGroups()[$group] ?? null;
        abort_if($screens === null, 404);

        $keys = [];
        foreach ($screens as $slug => $actions) {
            foreach ($actions as $action) {
                $keys[] = $slug.'.'.$action;
            }
        }

        $this->permissions = $on
            ? array_values(array_unique([...$this->permissions, ...$keys]))
            : array_values(array_diff($this->permissions, $keys));
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
            'newFullName' => ['nullable', 'string', 'max:255'],
            'newJobTitle' => ['nullable', 'string', 'max:255'],
            'newEmail' => ['nullable', 'email', 'max:255'],
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

            if ($this->profilesAvailable() && (trim($this->newFullName) !== '' || trim($this->newJobTitle) !== '' || trim($this->newEmail) !== '')) {
                WebUserProfile::query()->create([
                    'user_id' => $id, 'full_name' => $this->blankToNull($this->newFullName),
                    'job_title' => $this->blankToNull($this->newJobTitle), 'email' => $this->blankToNull($this->newEmail),
                ]);
            }

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
                DB::table('web_user_projects')->where('user_id', $person->getKey())->delete();
                if (User::denialsAvailable()) {
                    WebUserDenial::query()->where('user_id', $person->getKey())->delete();
                }
                if ($this->profilesAvailable()) {
                    WebUserProfile::query()->whereKey($person->getKey())->delete();
                }
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
        return ['editing', 'primaryRoleId', 'extraRoleIds', 'active', 'permissions', 'projectCodes', 'username', 'fullName', 'jobTitle', 'email', 'phone', 'city', 'profileNotes', 'showCreate', 'newUsername', 'newRoleId', 'newFullName', 'newJobTitle', 'newEmail'];
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
        foreach (['username', 'fullName', 'jobTitle', 'email', 'phone', 'city', 'profileNotes', 'newFullName', 'newJobTitle', 'newEmail'] as $field) {
            $this->{$field} = is_string($payload[$field] ?? null) ? mb_substr($payload[$field], 0, 2000) : '';
        }
        if ($this->username === '' && $this->editing !== null) {
            $this->username = (string) User::query()->whereKey($this->editing)->value('Username');
        }
        $this->projectCodes = array_values(array_intersect(
            array_filter(is_array($payload['projectCodes'] ?? null) ? $payload['projectCodes'] : [], 'is_string'),
            array_keys($this->projectChoices()),
        ));
        $this->resetPasswordForm();
        $this->newPassword = '';
    }

    public function render(): View
    {
        $user = $this->actor();
        $term = trim($this->search);

        $profiles = $this->profilesAvailable();
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

        $people = User::query()->with(['roleRecord', 'webRoles', ...($profiles ? ['profile'] : [])])
            ->when($term !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('Username', 'like', $like)
                ->when($profiles, fn ($named) => $named->orWhereIn('ID', WebUserProfile::query()->where('full_name', 'like', $like)->orWhere('job_title', 'like', $like)->orWhere('email', 'like', $like)->select('user_id')))))
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
            'projectChoices' => $picked !== null ? $this->projectChoices() : [],
            'profiles' => $profiles,
            'cities' => $picked !== null ? GlCity::query()->orderBy('name')->pluck('name')->all() : [],
            'pickedRoles' => $heldRoles->values()->all(),
            'canEditRoles' => $user instanceof User && $user->canOnScreen('users', ScreenAction::Edit) && $user->canOnScreen('users', ScreenAction::Delete) && app(RolePermissions::class)->available(),
            'roleChanged' => $this->selectedRoleId !== null && RoleId::tryFrom($this->selectedRoleId) !== null && app(RolePermissions::class)->isChanged(RoleId::from($this->selectedRoleId)),
            'counts' => $this->tab === 'users' ? ['all' => User::query()->count(), 'active' => User::query()->where('State', 1)->count()] : ['all' => 0, 'active' => 0],
            'seesAllProjects' => ! app(ProjectAccess::class)->limits(array_values($heldRoles->all())),
            'neverLimited' => app(ProjectAccess::class)->neverLimited(array_values($heldRoles->all())),
            'switchAvailable' => ProjectAccess::switchAvailable(),
        ]);
    }

    /**
     * The projects that can be given to a person: code => name.
     *
     * @return array<string, string>
     */
    private function projectChoices(): array
    {
        return GlProject::query()->where('is_general_account', false)->orderBy('code')->pluck('name', 'code')
            ->map(fn (mixed $name): string => (string) $name)->all();
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
            'screens' => count(app(RolePermissions::class)->of($role)),
            'changed' => app(RolePermissions::class)->isChanged($role),
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

        return ['role' => $role, 'people' => $people, 'screens' => array_diff_key(app(RolePermissions::class)->of($role), array_flip(config('navigation.retired', [])))];
    }

    /**
     * Screens grouped as the navigation groups them (the first group holds the screens outside the menu).
     *
     * @return array<string, array<string, list<string>>> group => screen slug => actions that screen offers
     */
    private function screenGroups(): array
    {
        $navigation = require config_path('navigation.php');
        $groups = ['general' => ['home', 'project-money'], ...$navigation['groups']];
        $retired = $navigation['retired'] ?? [];

        return array_map(fn (array $slugs): array => collect($slugs)->reject(fn (string $slug): bool => in_array($slug, $retired, true))->mapWithKeys(fn (string $slug): array => [$slug => $this->actionsOf($slug)])->all(), $groups);
    }

    /**
     * The actions a screen has: view, plus whatever the roles other than Head Office are given on it.
     *
     * @return list<string>
     */
    private function actionsOf(string $slug): array
    {
        $used = ['view'];

        $roles = app(RolePermissions::class);
        foreach (RoleId::cases() as $role) {
            if ($role !== RoleId::HeadOffice) {
                $used = [...$used, ...($roles->defaults($role)[$slug] ?? []), ...($roles->of($role)[$slug] ?? [])];
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
            foreach ($this->keysOf(app(RolePermissions::class)->of($role)) as $key) {
                $keys[$key] = true;
            }
        }

        return $keys;
    }

    /**
     * @param  array<string, list<string>>  $screens  screen slug => actions
     * @return list<string> as `screen.action`
     */
    private function keysOf(array $screens): array
    {
        $keys = [];
        foreach ($screens as $slug => $actions) {
            foreach ((array) $actions as $action) {
                $keys[] = $slug.'.'.$action;
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
        $this->newFullName = '';
        $this->newJobTitle = '';
        $this->newEmail = '';
    }

    /** Whether the details table is there yet (IT runs its migration after the deploy). */
    private function profilesAvailable(): bool
    {
        return Schema::hasTable('web_user_profiles');
    }

    private function blankToNull(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
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
