# One-time change by IT: load module routes

Departments add new pages as new files in `routes/modules/` so they never edit `routes/web.php`. Make Laravel load them automatically (this edit is IT's, done once per site).

## Laravel 11 / 12 (`bootstrap/app.php`)

```php
use Illuminate\Support\Facades\Route;

->withRouting(
    web: __DIR__.'/../routes/web.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
    then: function () {
        foreach (glob(base_path('routes/modules/*.php')) as $file) {
            Route::middleware('web')->group($file);
        }
    },
)
```

## Laravel 10 and older (`app/Providers/RouteServiceProvider.php`, inside `boot()` routes closure)

```php
foreach (glob(base_path('routes/modules/*.php')) as $file) {
    Route::middleware('web')->group($file);
}
```

Rule for module files: every route that is not public must sit inside `Route::middleware(['auth'])->group(...)` (plus the site's permission middleware). The guard flags new module route files that mention no `auth` middleware.
