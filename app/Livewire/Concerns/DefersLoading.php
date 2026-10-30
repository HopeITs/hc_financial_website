<?php

namespace App\Livewire\Concerns;

use Illuminate\Contracts\View\View;

/**
 * What a deferred screen shows while its figures load (U-10): the page title and a static skeleton of filters and
 * a table. The route turns deferring on with `->defer()`; Livewire then fetches the real screen right after.
 */
trait DefersLoading
{
    /** @param  array<string, mixed>  $params */
    public function placeholder(array $params = []): View
    {
        $slug = $params['screen'] ?? request()->route('screen');

        return view('components.ui.report-skeleton', [
            'title' => is_string($slug) ? __('navigation.screens.'.$slug) : __('home.title'),
        ]);
    }
}
