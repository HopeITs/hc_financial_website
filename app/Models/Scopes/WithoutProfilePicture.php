<?php

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Leaves the `ProfilePicture` blob out of a query that does not choose its own columns,
 * so listing or signing in users never drags every picture along.
 *
 * @implements Scope<User>
 */
class WithoutProfilePicture implements Scope
{
    /**
     * @param  Builder<covariant User>  $builder
     * @param  User  $model
     */
    public function apply(Builder $builder, Model $model): void
    {
        if ($builder->getQuery()->columns !== null) {
            return;
        }

        $builder->select($model->qualifyColumns(['ID', 'Username', 'Password', 'Role', 'State']));
    }
}
