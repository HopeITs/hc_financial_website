<?php

return [
    'label' => 'Something stopped this page',
    'home' => 'Back to the home page',
    'back' => 'Go back',
    'sign_in' => 'Sign in again',

    '403' => [
        'title' => 'You do not have access to this page',
        'body' => 'Your role does not include this screen or this action. If you need it for your work, ask the Financial Manager or Head Office to give you access.',
    ],
    '404' => [
        'title' => 'This page does not exist',
        'body' => 'The address may be mistyped, or the voucher, request or project it points to was removed. Check the address, or start again from the home page.',
    ],
    '419' => [
        'title' => 'Your session has expired',
        'body' => 'For your safety you were signed out after a while without activity. Sign in again to carry on. A draft you saved is still there.',
    ],
    '500' => [
        'title' => 'Something went wrong on our side',
        'body' => 'Nothing you did caused this, and it has been recorded for the IT team. Try again in a moment; if it keeps happening, tell IT what you were doing.',
    ],
    '503' => [
        'title' => 'The program is being updated',
        'body' => 'It will be back in a few minutes. Your saved work is safe. Try again shortly.',
    ],

    'expired' => [
        'title' => 'Your session has expired',
        'body' => 'You were signed out after a while without activity, so this page can no longer save. Sign in again to carry on.',
        'action' => 'Sign in again',
    ],
];
