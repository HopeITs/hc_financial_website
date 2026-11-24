<?php

namespace App\Services\Ledger;

use Mpdf\Mpdf;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Writes a report made of several tables as XLSX or PDF, in the same way StatementExport writes a statement: in Arabic
 * the sheet opens mirrored and the PDF runs right to left. Numbers stay numbers in the sheet.
 *
 * A section is [heading, column headings, rows, last row is a total]; a cell that is a numeric string is written as a
 * number with two decimals, anything else as text.
 *
 * @phpstan-type Section array{0: string, 1: list<string>, 2: list<list<string|null>>, 3: bool}
 */
class TableExport
{
    /**
     * @param  list<string>  $lines  what the report is about, under its title
     * @param  list<Section>  $sections
     * @return string Path of a temporary file the caller reads and deletes.
     */
    public function xlsx(string $title, array $lines, array $sections, bool $rtl): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'tbl');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName(mb_substr($title, 0, 31));
        $writer->getCurrentSheet()->setSheetView((new SheetView)->withRightToLeft($rtl));

        $bold = (new Style)->withFontBold(true);
        $writer->addRow(new Row([Cell::fromValue($title, $bold)]));
        foreach ($lines as $line) {
            $writer->addRow(new Row([Cell::fromValue($line)]));
        }

        foreach ($sections as [$heading, $headings, $rows, $total]) {
            $writer->addRow(new Row([]));
            $writer->addRow(new Row([Cell::fromValue($heading, $bold)]));
            $writer->addRow(new Row(array_map(fn (string $column) => Cell::fromValue($column, $bold), $headings)));
            foreach ($rows as $n => $row) {
                $style = $total && $n === count($rows) - 1 ? $bold : null;
                $writer->addRow(new Row(array_map(fn (?string $value) => Cell::fromValue($this->isAmount($value) ? (float) $value : (string) $value, $style), $row)));
            }
        }

        $writer->close();

        return $path;
    }

    /**
     * @param  list<string>  $lines
     * @param  list<Section>  $sections
     */
    public function pdf(string $title, array $lines, array $sections, bool $rtl): string
    {
        $dir = storage_path('app/mpdf');
        is_dir($dir) || mkdir($dir, 0775, true);

        $mpdf = new Mpdf(['mode' => 'utf-8', 'tempDir' => $dir, 'autoScriptToLang' => true, 'autoLangToFont' => true, 'default_font_size' => 9, 'orientation' => 'L']);
        if ($rtl) {
            $mpdf->SetDirectionality('rtl');
        }

        $align = $rtl ? 'right' : 'left';
        $num = $rtl ? 'left' : 'right';
        $html = '<h2>'.e($title).'</h2>';
        foreach ($lines as $line) {
            $html .= '<p style="margin:0 0 2px">'.e($line).'</p>';
        }

        foreach ($sections as [$heading, $headings, $rows, $total]) {
            $html .= '<h3 style="margin-top:14px">'.e($heading).'</h3><table width="100%" cellpadding="4" style="border-collapse:collapse"><thead><tr>';
            foreach ($headings as $i => $column) {
                $html .= '<th style="border-bottom:1px solid #000;text-align:'.($i === 0 ? $align : $num).'">'.e($column).'</th>';
            }
            $html .= '</tr></thead><tbody>';

            foreach ($rows as $n => $row) {
                $last = $total && $n === count($rows) - 1;
                $html .= '<tr style="'.($last ? 'font-weight:bold;border-top:1px solid #000;' : '').'">';
                foreach ($row as $value) {
                    $html .= $this->isAmount($value)
                        ? '<td dir="ltr" style="text-align:'.$num.'">'.e(number_format((float) $value, 2)).'</td>'
                        : '<td style="text-align:'.$align.'">'.e((string) $value).'</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        }

        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    /**
     * Several reports in one workbook, one sheet each (the donor's financial reports, 2026-11-26).
     *
     * @param  list<array{title: string, sheet: string, lines: list<string>, sections: list<Section>}>  $parts
     * @return string Path of a temporary file the caller reads and deletes.
     */
    public function xlsxBook(array $parts, bool $rtl): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'tbl');
        $writer = new Writer;
        $writer->openToFile($path);
        $bold = (new Style)->withFontBold(true);

        foreach ($parts as $n => $part) {
            $sheet = $n === 0 ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName(mb_substr($part['sheet'], 0, 31));
            $sheet->setSheetView((new SheetView)->withRightToLeft($rtl));

            $writer->addRow(new Row([Cell::fromValue($part['title'], $bold)]));
            foreach ($part['lines'] as $line) {
                $writer->addRow(new Row([Cell::fromValue($line)]));
            }

            foreach ($part['sections'] as [$heading, $headings, $rows, $total]) {
                $writer->addRow(new Row([]));
                $writer->addRow(new Row([Cell::fromValue($heading, $bold)]));
                $writer->addRow(new Row(array_map(fn (string $column) => Cell::fromValue($column, $bold), $headings)));
                foreach ($rows as $i => $row) {
                    $style = $total && $i === count($rows) - 1 ? $bold : null;
                    $writer->addRow(new Row(array_map(fn (?string $value) => Cell::fromValue($this->isAmount($value) ? (float) $value : (string) $value, $style), $row)));
                }
            }
        }

        $writer->close();

        return $path;
    }

    /**
     * Several reports as one PDF on the organisation's letterhead, each on its own page, ending with signature lines
     * (the donor's financial reports, 2026-11-26). The letterhead is a whole A4 page, drawn behind each page.
     *
     * @param  list<array{title: string, sheet: string, lines: list<string>, sections: list<Section>}>  $parts
     * @param  list<string>  $signatures  the roles that sign, each over a line for the name, signature and date
     */
    public function pdfBook(array $parts, bool $rtl, array $signatures): string
    {
        $dir = storage_path('app/mpdf');
        is_dir($dir) || mkdir($dir, 0775, true);

        $mpdf = new Mpdf(['mode' => 'utf-8', 'tempDir' => $dir, 'autoScriptToLang' => true, 'autoLangToFont' => true, 'default_font_size' => 8.5,
            'format' => 'A4', 'margin_top' => 34, 'margin_bottom' => 22, 'margin_left' => 12, 'margin_right' => 12]);
        if ($rtl) {
            $mpdf->SetDirectionality('rtl');
        }

        $letterhead = public_path('images/micro-forms/letterhead.jpg');
        $page = is_file($letterhead) ? '@page { background: url("'.$letterhead.'") no-repeat 0 0; background-image-resize: 6; }' : '';
        $align = $rtl ? 'right' : 'left';
        $num = $rtl ? 'left' : 'right';
        $html = '<style>'.$page.' th, td { border-bottom: 0.3px solid #999; } h2 { margin: 0 0 4px; font-size: 13pt; } h3 { margin: 12px 0 4px; font-size: 10pt; }</style>';

        foreach ($parts as $n => $part) {
            if ($n > 0) {
                $html .= '<pagebreak />';
            }

            $html .= '<h2>'.e($part['title']).'</h2>';
            foreach ($part['lines'] as $line) {
                $html .= '<p style="margin:0 0 2px">'.e($line).'</p>';
            }

            foreach ($part['sections'] as [$heading, $headings, $rows, $total]) {
                $html .= '<h3>'.e($heading).'</h3><table width="100%" cellpadding="3" style="border-collapse:collapse"><thead><tr>';
                foreach ($headings as $i => $column) {
                    $html .= '<th style="border-bottom:1px solid #000;text-align:'.($i === 0 ? $align : $num).'">'.e($column).'</th>';
                }
                $html .= '</tr></thead><tbody>';

                foreach ($rows as $i => $row) {
                    $last = $total && $i === count($rows) - 1;
                    $html .= '<tr style="'.($last ? 'font-weight:bold;' : '').'">';
                    foreach ($row as $value) {
                        $html .= $this->isAmount($value)
                            ? '<td dir="ltr" style="text-align:'.$num.'">'.e(number_format((float) $value, 2)).'</td>'
                            : '<td style="text-align:'.$align.'">'.e((string) $value).'</td>';
                    }
                    $html .= '</tr>';
                }
                $html .= '</tbody></table>';
            }
        }

        if ($signatures !== []) {
            $html .= '<table width="100%" cellpadding="6" style="margin-top:28px;border-collapse:collapse"><tr>';
            foreach ($signatures as $role) {
                $html .= '<td style="border:0;text-align:center;vertical-align:top"><b>'.e($role).'</b><br><br><br>______________________</td>';
            }
            $html .= '</tr></table>';
        }

        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    /** A money figure from the reports: digits with exactly two decimals, so codes, dates and rates stay text. */
    private function isAmount(?string $value): bool
    {
        return $value !== null && preg_match('/^-?\d+\.\d{2}$/', $value) === 1;
    }
}
