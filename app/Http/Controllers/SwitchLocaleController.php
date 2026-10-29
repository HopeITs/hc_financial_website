<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Locale;
use App\Http\Middleware\SetLocale;
use App\Http\Requests\SwitchLocaleRequest;
use App\Support\UserPreferences;
use Illuminate\Http\RedirectResponse;

class SwitchLocaleController extends Controller
{
    /**
     * Switch the interface language and go back to the same page. A signed-in person keeps the choice for their
     * next sign-in, from any browser (U-12).
     */
    public function __invoke(SwitchLocaleRequest $request, UserPreferences $preferences): RedirectResponse
    {
        $locale = Locale::from((string) $request->validated('locale'));
        $request->session()->put(SetLocale::SessionKey, $locale->value);

        if ($request->user() !== null) {
            $preferences->setLocale((int) $request->user()->getAuthIdentifier(), $locale);
        }

        return redirect()->back();
    }
}
