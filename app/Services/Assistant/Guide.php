<?php

namespace App\Services\Assistant;

use App\Enums\ScreenAction;
use App\Models\User;
use App\Services\Home\ApprovalsInbox;
use App\Services\Treasury\DailyRates;
use App\Services\Treasury\MonthlyRates;
use App\Support\Navigation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * The assistant without an AI service (finance request of 2026-11-26): it answers "how do I…" questions from the
 * program's own guide (lang/<locale>/assistant_guide.php), links the screen the answer is about when the person may
 * open it, and adds live figures for the questions that have them (what waits for my approval, today's rates). It needs
 * no key and no internet.
 *
 * A question is matched against the words of every topic in both languages: a word counts when it starts a word of the
 * question, Arabic prefixes (و ف ب ل ك ال) allowed, so "بالمشتريات" finds "مشتريات".
 */
class Guide
{
    /** The screen each topic opens. */
    public const SCREENS = [
        'operations' => 'operations', 'payment' => 'single', 'receipt' => 'vouchers', 'donor' => 'contracts', 'approvals' => 'inbox',
        'inbox' => 'inbox', 'rejected' => 'vouchers', 'journal' => 'journal', 'requests' => 'requests', 'purchases' => 'purchases',
        'quotes' => 'purchases', 'receiving' => 'purchases', 'advances' => 'advance', 'payroll' => 'payroll', 'staff' => 'payroll',
        'recurring' => 'recurring', 'transfers' => 'transfers', 'rates' => 'alloc', 'cashcount' => 'tills', 'monthend' => 'monthend',
        'micro' => 'microfiles', 'instalments' => 'payments', 'funds' => 'loanfunds', 'revenues' => 'revenues', 'reports' => 'reports',
        'excel' => 'multi', 'users' => 'users', 'opening' => 'opening', 'print' => 'purchases', 'budget' => 'contracts',
    ];

    /** Topics whose answer adds live figures. */
    private const LIVE = ['inbox' => 'inbox', 'approvals' => 'inbox', 'rates' => 'rates'];

    private const RELATED = 2;

    public function __construct(
        private readonly ApprovalsInbox $inbox,
        private readonly DailyRates $daily,
        private readonly MonthlyRates $monthly,
    ) {}

    /**
     * The answer to a question.
     *
     * @return array{text: string, links: list<array{label: string, url: string}>, related: list<array{key: string, title: string}>}
     */
    public function answer(User $user, string $question): array
    {
        $scores = $this->scores($question);
        arsort($scores);
        $ranked = array_keys(array_filter($scores, fn (int $score): bool => $score > 0));

        if ($ranked === []) {
            return $this->notFound($user, $question);
        }

        $answer = $this->topic($user, $ranked[0]);
        $answer['related'] = $this->titles(array_slice($ranked, 1, self::RELATED));

        return $answer;
    }

    /**
     * One topic's answer, as picked from a suggestion.
     *
     * @return array{text: string, links: list<array{label: string, url: string}>, related: list<array{key: string, title: string}>}
     */
    public function topic(User $user, string $key): array
    {
        $topic = $this->topics()[$key] ?? null;

        if ($topic === null) {
            return $this->notFound($user, '');
        }

        $text = '**'.$topic['title']."**\n\n".$topic['answer'];
        $live = isset(self::LIVE[$key]) ? $this->live($user, self::LIVE[$key]) : null;

        if ($live !== null) {
            $text .= "\n\n".$live;
        }

        return ['text' => $text, 'links' => $this->link($user, self::SCREENS[$key] ?? null), 'related' => []];
    }

    /** @return list<array{key: string, title: string}> the topics offered before the first question */
    public function suggestions(): array
    {
        $keys = __('assistant_guide.suggestions');

        return $this->titles(is_array($keys) ? array_values($keys) : []);
    }

    /** @return array<string, int> topic key => how well it matches the question */
    private function scores(string $question): array
    {
        $text = ' '.$this->normalize($question).' ';
        $scores = [];

        foreach (array_keys($this->topics()) as $key) {
            $words = [];

            foreach (['ar', 'en'] as $locale) {
                $keywords = __("assistant_guide.topics.{$key}.keywords", [], $locale);
                $words = [...$words, ...(is_array($keywords) ? $keywords : [])];
            }

            $scores[$key] = 0;

            foreach (array_unique(array_map(fn (string $word): string => $this->normalize($word), $words)) as $word) {
                // A phrase says more than a single word, so it weighs by its words too.
                if ($word !== '' && $this->contains($text, $word)) {
                    $scores[$key] += mb_strlen($word) * (1 + substr_count($word, ' '));
                }
            }
        }

        return $scores;
    }

    /** Whether the word starts a word of the text, Arabic prefixes allowed; a short Latin word must also end one. */
    private function contains(string $text, string $word): bool
    {
        $end = preg_match('/^[a-z0-9+ ]{1,3}$/', $word) === 1 ? '(?=\s)' : '';

        return preg_match('/\s(?:و|ف|ب|ل|ك)?(?:ال|لل)?'.preg_quote($word, '/').$end.'/u', $text) === 1;
    }

    /** Lower case, no diacritics, one spelling of alef, ta marbuta and ya, no punctuation, single spaces. */
    private function normalize(string $text): string
    {
        $text = mb_strtolower($text);
        $text = (string) preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text);
        $text = strtr($text, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي']);
        $text = (string) preg_replace('/[^\p{L}\p{N}+]+/u', ' ', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * No topic matched: the screens whose names are in the question, and the topics to pick from.
     *
     * @return array{text: string, links: list<array{label: string, url: string}>, related: list<array{key: string, title: string}>}
     */
    private function notFound(User $user, string $question): array
    {
        $text = ' '.$this->normalize($question).' ';
        $links = [];

        foreach (Navigation::visibleGroups($user) as $screens) {
            foreach ($screens as $screen) {
                foreach (['ar', 'en'] as $locale) {
                    $name = $this->normalize((string) __('navigation.screens.'.$screen, [], $locale));

                    if ($name !== '' && mb_strlen($name) > 2 && $this->contains($text, $name)) {
                        $links = [...$links, ...$this->link($user, $screen)];

                        break;
                    }
                }
            }
        }

        return [
            'text' => $links === [] ? (string) __('assistant_guide.not_found') : (string) __('assistant_guide.screens_found'),
            'links' => array_slice($links, 0, 4),
            'related' => $links === [] ? $this->titles(array_keys($this->topics())) : [],
        ];
    }

    /** @return list<array{label: string, url: string}> the screen as a link, when the person may open it */
    private function link(User $user, ?string $screen): array
    {
        if ($screen === null || ! $user->canOnScreen($screen, $screen === 'single' ? ScreenAction::Create : ScreenAction::View)) {
            return [];
        }

        $route = Navigation::routeName($screen);

        if (! Route::has($route)) {
            return [];
        }

        return [['label' => (string) __('assistant_guide.open', ['screen' => __('navigation.screens.'.$screen)]), 'url' => route($route)]];
    }

    /** The live part of an answer, in the person's language, or null when they may not see it. */
    private function live(User $user, string $kind): ?string
    {
        try {
            return match ($kind) {
                'inbox' => $user->canOnScreen('inbox') ? $this->inboxLine($user) : null,
                'rates' => $user->canOnScreen('alloc') || $user->canOnScreen('treasury') ? $this->ratesLines() : null,
                default => null,
            };
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    private function inboxLine(User $user): string
    {
        $kinds = __('assistant_guide.live.inbox_kinds');
        $parts = [];
        $total = 0;

        foreach ($this->inbox->for($user) as $kind => $items) {
            if ($items !== []) {
                $total += count($items);
                $parts[] = '- '.count($items).' '.(is_array($kinds) ? ($kinds[$kind] ?? $kind) : $kind);
            }
        }

        return $total === 0 ? (string) __('assistant_guide.live.inbox_none') : __('assistant_guide.live.inbox_some', ['count' => $total])."\n".implode("\n", $parts);
    }

    private function ratesLines(): string
    {
        $day = CarbonImmutable::today();
        $none = (string) __('assistant_guide.live.rate_none');
        $lines = [__('assistant_guide.live.rates_title', ['day' => $day->toDateString()])];

        foreach ($this->daily->currencies() as $currency) {
            $daily = $this->daily->rateFor($currency, $day);
            $monthly = $this->monthly->forDay($currency, $day);
            $lines[] = '- '.__('assistant_guide.live.rate_line', [
                'currency' => $currency,
                'daily' => $daily !== null ? rtrim(rtrim((string) $daily->rate, '0'), '.') : $none,
                'monthly' => $monthly !== null ? rtrim(rtrim($monthly['rate'], '0'), '.') : $none,
            ]);
        }

        return implode("\n", $lines);
    }

    /** @return array<string, array{title: string, keywords: list<string>, answer: string}> */
    private function topics(): array
    {
        $topics = __('assistant_guide.topics');

        return is_array($topics) ? $topics : [];
    }

    /**
     * @param  list<string>  $keys
     * @return list<array{key: string, title: string}>
     */
    private function titles(array $keys): array
    {
        $topics = $this->topics();

        return array_values(array_map(fn (string $key): array => ['key' => $key, 'title' => $topics[$key]['title']], array_filter($keys, fn (string $key): bool => isset($topics[$key]))));
    }
}
