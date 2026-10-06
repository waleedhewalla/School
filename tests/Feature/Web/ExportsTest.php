<?php

namespace Tests\Feature\Web;

use App\Enums\SchoolRole;
use App\Support\Import\NoorStudentSheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Concerns\CreatesSchools;
use Tests\TestCase;

class ExportsTest extends TestCase
{
    use CreatesSchools, RefreshDatabase;

    /** @return list<list<mixed>> rows of the first sheet */
    private function rows(string $content, int $sheetIndex = 0): array
    {
        $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        file_put_contents($path, $content);
        $reader = new Reader;
        $reader->open($path);
        $rows = [];
        foreach ($reader->getSheetIterator() as $i => $sheet) {
            if ($i - 1 === $sheetIndex) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $row->toArray();
                }
            }
        }
        $reader->close();

        return $rows;
    }

    public function test_student_export_round_trips_through_the_noor_reader(): void
    {
        $school = $this->createSchool();
        $d = $this->seedSchoolData($school);
        $this->inSchool($school, fn () => $d['students'][0]->update(['national_id' => $this->saudiId(55), 'date_of_birth' => '2019-09-01']));
        $this->actingAs($this->memberOf($school, SchoolRole::Registrar));

        $response = $this->get('/exports/students')->assertOk();
        $rows = $this->rows($response->streamedContent());
        $this->assertSame('رقم الهوية', $rows[0][0]);
        $this->assertCount(3, $rows);

        // The Noor importer recognises the file.
        $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        file_put_contents($path, $response->streamedContent());
        $sheet = NoorStudentSheet::read($path);
        $this->assertSame(2, count($sheet['rows']));
        $ahmad = collect($sheet['rows'])->firstWhere('values.national_id', $this->saudiId(55));
        $this->assertSame('ذكر', $ahmad['values']['gender']);
    }

    public function test_attendance_and_results_exports(): void
    {
        $school = $this->createSchool();
        $d = $this->seedSchoolData($school);
        $this->actingAs($this->memberOf($school, SchoolRole::Principal));

        $att = $this->rows($this->get('/exports/attendance?from=2026-08-23&to=2026-09-03')->assertOk()->streamedContent());
        $present = collect($att)->first(fn ($r) => $r[2] === $d['students'][0]->name_ar);
        $this->assertSame(1, (int) $present[5]);

        $term = $this->inSchool($school, fn () => $d['year']->terms()->first());
        $res = $this->rows($this->get("/exports/results?term_id={$term->id}")->assertOk()->streamedContent());
        $this->assertSame('الرقم الدراسي', $res[0][0]);
        $this->assertCount(3, $res);

        $this->get('/exports/attendance?from=2026-09-03&to=2026-08-01')->assertSessionHasErrors('to');
    }

    public function test_exports_need_the_matching_permission_and_stay_in_the_school(): void
    {
        $school = $this->createSchool();
        $d = $this->seedSchoolData($school);
        $other = $this->createSchool();
        $foreignSection = $this->seedSchoolData($other)['sectionA'];

        $this->actingAs($d['teacher']);
        $this->get('/exports/students')->assertForbidden();

        $this->actingAs($this->memberOf($school, SchoolRole::Registrar));
        $this->get("/exports/students?section_id={$foreignSection->id}")->assertSessionHasErrors('section_id');
        $this->get('/exports')->assertOk();
    }
}
