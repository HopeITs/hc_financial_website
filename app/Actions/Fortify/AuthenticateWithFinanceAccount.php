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
 * sensitively (the program uses BINARY), then verify the PBKDF2 hash. Only
 * active accounts (`State` = 1) get in. A wrong username, a wrong password and
 * an inactive account are indistinguishable to the caller, in answer and in
 * time: the hash is always computed, against a dummy when there is no account.
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
        $found = $user !== null && $user->username === $username;

        $verified = FinancePassword::verify(
            $password,
            $found ? $user->getAuthPassword() : self::dummyHash(),
        );

        return $found && $verified && $user->isActive() ? $user : null;
    }

    /**
     * A well formed hash nobody knows the password of, so a missing account
     * costs the same time as a real one.
     */
    private static function dummyHash(): string
    {
        static $hash = null;

        return $hash ??= FinancePassword::make(bin2hex(random_bytes(16)));
    }
}
