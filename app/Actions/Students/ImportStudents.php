<?php

namespace App\Actions\Students;

use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\Section;
use App\Models\Student;
use App\Rules\SaudiNationalId;
use App\Support\ArabicName;
use App\Support\Dates\SchoolDate;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Imports students from rows read by NoorStudentSheet, in two steps:
 * preview (validate every row, change nothing) and commit (admit the
 * valid rows, skip the rest). Siblings are linked when they share a
 * guardian national ID or mobile number.
 */
class ImportStudents
{
    public function __construct(private AdmitStudent $admit) {}

    /**
     * @param  list<array{row: int, values: array<string, mixed>}>  $rows
     * @return list<array{row: int, name: string, status: string, messages: list<string>}>
     *                                                                                     status is "ready", "imported", "skipped" or "error"
     */
    public function handle(array $rows, AcademicYear $year, bool $commit): array
    {
        $grades = GradeLevel::query()->get();
        $sections = Section::query()->where('academic_year_id', $year->id)->get();
        $existingIds = Student::withTrashed()->whereNotNull('national_id')->pluck('national_id')->flip();
        $seenIds = [];
        $report = [];

        foreach ($rows as ['row' => $line, 'values' => $values]) {
            [$data, $errors, $notes] = $this->parse($values, $grades, $sections, $year);
            $name = $values['full_name_ar'] ?? '';
            $nationalId = $data['national_id'] ?? null;

            if ($nationalId !== null && ($existingIds->has($nationalId) || isset($seenIds[$nationalId]))) {
                $report[] = ['row' => $line, 'name' => $name, 'status' => 'skipped', 'messages' => [__('import.already_exists')]];

                continue;
            }

            if ($errors !== []) {
                $report[] = ['row' => $line, 'name' => $name, 'status' => 'error', 'messages' => $errors];

                continue;
            }

            if ($nationalId !== null) {
                $seenIds[$nationalId] = true;
            }

            if (! $commit) {
                $report[] = ['row' => $line, 'name' => $name, 'status' => 'ready', 'messages' => $notes];

                continue;
            }

            try {
                DB::transaction(function () use (&$data, &$sections, $year) {
                    $data['enrollment']['section_id'] = $this->sectionFor($data, $sections, $year)?->id;
                    $data['guardians'] = $this->guardiansFor($data['guardian']);
                    unset($data['guardian'], $data['enrollment']['section_name']);

                    $this->admit->handle($data);
                });
                $report[] = ['row' => $line, 'name' => $name, 'status' => 'imported', 'messages' => $notes];
            } catch (Throwable $e) {
                report($e);
                $report[] = ['row' => $line, 'name' => $name, 'status' => 'error', 'messages' => [__('import.failed')]];
            }
        }

        return $report;
    }

    /** @return array{0: array<string, mixed>, 1: list<string>, 2: list<string>} data, errors, notes */
    private function parse(array $v, Collection $grades, Collection $sections, AcademicYear $year): array
    {
        $errors = [];
        $notes = [];

        $name = ArabicName::split((string) ($v['full_name_ar'] ?? ''));
        if ($name['first'] === '' || $name['family'] === '') {
            $errors[] = __('import.name_required');
        }

        $nationalId = $this->digits($v['national_id'] ?? null);
        if ($nationalId !== null && ! SaudiNationalId::isValid($nationalId)) {
            $errors[] = __('import.invalid_national_id');
        }

        $gender = $this->gender($v['gender'] ?? null);
        if ($gender === null) {
            $errors[] = __('import.invalid_gender');
        }

        $birth = $this->date($v['date_of_birth'] ?? null);
        if (($v['date_of_birth'] ?? null) && $birth === null) {
            $errors[] = __('import.invalid_date');
        }

        $grade = $this->grade((string) ($v['grade'] ?? ''), $grades);
        if ($grade === null) {
            $errors[] = __('import.unknown_grade', ['grade' => (string) ($v['grade'] ?? '')]);
        }

        $sectionName = $this->sectionName($v['section'] ?? null);
        if ($grade && $sectionName !== null && ! $sections->contains(fn (Section $s) => (int) $s->grade_level_id === (int) $grade->id && $s->name === $sectionName)) {
            $notes[] = __('import.new_section', ['section' => $sectionName]);
        }

        $guardianId = $this->digits($v['guardian_national_id'] ?? null);
        if ($guardianId !== null && ! SaudiNationalId::isValid($guardianId)) {
            $errors[] = __('import.invalid_guardian_id');
        }
        $phone = PhoneNumber::normalize(isset($v['guardian_phone']) ? (string) $v['guardian_phone'] : null);
        if (filled($v['guardian_phone'] ?? null) && $phone === null) {
            $errors[] = __('import.invalid_phone');
        }

        return [[
            'national_id' => $nationalId,
            'first_name_ar' => $name['first'],
            'father_name_ar' => $name['father'],
            'grandfather_name_ar' => $name['grandfather'],
            'family_name_ar' => $name['family'],
            'name_en' => filled($v['name_en'] ?? null) ? (string) $v['name_en'] : null,
            'gender' => $gender,
            'date_of_birth' => $birth?->toDateString(),
            'nationality' => $this->nationality($v['nationality'] ?? null),
            'enrollment' => ['academic_year_id' => $year->id, 'grade_level_id' => $grade?->id, 'section_name' => $sectionName],
            'guardian' => [
                'name_ar' => filled($v['guardian_name'] ?? null) ? (string) $v['guardian_name'] : null,
                'national_id' => $guardianId,
                'phone' => $phone,
                'fallback_name' => trim(($name['father'] ?? '').' '.$name['family']),
            ],
        ], $errors, $notes];
    }

    private function sectionFor(array $data, Collection &$sections, AcademicYear $year): ?Section
    {
        $sectionName = $data['enrollment']['section_name'];

        if ($sectionName === null) {
            return null;
        }

        $gradeId = $data['enrollment']['grade_level_id'];
        $section = $sections->first(fn (Section $s) => (int) $s->grade_level_id === (int) $gradeId && $s->name === $sectionName);

        if ($section === null) {
            $section = Section::query()->create(['academic_year_id' => $year->id, 'grade_level_id' => $gradeId, 'name' => $sectionName]);
            $sections->push($section);
        }

        return $section;
    }

    /** Reuses a guardian already in the school (same ID or mobile), so siblings share a family. */
    private function guardiansFor(array $g): array
    {
        if ($g['national_id'] === null && $g['phone'] === null && $g['name_ar'] === null) {
            return [];
        }

        $existing = Guardian::query()
            ->where(fn ($q) => $q
                ->when($g['national_id'], fn ($q, $id) => $q->orWhere('national_id', $id))
                ->when($g['phone'], fn ($q, $phone) => $q->orWhere('phone', $phone)))
            ->when($g['national_id'] === null && $g['phone'] === null, fn ($q) => $q->whereRaw('1 = 0'))
            ->first();

        if ($existing) {
            return [['guardian_id' => $existing->id, 'relationship' => 'father', 'is_primary' => true]];
        }

        return [[
            'name_ar' => $g['name_ar'] ?: __('import.guardian_of', ['name' => $g['fallback_name']]),
            'national_id' => $g['national_id'],
            'phone' => $g['phone'],
            'relationship' => 'father',
            'is_primary' => true,
        ]];
    }

    private function digits(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_float($value)) {
            $value = number_format($value, 0, '', '');
        }

        $digits = preg_replace('/\D+/', '', strtr((string) $value, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']));

        return $digits === '' ? null : $digits;
    }

    private function gender(mixed $value): ?string
    {
        return match (ArabicName::normalize((string) $value)) {
            'ذكر', 'm', 'male', 'boy', 'طالب' => 'male',
            'انثي', 'f', 'female', 'girl', 'طالبه' => 'female',
            default => null,
        };
    }

    /** Accepts Excel dates, Y-m-d / d/m/Y strings, and Hijri dates (year below 1600). */
    private function date(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->startOfDay();
        }
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = $this->digitsOnlyDate($value);

        if (preg_match('/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})$/', $value, $m)) {
            [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})$/', $value, $m)) {
            [$y, $mo, $d] = [(int) $m[3], (int) $m[2], (int) $m[1]];
        } else {
            return null;
        }

        if ($mo < 1 || $mo > 12 || $d < 1 || $d > 31) {
            return null;
        }

        if ($y < 1600) {
            return $d <= 30 ? SchoolDate::fromHijri($y, $mo, $d) : null;
        }

        return checkdate($mo, $d, $y) ? CarbonImmutable::create($y, $mo, $d) : null;
    }

    private function digitsOnlyDate(string $value): string
    {
        return trim(strtr($value, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', 'هـ' => '', 'م' => '']));
    }

    private function grade(string $value, Collection $grades): ?GradeLevel
    {
        $wanted = ArabicName::normalize($value);

        return $wanted === '' ? null : $grades->first(fn (GradeLevel $g) => ArabicName::normalize($g->name_ar) === $wanted
            || ($g->name_en && ArabicName::normalize($g->name_en) === $wanted));
    }

    private function sectionName(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_float($value) ? (string) (int) $value : trim((string) $value);
    }

    private function nationality(mixed $value): string
    {
        $normalized = ArabicName::normalize((string) $value);

        return match (true) {
            $normalized === '', in_array($normalized, ['سعودي', 'سعوديه', 'السعوديه', 'saudi', 'sa'], true) => 'SA',
            strlen((string) $value) === 2 && ctype_alpha((string) $value) => strtoupper((string) $value),
            default => 'XX',
        };
    }
}
