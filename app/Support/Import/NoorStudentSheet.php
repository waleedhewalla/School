<?php

namespace App\Support\Import;

use App\Support\ArabicName;
use DateTimeInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Reads a student list exported from Noor (or typed by hand in the same
 * layout). Columns are found by header text, in any order, using the
 * aliases below; extra columns are ignored.
 *
 * The aliases follow common Noor export headers. Exports differ between
 * Noor screens and versions, so check them against a real export from
 * each pilot school and add aliases here as needed.
 */
class NoorStudentSheet
{
    /** Canonical column => accepted header texts (compared after ArabicName::normalize). */
    public const COLUMNS = [
        'national_id' => ['رقم الهوية', 'رقم هوية الطالب', 'السجل المدني', 'رقم السجل المدني', 'هوية الطالب', 'رقم الاقامة', 'السجل المدني او الاقامة', 'السجل المدني الاقامة', 'الهوية الاقامة', 'رقم السجل المدني الاقامة', 'رقم الهوية الاقامة', 'الهوية', 'رقم الهوية الوطنية', 'السجل', 'national id', 'id number'],
        'full_name_ar' => ['اسم الطالب', 'اسم الطالبة', 'الاسم', 'اسم الطالب رباعي', 'اسم الطالب رباعيا', 'الاسم رباعي', 'الاسم الرباعي', 'اسم الطالب بالعربي', 'student name'],
        'name_en' => ['اسم الطالب بالانجليزي', 'الاسم بالانجليزي', 'الاسم باللغة الانجليزية', 'english name', 'name (english)'],
        'gender' => ['الجنس', 'gender'],
        'date_of_birth' => ['تاريخ الميلاد', 'تاريخ الميلاد ميلادي', 'تاريخ الميلاد هجري', 'تاريخ الميلاد هـ', 'تاريخ الميلاد م', 'تاريخ الميلاد الهجري', 'تاريخ الميلاد الميلادي', 'date of birth', 'birth date'],
        'nationality' => ['الجنسية', 'nationality'],
        'grade' => ['الصف', 'الصف الدراسي', 'المستوى', 'السنة الدراسية', 'grade'],
        'section' => ['الفصل', 'الشعبة', 'رقم الفصل', 'رقم الشعبة', 'اسم الفصل', 'section', 'class'],
        'guardian_name' => ['اسم ولي الامر', 'ولي الامر', 'guardian name'],
        'guardian_national_id' => ['هوية ولي الامر', 'رقم هوية ولي الامر', 'guardian id'],
        'guardian_phone' => ['جوال ولي الامر', 'رقم جوال ولي الامر', 'هاتف ولي الامر', 'رقم الجوال', 'الجوال', 'جوال', 'رقم الهاتف', 'الهاتف', 'guardian phone', 'mobile'],
        // Read only so it is never mistaken for the guardian's number.
        'student_phone' => ['جوال الطالب', 'رقم جوال الطالب', 'هاتف الطالب', 'student phone'],
    ];

    /** Headers written to the downloadable template. */
    public const TEMPLATE_HEADERS = [
        'رقم الهوية', 'اسم الطالب', 'اسم الطالب بالانجليزي', 'الجنس', 'تاريخ الميلاد (هـ)', 'الجنسية',
        'الصف', 'الفصل', 'اسم ولي الأمر', 'هوية ولي الأمر', 'جوال ولي الأمر',
    ];

    /**
     * @return array{columns: array<string, int>, rows: list<array{row: int, values: array<string, mixed>}>}
     *                                                                                                       rows keyed by canonical column; row numbers as in Excel
     */
    /**
     * Noor exports usually have a few merged title rows above the real
     * header row, so the header is the first row (within the first 15 of
     * any sheet) that names the student column and at least one other.
     *
     * @return array{columns: array<string, int>, rows: list<array{row: int, values: array<string, mixed>}>}
     *                                                                                                       rows keyed by canonical column; row numbers as in Excel
     */
    public static function read(string $path, int $maxRows = PHP_INT_MAX): array
    {
        // Keep empty rows so the row numbers we report match Excel's.
        $reader = new Reader(new Options(SHOULD_PRESERVE_EMPTY_ROWS: true));
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $columns = [];
                $rows = [];
                $line = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    $line++;
                    $cells = $row->toArray();

                    if ($columns === []) {
                        $found = self::matchHeaders($cells);
                        if (isset($found['full_name_ar']) && count($found) >= 2) {
                            $columns = $found;
                        } elseif ($line >= 15) {
                            continue 2; // not a student list; try the next sheet
                        }

                        continue;
                    }

                    if (collect($cells)->filter(fn ($v) => $v !== null && trim((string) ($v instanceof DateTimeInterface ? 'x' : $v)) !== '')->isEmpty()) {
                        continue;
                    }

                    $values = [];
                    foreach ($columns as $key => $index) {
                        $value = $cells[$index] ?? null;
                        $values[$key] = is_string($value) ? trim($value) : $value;
                    }
                    $rows[] = ['row' => $line, 'values' => $values];

                    if (count($rows) >= $maxRows) {
                        break;
                    }
                }

                if ($columns !== []) {
                    return ['columns' => $columns, 'rows' => $rows];
                }
            }
        } finally {
            $reader->close();
        }

        return ['columns' => [], 'rows' => []];
    }

    public static function writeTemplate(string $path): void
    {
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(self::TEMPLATE_HEADERS));
        $writer->close();
    }

    /** @return array<string, int> canonical column => cell index */
    private static function matchHeaders(array $cells): array
    {
        $aliases = [];
        foreach (self::COLUMNS as $key => $names) {
            foreach ($names as $name) {
                $aliases[self::normalizeHeader($name)] = $key;
            }
        }

        $columns = [];
        foreach ($cells as $index => $header) {
            $key = $aliases[self::normalizeHeader((string) $header)] ?? null;
            if ($key !== null && ! isset($columns[$key])) {
                $columns[$key] = $index;
            }
        }

        return $columns;
    }

    /** Headers are compared without brackets, slashes and punctuation: "تاريخ الميلاد (هـ)" = "تاريخ الميلاد هـ". */
    private static function normalizeHeader(string $header): string
    {
        return ArabicName::normalize(preg_replace('/[()\[\]{}\/\\:\-_.،,*]+/u', ' ', $header));
    }
}
