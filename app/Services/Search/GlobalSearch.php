<?php

namespace App\Services\Search;

use App\Models\GlAccount;
use App\Models\GlProject;
use App\Models\LedgerRequest;
use App\Models\MicroLoan;
use App\Models\Purchase;
use App\Models\StaffMember;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Projects\ProjectAccess;
use App\Support\Navigation;
use Illuminate\Support\Facades\Schema;

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
            'purchases' => $this->purchases($user, $term),
            'requests' => $this->requests($user, $term),
            'staff' => $this->staff($user, $term),
            'micro' => $this->microFiles($user, $term),
            'accounts' => $this->accounts($user, $term),
            'projects' => $this->projects($user, $term),
            'screens' => $this->screens($user, $term),
        ]);
    }

    /**
     * Purchase files by number (PR-0012, PO-0012… or 12), what is bought or the supplier (finance request of 2026-11-25).
     *
     * @return list<array{label: string, detail: string|null, url: string}>
     */
    private function purchases(User $user, string $term): array
    {
        if (! $user->canOnScreen('purchases') || ! Schema::hasTable('gl_purchases')) {
            return [];
        }

        $like = $this->like($term);
        $number = preg_replace('/\D/', '', $term);

        return array_values(Purchase::query()
            ->with('awardedQuote')
            ->when(($codes = $this->projectCodes($user)) !== null, fn ($query) => $query->whereIn('project_code', $codes))
            ->where(fn ($query) => $query
                ->where('title', 'like', '%'.$like.'%')
                ->orWhereHas('quotes', fn ($quotes) => $quotes->where('supplier', 'like', '%'.$like.'%'))
                ->when($number !== '', fn ($inner) => $inner->orWhere('id', (int) $number)))
            ->orderByDesc('id')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Purchase $purchase): array => [
                'label' => $purchase->number('PR').' · '.$purchase->title,
                'detail' => $purchase->project_code.' · '.__('purchases.stages.'.$purchase->stage).($purchase->awardedQuote !== null ? ' · '.$purchase->awardedQuote->supplier : ''),
                'url' => route(Navigation::routeName('purchases'), ['file' => $purchase->id]),
            ])->all());
    }

    /** @return list<array{label: string, detail: string|null, url: string}> project requests by number or description */
    private function requests(User $user, string $term): array
    {
        if (! $user->canOnScreen('requests') || ! Schema::hasTable('gl_requests')) {
            return [];
        }

        $like = $this->like($term);
        $number = preg_replace('/\D/', '', $term);

        return array_values(LedgerRequest::query()
            ->when(($codes = $this->projectCodes($user)) !== null, fn ($query) => $query->whereIn('project_code', $codes))
            ->where(fn ($query) => $query->where('description', 'like', '%'.$like.'%')->when($number !== '', fn ($inner) => $inner->orWhere('id', (int) $number)))
            ->orderByDesc('id')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (LedgerRequest $request): array => [
                'label' => '#'.$request->id.' · '.($request->description ?? $request->project_code),
                'detail' => $request->project_code.' · '.__('requests.status.'.$request->status),
                'url' => route(Navigation::routeName('requests'), ['request' => $request->id]),
            ])->all());
    }

    /** @return list<array{label: string, detail: string|null, url: string}> employees by name or ID, to their payroll */
    private function staff(User $user, string $term): array
    {
        if (! $user->canOnScreen('payroll') || ! Schema::hasTable('gl_staff')) {
            return [];
        }

        $like = $this->like($term);

        return array_values(StaffMember::query()
            ->where(fn ($query) => $query->where('name', 'like', '%'.$like.'%')->when(ctype_digit($term), fn ($inner) => $inner->orWhere('employee_id', (int) $term)))
            ->orderBy('name')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (StaffMember $member): array => [
                'label' => $member->name.' · #'.$member->employee_id,
                'detail' => trim($member->project_code.' · '.$member->position, ' ·'),
                'url' => route(Navigation::routeName('payroll'), ['tab' => 'staff']),
            ])->all());
    }

    /** @return list<array{label: string, detail: string|null, url: string}> micro-project files by beneficiary or project number */
    private function microFiles(User $user, string $term): array
    {
        if (! $user->canOnScreen('microfiles') || ! Schema::hasColumn('gl_micro_loans', 'stage')) {
            return [];
        }

        $like = $this->like($term);

        return array_values(MicroLoan::query()->visibleTo($user)
            ->where(fn ($query) => $query->where('beneficiary', 'like', '%'.$like.'%')->orWhere('project_number', 'like', $like.'%'))
            ->orderByDesc('id')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (MicroLoan $file): array => [
                'label' => $file->beneficiary,
                'detail' => trim(($file->project_number ?? '').' · '.__('micro.stages.'.$file->stage), ' ·'),
                'url' => route(Navigation::routeName('microfiles'), ['file' => $file->id]),
            ])->all());
    }

    /** @return list<string>|null the project codes the person is limited to, or null when not limited */
    private function projectCodes(User $user): ?array
    {
        return $this->access->limitedTo($user);
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
        if (! $user->canOnScreen('contracts')) {
            return [];
        }

        $like = $this->like($term);
        $limit = $this->access->limitedTo($user);

        return array_values(GlProject::query()
            ->when($limit !== null, fn ($query) => $query->whereIn('code', $limit ?? []))
            ->where(fn ($query) => $query->where('code', 'like', $like.'%')->orWhere('name', 'like', '%'.$like.'%'))
            ->orderBy('code')
            ->limit(self::PER_GROUP)
            ->get(['code', 'name', 'donor'])
            ->map(fn (GlProject $project): array => [
                'label' => $project->code.' · '.$project->name,
                'detail' => $project->donor,
                'url' => route(Navigation::routeName('contracts')),
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
