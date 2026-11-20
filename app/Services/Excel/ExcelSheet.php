<?php

namespace App\Services\Excel;

use DateTimeInterface;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Writing and reading the simple Excel tables of the data screens (finance request of 2026-11-20): bold headings on the
 * first row, one record per row, the sheets right to left in Arabic.
 */
class ExcelSheet
{
    /**
     * Writes the sheets in order, the first being the one the import reads.
     *
     * @param  array<string, array{headings: list<string>, rows: iterable<list<string|int|float|null>>}>  $sheets  sheet name => its table
     * @return string the path of a temporary file the caller sends and deletes
     */
    public function write(array $sheets, bool $rtl): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'xls');
        $writer = new Writer;
        $writer->openToFile($path);
        $bold = (new Style)->withFontBold(true);
        $first = true;

        foreach ($sheets as $name => $table) {
            $sheet = $first ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName(mb_substr((string) $name, 0, 31));
            $sheet->setSheetView((new SheetView)->withRightToLeft($rtl));
            $first = false;

            $writer->addRow(new Row(array_map(fn (string $heading): Cell => Cell::fromValue($heading, $bold), $table['headings'])));
            foreach ($table['rows'] as $row) {
                $writer->addRow(Row::fromValues($row));
            }
        }

        $writer->close();

        return $path;
    }

    /**
     * Reads the first sheet under its heading row: the given number of cells of each row that is not blank, as text,
     * with its row number in the sheet.
     *
     * @return list<array{row: int, cells: list<string>}>
     *
     * @throws InvalidArgumentException when the file cannot be read, holds no row or too many
     */
    public function read(string $path, int $columns, int $maxRows): array
    {
        $reader = new Reader;

        try {
            $reader->open($path);
        } catch (\Throwable) {
            throw new InvalidArgumentException(__('excel.unreadable'));
        }

        $rows = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $number => $row) {
                    if ($number === 1) {
                        continue;
                    }

                    $cells = array_pad(array_map($this->text(...), array_slice($row->toArray(), 0, $columns)), $columns, '');
                    if (implode('', $cells) === '') {
                        continue;
                    }

                    $rows[] = ['row' => (int) $number, 'cells' => $cells];

                    if (count($rows) > $maxRows) {
                        throw new InvalidArgumentException(__('excel.too_many', ['max' => $maxRows]));
                    }
                }

                break;
            }
        } finally {
            $reader->close();
        }

        if ($rows === []) {
            throw new InvalidArgumentException(__('excel.empty'));
        }

        return $rows;
    }

    private function text(mixed $cell): string
    {
        if ($cell instanceof DateTimeInterface) {
            return $cell->format('Y-m-d');
        }

        if (is_bool($cell)) {
            return $cell ? '1' : '0';
        }

        if (is_float($cell) || is_int($cell)) {
            return rtrim(rtrim(number_format((float) $cell, 6, '.', ''), '0'), '.');
        }

        return trim((string) $cell);
    }
}
