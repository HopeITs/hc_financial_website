<?php

namespace App\Services\Chart;

use App\Models\GlAccount;

/**
 * The chart of accounts as a flat, ordered list with each account's depth, for the sidebar tree.
 */
class ChartTree
{
    /**
     * @return list<array{code: string, name: string, level: string, parent: string|null, depth: int, hasChildren: bool, class: string, search: string}>
     */
    public function flat(): array
    {
        $accounts = GlAccount::query()->orderBy('code')->get();
        $byParent = $accounts->groupBy(fn (GlAccount $account): string => (string) $account->parent_code);
        $rows = [];

        $walk = function (string $parent, int $depth) use (&$walk, $byParent, &$rows): void {
            foreach ($byParent->get($parent, []) as $account) {
                $rows[] = [
                    'code' => $account->code,
                    'name' => $account->localizedName(),
                    'level' => $account->level,
                    'parent' => $account->parent_code,
                    'depth' => $depth,
                    'hasChildren' => $byParent->has($account->code),
                    'class' => $account->class,
                    'search' => mb_strtolower($account->code.' '.$account->name_en.' '.$account->name_ar),
                ];
                $walk($account->code, $depth + 1);
            }
        };

        $walk('', 0);

        return $rows;
    }
}
