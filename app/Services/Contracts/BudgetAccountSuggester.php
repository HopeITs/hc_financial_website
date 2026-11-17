<?php

namespace App\Services\Contracts;

use App\Models\GlBudgetLine;

/**
 * Proposes the expense account of an imported budget line, and says why, so the person checking the import knows how
 * far to trust it. Three sources, tried in order:
 *
 * 1. `rule`: the Financial Manager's rules (decision R4-12) on the line's type and name: salaries 5101, rent 5301...
 * 2. `learned`: a budget line with the same name already saved (any project) uses this account.
 * 3. `chart`: the expense account whose English or Arabic name shares the most words with the line.
 *
 * Only accounts the caller allows (the active expense posting accounts) are ever proposed. A line that nothing
 * matches gets no proposal, and the person picks the account.
 */
class BudgetAccountSuggester
{
    /** @var list<array{string, string}> account code => pattern, tried in order; the narrower rules come first */
    private const RULES = [
        ['5104', '/staff\s+(training|activit)|تدريب\s+(الموظفين|الكادر)/iu'],
        ['5403', '/certificate|hospitality|refreshment|catering|ضيافه|شهادات/iu'],
        ['5204', '/solar|طاقه\s+شمسيه/iu'],
        ['5406', '/training|event|course|workshop|seminar|تدريب|ورشه|دوره|فعاليه/iu'],
        ['5408', '/travel|lodging|accommodation|hotel|per\s*diem|سفر|اقامه|فندق/iu'],
        ['5306', '/(internal\s+)?transport|taxi|bus\b|مواصلات|نقل/iu'],
        ['5302', '/fuel|electric|water|generator|diesel|gasoline|وقود|كهرباء|مياه|مازوت|بنزين|مولده/iu'],
        ['5303', '/phone|internet|mobile|communication|telecom|اتصالات|انترنت|هاتف/iu'],
        ['5304', '/stationery|office\s+suppl|قرطاسيه/iu'],
        ['5309', '/clean|تنظيف/iu'],
        ['5307', '/maintenance|repair|صيانه|اصلاح/iu'],
        ['5301', '/\brent|ايجار/iu'],
        ['5102', '/other\s+staff|casual|volunteer|incentive|متطوع|حوافز/iu'],
        ['5101', '/salar|wage|staff\s+cost|personnel|payroll|رواتب|اجور|راتب/iu'],
    ];

    /** Words too common to say anything about an account. */
    private const STOP_WORDS = ['and', 'the', 'for', 'of', 'cost', 'costs', 'other', 'expenses', 'expense', 'and/or', 'مصاريف', 'مصروفات', 'اخرى', 'تكاليف'];

    /** @var array<string, string>|null normalised line name => the account most saved lines with that name use */
    private ?array $learned = null;

    /**
     * @param  array<string, string>  $accounts  the accounts that may be proposed: code => "code · name" (English or Arabic)
     * @param  array<string, string>  $otherNames  code => the account's name in the other language, for the chart match
     * @return array{account: string|null, source: string|null}
     */
    public function suggest(string $type, string $name, array $accounts, array $otherNames = []): array
    {
        $text = $this->normalise(trim($type.' '.$name));

        foreach ([$this->normalise($type.' '.$name), $this->normalise($type), $this->normalise($name)] as $candidate) {
            if ($candidate === '') {
                continue;
            }

            foreach (self::RULES as [$code, $pattern]) {
                if (isset($accounts[$code]) && preg_match($pattern, $candidate) === 1) {
                    return ['account' => $code, 'source' => 'rule'];
                }
            }
        }

        $learned = $this->learned()[$this->normalise($name)] ?? null;
        if ($learned !== null && isset($accounts[$learned])) {
            return ['account' => $learned, 'source' => 'learned'];
        }

        $best = null;
        $bestScore = 0;
        $words = $this->words($text);

        foreach ($accounts as $code => $label) {
            $score = count(array_intersect($words, $this->words($this->normalise($label.' '.($otherNames[$code] ?? '')))));

            if ($score > $bestScore) {
                [$best, $bestScore] = [(string) $code, $score];
            }
        }

        return $best !== null ? ['account' => $best, 'source' => 'chart'] : ['account' => null, 'source' => null];
    }

    /** @return array<string, string> */
    private function learned(): array
    {
        if ($this->learned !== null) {
            return $this->learned;
        }

        $counts = [];
        foreach (GlBudgetLine::query()->get(['name', 'account_code']) as $line) {
            $key = $this->normalise((string) $line->name);
            $counts[$key][$line->account_code] = ($counts[$key][$line->account_code] ?? 0) + 1;
        }

        return $this->learned = array_map(function (array $byAccount): string {
            arsort($byAccount);

            return (string) array_key_first($byAccount);
        }, $counts);
    }

    /** @return list<string> */
    private function words(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter($words, fn (string $word): bool => mb_strlen($word) >= 3 && ! is_numeric($word) && ! in_array($word, self::STOP_WORDS, true))));
    }

    private function normalise(string $text): string
    {
        $text = mb_strtolower(preg_replace('/\s+/u', ' ', $text) ?? $text);
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0640}]/u', '', $text) ?? $text;
        $text = strtr($text, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي']);

        return trim($text);
    }
}
