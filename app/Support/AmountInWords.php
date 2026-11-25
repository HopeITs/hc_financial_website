<?php

namespace App\Support;

/**
 * An amount written in words (التفقيط), in Arabic and in English, for the paper forms: the trust bond and the receipt
 * protocol carry the total in figures and in words. Whole units only; the cents, when there are any, follow as a
 * fraction of a hundred.
 */
class AmountInWords
{
    private const AR_UNITS = ['', 'واحد', 'اثنان', 'ثلاثة', 'أربعة', 'خمسة', 'ستة', 'سبعة', 'ثمانية', 'تسعة', 'عشرة', 'أحد عشر', 'اثنا عشر', 'ثلاثة عشر', 'أربعة عشر', 'خمسة عشر', 'ستة عشر', 'سبعة عشر', 'ثمانية عشر', 'تسعة عشر'];

    private const AR_TENS = ['', '', 'عشرون', 'ثلاثون', 'أربعون', 'خمسون', 'ستون', 'سبعون', 'ثمانون', 'تسعون'];

    private const AR_HUNDREDS = ['', 'مائة', 'مئتان', 'ثلاثمائة', 'أربعمائة', 'خمسمائة', 'ستمائة', 'سبعمائة', 'ثمانمائة', 'تسعمائة'];

    /** @var list<array{0: string, 1: string, 2: string}> one, two, three to ten */
    private const AR_SCALES = [['', '', ''], ['ألف', 'ألفان', 'آلاف'], ['مليون', 'مليونان', 'ملايين'], ['مليار', 'ملياران', 'مليارات']];

    private const EN_UNITS = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];

    private const EN_TENS = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

    private const EN_SCALES = ['', 'thousand', 'million', 'billion'];

    /** `SYP_NEW` is the new Syrian pound, a hundred old ones (the delivery protocol shows both). */
    private const CURRENCY_AR = ['USD' => 'دولار أمريكي', 'SYP' => 'ليرة سورية', 'SYP_NEW' => 'ليرة سورية جديدة', 'EUR' => 'يورو'];

    private const CURRENCY_EN = ['USD' => 'US dollars', 'SYP' => 'Syrian pounds', 'SYP_NEW' => 'new Syrian pounds', 'EUR' => 'euros'];

    /** "فقط ... دولار أمريكي لا غير" */
    public function arabic(string $amount, string $currency): string
    {
        [$whole, $cents] = $this->split($amount);
        $words = $whole === 0 ? 'صفر' : $this->arabicNumber($whole);

        return 'فقط '.$words.' '.(self::CURRENCY_AR[$currency] ?? $currency).($cents > 0 ? ' و'.$cents.'/100' : '').' لا غير';
    }

    /** "Only ... US dollars" */
    public function english(string $amount, string $currency): string
    {
        [$whole, $cents] = $this->split($amount);
        $words = $whole === 0 ? 'zero' : $this->englishNumber($whole);

        return 'Only '.$words.' '.(self::CURRENCY_EN[$currency] ?? $currency).($cents > 0 ? ' and '.$cents.'/100' : '');
    }

    /** @return array{0: int, 1: int} the whole units and the cents */
    private function split(string $amount): array
    {
        $clean = str_replace([',', ' '], '', trim($amount));
        if (preg_match('/^(\d+)(?:\.(\d{1,2}))?/', $clean, $match) !== 1) {
            return [0, 0];
        }

        return [(int) $match[1], isset($match[2]) ? (int) str_pad($match[2], 2, '0') : 0];
    }

    private function arabicNumber(int $number): string
    {
        $parts = [];

        foreach ($this->groups($number) as $scale => $group) {
            if ($group === 0) {
                continue;
            }

            if ($scale === 0) {
                $parts[] = $this->arabicGroup($group);
            } elseif ($group === 1) {
                $parts[] = self::AR_SCALES[$scale][0];
            } elseif ($group === 2) {
                $parts[] = self::AR_SCALES[$scale][1];
            } elseif ($group <= 10) {
                $parts[] = $this->arabicGroup($group).' '.self::AR_SCALES[$scale][2];
            } else {
                // "مئتا ألف", not "مئتان ألف", before the counted word.
                $parts[] = ($group === 200 ? 'مئتا' : $this->arabicGroup($group)).' '.self::AR_SCALES[$scale][0];
            }
        }

        return implode(' و', array_reverse($parts));
    }

    private function arabicGroup(int $group): string
    {
        $words = [];
        $hundreds = intdiv($group, 100);
        $rest = $group % 100;

        if ($hundreds > 0) {
            $words[] = self::AR_HUNDREDS[$hundreds];
        }

        if ($rest > 0 && $rest < 20) {
            $words[] = self::AR_UNITS[$rest];
        } elseif ($rest >= 20) {
            $words[] = ($rest % 10 > 0 ? self::AR_UNITS[$rest % 10].' و' : '').self::AR_TENS[intdiv($rest, 10)];
        }

        return implode(' و', $words);
    }

    private function englishNumber(int $number): string
    {
        $parts = [];

        foreach ($this->groups($number) as $scale => $group) {
            if ($group > 0) {
                $parts[] = trim($this->englishGroup($group).' '.self::EN_SCALES[$scale]);
            }
        }

        return implode(' ', array_reverse($parts));
    }

    private function englishGroup(int $group): string
    {
        $words = [];
        $hundreds = intdiv($group, 100);
        $rest = $group % 100;

        if ($hundreds > 0) {
            $words[] = self::EN_UNITS[$hundreds].' hundred';
        }

        if ($rest > 0 && $rest < 20) {
            $words[] = self::EN_UNITS[$rest];
        } elseif ($rest >= 20) {
            $words[] = self::EN_TENS[intdiv($rest, 10)].($rest % 10 > 0 ? '-'.self::EN_UNITS[$rest % 10] : '');
        }

        return implode(' ', $words);
    }

    /** @return array<int, int> the groups of three digits, the units first */
    private function groups(int $number): array
    {
        $groups = [];

        for ($scale = 0; $number > 0 && $scale < count(self::EN_SCALES); $scale++) {
            $groups[$scale] = $number % 1000;
            $number = intdiv($number, 1000);
        }

        return $groups;
    }
}
