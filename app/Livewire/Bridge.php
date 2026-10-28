<?php

namespace App\Livewire;

use App\Services\Bridge\DecisionsLog;
use App\Services\Bridge\PostingRuleCatalogue;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The three read-only bridge pages for Head Office and the Financial Manager (task 39), told apart by the route's
 * screen slug: the decisions log, the Windows program mapping and the posting rules.
 */
#[Layout('components.layouts.app')]
class Bridge extends Component
{
    public const PAGES = ['decisions', 'mapping', 'rules'];

    public string $screen = '';

    #[Url(as: 'q')]
    public string $search = '';

    public function mount(string $screen): void
    {
        abort_unless(in_array($screen, self::PAGES, true), 404);
        $this->screen = $screen;
    }

    public function render(DecisionsLog $log, PostingRuleCatalogue $catalogue): View
    {
        $sections = $this->screen === 'decisions' ? $log->sections() : [];

        return view('livewire.bridge', [
            'summary' => $log->summary($sections),
            'sections' => $this->filter($sections),
            'rules' => $this->screen === 'rules' ? $catalogue->rules() : [],
        ]);
    }

    /**
     * Keep only the table rows that contain the search text; while searching, notes and lists are hidden.
     *
     * @param  list<array{title: string, title_html: string, blocks: list<array<string, mixed>>}>  $sections
     * @return list<array{title: string, title_html: string, blocks: list<array<string, mixed>>}>
     */
    private function filter(array $sections): array
    {
        $needle = trim($this->search);

        if ($needle === '') {
            return $sections;
        }

        $kept = [];

        foreach ($sections as $section) {
            $blocks = [];

            foreach ($section['blocks'] as $block) {
                if ($block['type'] !== 'table') {
                    continue;
                }

                $block['rows'] = array_values(array_filter($block['rows'], fn (array $row): bool => collect($row)
                    ->contains(fn (array $cell): bool => mb_stripos($cell['text'], $needle) !== false)));

                if ($block['rows'] !== []) {
                    $blocks[] = $block;
                }
            }

            if ($blocks !== []) {
                $kept[] = ['title' => $section['title'], 'title_html' => $section['title_html'], 'blocks' => $blocks];
            }
        }

        return $kept;
    }
}
