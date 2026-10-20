<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use App\Http\Requests\SwitchLocaleRequest;
use Illuminate\Http\RedirectResponse;

class SwitchLocaleController extends Controller
{
    /**
     * Switch the interface language and go back to the same page.
     */
    public function __invoke(SwitchLocaleRequest $request): RedirectResponse
    {
        $request->session()->put(SetLocale::SessionKey, $request->validated('locale'));

        return redirect()->back();
    }
}
