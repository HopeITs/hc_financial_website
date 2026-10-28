<?php

namespace App\Services\Projects;

use Illuminate\Support\Str;

/**
 * Turns a budget sheet into project drafts (Financial Manager decisions R4-5 and R4-11): the sheet is shared between
 * donors, so each donor in the Donor column becomes its own project (ADISC -> ADISC-MCC and ADISC-OO), holding only
 * that donor's lines. Each line gets the expense account proposed from its type and name (R4-12), which the user
 * can change before saving. Nothing is saved here.
 */
class BudgetSheetImport
{
    public function __construct(private readonly BudgetSheetReader $reader, private readonly ExpenseAccountMapper $accounts) {}

    /**
     * @param  array<string, mixed>  $template  the project fields shared by every project of the sheet (code, name, city, ...)
     * @return list<array<string, mixed>>
     */
    public function drafts(string $path, array $template): array
    {
        $byDonor = [];

        foreach ($this->reader->read($path) as $line) {
            // A sheet without a donor column (or a blank cell) belongs to the donor already typed in the form.
            $line['donor'] = $line['donor'] !== '' ? $line['donor'] : (string) ($template['donor'] ?? '');

            $byDonor[$line['donor']][] = [
                'key' => null,
                'name' => $line['name'],
                'type' => $line['type'],
                'account_code' => $this->accounts->propose($line['type'], $line['name']) ?? '',
                'donor' => $line['donor'],
                'budget' => $line['budget'],
                'currency' => $line['currency'],
            ];
        }

        $split = count($byDonor) > 1;
        $drafts = [];

        foreach ($byDonor as $donor => $lines) {
            $suffix = Str::upper(Str::slug((string) $donor));

            $drafts[] = array_merge($template, [
                'code' => $split && $suffix !== '' ? $template['code'].'-'.$suffix : $template['code'],
                'name' => $split && $donor !== '' ? $template['name'].' - '.$donor : $template['name'],
                'donor' => (string) $donor,
                'lines' => $lines,
            ]);
        }

        return $drafts;
    }
}
