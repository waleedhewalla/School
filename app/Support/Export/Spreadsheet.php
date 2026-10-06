<?php

namespace App\Support\Export;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Writes simple tables to an .xlsx download; sheets read right-to-left in Arabic. */
class Spreadsheet
{
    /**
     * @param  array<string, array{0: list<string>, 1: iterable<list<mixed>>}>  $sheets  sheet name => [headers, rows]
     */
    public static function download(string $filename, array $sheets): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'exp');
        $writer = new Writer;
        $writer->openToFile($path);
        $bold = new Style(fontBold: true);
        $first = true;

        foreach ($sheets as $name => [$headers, $rows]) {
            $sheet = $first ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
            $first = false;
            // Sheet names: max 31 characters, no []:*?/\
            $sheet->setName(mb_substr(preg_replace('/[\[\]:*?\/\\\\]/u', '-', $name), 0, 31));
            $sheet->setSheetView((new SheetView)->withRightToLeft(app()->getLocale() === 'ar'));

            $writer->addRow(new Row(array_map(fn ($h) => Cell::fromValue($h, $bold), $headers)));
            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues(array_map(fn ($v) => $v ?? '', $row)));
            }
        }
        $writer->close();

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }
}
