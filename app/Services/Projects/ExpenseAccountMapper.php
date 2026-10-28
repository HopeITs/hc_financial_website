<?php

namespace App\Services\Projects;

/**
 * Financial Manager decision R4-12: which expense account a budget line posts to, proposed from its Budget Line Type
 * and name. The first matching rule wins, so the narrower rules (staff training, certificates) come before the
 * wider ones (training, salaries). A line that matches nothing gets no proposal and the user picks the account.
 */
class ExpenseAccountMapper
{
    /** @var list<array{string, string}> account code => pattern, tried in order */
    private const RULES = [
        ['5104', '/staff\s+(training|activit)/i'],
        ['5403', '/certificate|hospitality|refreshment|catering/i'],
        ['5204', '/solar/i'],
        ['5406', '/training|event|course|workshop|seminar/i'],
        ['5408', '/travel|lodging|accommodation|hotel|per\s*diem/i'],
        ['5306', '/(internal\s+)?transport|taxi|bus\b/i'],
        ['5302', '/fuel|electric|water|generator|diesel|gasoline/i'],
        ['5303', '/phone|internet|mobile|communication|telecom/i'],
        ['5304', '/stationery|office\s+suppl/i'],
        ['5309', '/clean/i'],
        ['5307', '/maintenance|repair/i'],
        ['5301', '/\brent/i'],
        ['5102', '/other\s+staff|casual|volunteer|incentive/i'],
        ['5101', '/salar|wage|staff\s+cost|personnel|payroll/i'],
    ];

    public function propose(?string $type, ?string $name): ?string
    {
        // Staff training sits under the wider "training" type, so it is looked for in both together first.
        if (preg_match(self::RULES[0][1], trim($type.' '.$name)) === 1) {
            return self::RULES[0][0];
        }

        // The type is the better signal; the name decides only when the type says nothing.
        foreach ([$type, $name] as $text) {
            $text = trim((string) $text);

            if ($text === '') {
                continue;
            }

            foreach (self::RULES as [$code, $pattern]) {
                if (preg_match($pattern, $text) === 1) {
                    return $code;
                }
            }
        }

        return null;
    }
}
