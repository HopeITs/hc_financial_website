<?php

namespace App\Services\Ledger;

use Mpdf\Mpdf;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Writes a statement as XLSX or PDF. Both take the same flat rows, and in Arabic both are laid out right to left:
 * the sheet opens mirrored and the PDF has right-to-left direction.
 *
 * A row is [label, current, previous|null, kind] where kind is title, section, line or total.
 */
class StatementExport
{
    /**
     * @param  list<string>  $headings
     * @param  list<array{0: string, 1: string|null, 2: string|null, 3: string}>  $rows
     * @return string Path of a temporary file the caller reads and deletes.
     */
    public function xlsx(string $title, array $headings, array $rows, bool $rtl): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'stm');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName(mb_substr($title, 0, 31));
        $writer->getCurrentSheet()->setSheetView((new SheetView)->withRightToLeft($rtl));

        $bold = (new Style)->withFontBold(true);
        $writer->addRow(new Row(array_map(fn (string $heading) => Cell::fromValue($heading, $bold), $headings)));

        foreach ($rows as [$label, $current, $previous, $kind]) {
            $style = $kind === 'line' ? null : $bold;
            $cells = [Cell::fromValue($label, $style)];
            foreach ([$current, $previous] as $value) {
                if ($value !== null) {
                    $cells[] = Cell::fromValue((float) $value, $style);
                }
            }
            $writer->addRow(new Row($cells));
        }

        $writer->close();

        return $path;
    }

    /**
     * @param  list<string>  $headings
     * @param  list<array{0: string, 1: string|null, 2: string|null, 3: string}>  $rows
     */
    public function pdf(string $title, string $subtitle, array $headings, array $rows, bool $rtl): string
    {
        $dir = storage_path('app/mpdf');
        is_dir($dir) || mkdir($dir, 0775, true);

        $mpdf = new Mpdf(['mode' => 'utf-8', 'tempDir' => $dir, 'autoScriptToLang' => true, 'autoLangToFont' => true, 'default_font_size' => 10]);
        if ($rtl) {
            $mpdf->SetDirectionality('rtl');
        }

        $align = $rtl ? 'right' : 'left';
        $num = $rtl ? 'left' : 'right';
        $html = '<h2>'.e($title).'</h2><p>'.e($subtitle).'</p><table width="100%" cellpadding="4" style="border-collapse:collapse"><thead><tr>';
        foreach ($headings as $i => $heading) {
            $html .= '<th style="border-bottom:1px solid #000;text-align:'.($i === 0 ? $align : $num).'">'.e($heading).'</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($rows as [$label, $current, $previous, $kind]) {
            $weight = $kind === 'line' ? 'normal' : 'bold';
            $border = $kind === 'total' ? 'border-top:1px solid #000;' : '';
            $html .= '<tr style="font-weight:'.$weight.';'.$border.'"><td style="text-align:'.$align.'">'.e($label).'</td>';
            foreach ([$current, $previous] as $value) {
                if ($value !== null) {
                    $html .= '<td dir="ltr" style="text-align:'.$num.'">'.e(number_format((float) $value, 2)).'</td>';
                }
            }
            $html .= '</tr>';
        }

        $mpdf->WriteHTML($html.'</tbody></table>');

        return $mpdf->Output('', 'S');
    }
}
