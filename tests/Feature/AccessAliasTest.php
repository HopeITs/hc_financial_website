<?php

use App\Http\Middleware\EnsureScreenAccess;
use Illuminate\Routing\Router;

it('registers access as another name for the screen middleware', function () {
    $aliases = app(Router::class)->getMiddleware();

    expect($aliases['access'])->toBe(EnsureScreenAccess::class)
        ->and($aliases['screen'])->toBe(EnsureScreenAccess::class);
});
