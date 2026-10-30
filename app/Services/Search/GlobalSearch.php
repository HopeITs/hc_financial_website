<?php

namespace App\Services\Search;

use App\Models\GlAccount;
use App\Models\GlProject;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Projects\ProjectAccess;
use App\Support\Navigation;

/**
 * The Ctrl+K search (U-06): vouchers by number or description, accounts by code or name, projects and screens. Each
 * kind is searched only when the person may open its screen, and projects follow their data scope, so a result never
 * shows what the person may not see. A voucher whose number is typed in full comes first.
 */
class GlobalSearch
{
    public const MIN_LENGTH = 2;

    public const PER_GROUP = 6;

    public function __construct(private readonly ProjectAccess $access) {}

    /** @return array<string, list<array{label: string, detail: string|null, url: string}>> group => results, empty groups left out */
    public function search(User $user, string $term): array
    {
        $term = trim($term);

        if (mb_strlen($term) < self::MIN_LENGTH) {
            return [];
        }

        return array_filter([
            'vouchers' => $this->vouchers($user, $term),
            'accounts' => $this->accounts($user, $term),
            'projects' => $this->projects($user, $term),
            'screens' => $this->screens($user, $term),
        ]);
    }

    /** @return list<array{label: string, detail: string|null, url: string}> */
    private function vouchers(User $user, string $term): array
    {
        if (! $user->canOnScreen('vouchers')) {
            return [];
        }

        $like = $this->like($term);

        return array_values(Voucher::withTrashed()
            ->where(fn ($query) => $query->where('id', 'like', $like.'%')->orWhere('description', 'like', '%'.$like.'%'))
            ->orderByRaw('case when id = ? then 0 when id like ? then 1 else 2 end', [$term, $like.'%'])
            ->orderByDesc('date')
            ->limit(self::PER_GROUP)
            ->get(['id', 'date', 'description', 'status', 'deleted_at'])
            ->map(fn (Voucher $voucher): array => [
                'label' => $voucher->id,
                'detail' => trim($voucher->date->format('Y-m-d').' · '.($voucher->trashed() ? __('search.rejected') : $voucher->status->label()).($voucher->description ? ' · '.$voucher->description : '')),
                'url' => route(Navigation::routeName('vouchers'), ['voucher' => $voucher->id] + ($voucher->trashed() ? ['rejected' => 1] : [])),
            ])->all());
    }

    /** @return list<array{label: string, detail: string|null, url: string}> */
    private function accounts(User $user, string $term): array
    {
        if (! $user->canOnScreen('chart')) {
            return [];
        }

        $like = $this->like($term);
        $arabic = app()->getLocale() === 'ar';
        $statement = $user->canOnScreen('statement');

        return array_values(GlAccount::query()
            ->where(fn ($query) => $query->where('code', 'like', $like.'%')->orWhere('name_en', 'like', '%'.$like.'%')->orWhere('name_ar', 'like', '%'.$like.'%'))
            ->orderBy('code')
            ->limit(self::PER_GROUP)
            ->get(['code', 'name_en', 'name_ar'])
            ->map(fn (GlAccount $account): array => [
                'label' => $account->code.' · '.($arabic ? $account->name_ar : $account->name_en),
                'detail' => $arabic ? $account->name_en : $account->name_ar,
                'url' => $statement ? route(Navigation::routeName('statement'), ['account' => $account->code]) : route(Navigation::routeName('chart')),
            ])->all());
    }

    /** @return list<array{label: string, detail: string|null, url: string}> */
    private function projects(User $user, string $term): array
    {
        if (! $user->canOnScreen('projects')) {
            return [];
        }

        $like = $this->like($term);
        $limit = $this->access->limitedTo($user);

        return array_values(GlProject::query()
            ->when($limit !== null, fn ($query) => $query->whereIn('legacy_account_id', $limit ?? []))
            ->where(fn ($query) => $query->where('code', 'like', $like.'%')->orWhere('name', 'like', '%'.$like.'%'))
            ->orderBy('code')
            ->limit(self::PER_GROUP)
            ->get(['code', 'name', 'donor', 'legacy_account_id'])
            ->map(fn (GlProject $project): array => [
                'label' => $project->code.' · '.$project->name,
                'detail' => $project->donor,
                'url' => $project->legacy_account_id !== null
                    ? route('projects.show', ['account' => $project->legacy_account_id])
                    : route(Navigation::routeName('projects')),
            ])->all());
    }

    /** @return list<array{label: string, detail: string|null, url: string}> */
    private function screens(User $user, string $term): array
    {
        $needle = mb_strtolower($term);
        $name = fn (string $screen, ?string $locale = null): string => (string) __('navigation.screens.'.$screen, [], $locale);

        return array_values(collect(Navigation::visibleGroups($user))
            ->flatMap(fn (array $screens, string $group) => array_map(fn (string $screen): array => [$screen, $group], $screens))
            ->filter(fn (array $pair): bool => str_contains(mb_strtolower($name($pair[0])), $needle) || str_contains(mb_strtolower($name($pair[0], 'en')), $needle))
            ->take(self::PER_GROUP)
            ->map(fn (array $pair): array => [
                'label' => $name($pair[0]),
                'detail' => (string) __('navigation.groups.'.$pair[1]),
                'url' => route(Navigation::routeName($pair[0])),
            ])->all());
    }

    /** The term with LIKE's wildcards taken literally. */
    private function like(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    }
}
