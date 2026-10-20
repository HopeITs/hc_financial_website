<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fortify Guard
    |--------------------------------------------------------------------------
    */

    'guard' => 'web',

    'passwords' => 'users',

    /*
    |--------------------------------------------------------------------------
    | Username / Email
    |--------------------------------------------------------------------------
    |
    | People sign in with the username they use in the Windows program: the
    | `username` column of the finance database's `user` table. Usernames are
    | case sensitive there (BINARY comparison), so they are never lowercased.
    |
    */

    'username' => 'username',

    'email' => 'email',

    'lowercase_usernames' => false,

    'home' => '/home',

    'prefix' => '',

    'domain' => null,

    'middleware' => ['web'],

    'limiters' => [
        'login' => 'login',
    ],

    'views' => true,

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | None. Accounts, passwords and roles are managed in the Windows program
    | (Users screen), so registration, password reset, profile and password
    | updates are not offered here, and two-factor authentication is not used:
    | sign-in is a username and password, as in the program's login window.
    |
    */

    'features' => [],

];
