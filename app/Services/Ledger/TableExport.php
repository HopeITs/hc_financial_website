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

    /** A money figure from the reports: digits with exactly two decimals, so codes, dates and rates stay text. */
    private function isAmount(?string $value): bool
    {
        return $value !== null && preg_match('/^-?\d+\.\d{2}$/', $value) === 1;
    }
}
