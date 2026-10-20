<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\User;
use App\Support\FinancePassword;
use Illuminate\Http\Request;
use Laravel\Fortify\Fortify;

/**
 * Logs people in with their account from the Windows program.
 *
 * Same rule as Login_Window2: find the `user` row by username, compare case
 * sensitively (the program uses BINARY), then verify the PBKDF2 hash. A wrong
 * username and a wrong password are indistinguishable to the caller.
 */
class AuthenticateWithFinanceAccount
{
    public function __invoke(Request $request): ?User
    {
        $username = $request->string(Fortify::username())->toString();
        $password = $request->string('password')->toString();

        if ($username === '' || $password === '') {
            return null;
        }

        $user = User::query()->where('username', $username)->first();

        // The database collation may be case insensitive; the program's is not.
        if ($user === null || $user->username !== $username) {
            return null;
        }

        return FinancePassword::verify($password, $user->getAuthPassword()) ? $user : null;
    }
}
