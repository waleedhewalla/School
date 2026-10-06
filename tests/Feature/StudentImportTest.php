<?php

namespace Tests\Feature;

use App\Enums\SchoolRole;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\Concerns\CreatesSchools;
use Tests\TestCase;

class StudentImportTest extends TestCase
{
    use CreatesSchools, RefreshDatabase;

    /** Columns in a different order from the template, with Noor-style headers. */
    private function noorFile(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'noor').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['م', 'اسم الطالب', 'السجل المدني', 'الجنس', 'تاريخ الميلاد', 'الصف', 'الفصل', 'جوال ولي الأمر', 'ملاحظات']));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return new UploadedFile($path, 'noor.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_preview_then_import_from_a_noor_export(): void
    {
        Storage::fake('local');
        $school = $this->createSchool();
        $data = $this->seedSchoolData($school);
        $this->actingAs($this->memberOf($school, SchoolRole::Registrar));

        $file = $this->noorFile([
            [1, 'عبد الرحمن خالد سعد القحطاني', $this->saudiId(111), 'ذكر', '1440/09/09', 'الصف الأول الابتدائي', 'أ', '0551112222', ''],
            [2, 'لين خالد سعد القحطاني', $this->saudiId(222), 'أنثى', '2019-02-01', 'الصف الاول الابتدائي', '3', '+966551112222', 'أخت'],
            [3, 'فهد علي العمري', '1234567890', 'ذكر', '2019-01-01', 'الصف الأول الابتدائي', 'أ', '', ''],
            [4, 'نواف علي العمري', $this->saudiId(333), 'ذكر', '2019-01-01', 'الصف العاشر', 'أ', '', ''],
            [5, 'تكرار', $this->saudiId(111), 'ذكر', '', 'الصف الأول الابتدائي', '', '', ''],
        ]);

        $this->post('/students/import/preview', ['file' => $file, 'academic_year_id' => $data['year']->id])
            ->assertRedirect('/students/import');

        $this->get('/students/import')->assertInertia(fn (Assert $page) => $page
            ->component('Students/Import')
            ->where('report.0.status', 'ready')
            ->where('report.1.status', 'ready')
            ->where('report.1.messages.0', 'سيتم إنشاء الفصل «3».')
            ->where('report.2.status', 'error')
            ->where('report.2.messages.0', 'رقم هوية الطالب غير صحيح.')
            ->where('report.3.status', 'error')
            ->where('report.3.messages.0', 'الصف «الصف العاشر» غير موجود في المدرسة.')
            ->where('report.4.status', 'skipped'));

        // Nothing saved yet.
        $this->assertSame(2, $this->inSchool($school, fn () => Student::query()->count()));

        $this->post('/students/import')->assertRedirect('/students')->assertSessionHas('success', 'تم استيراد 2 طالبًا.');

        $this->inSchool($school, function () use ($data) {
            $abdulrahman = Student::query()->where('first_name_ar', 'عبد الرحمن')->sole();
            $leen = Student::query()->where('first_name_ar', 'لين')->sole();

            $this->assertSame(['خالد', 'سعد', 'القحطاني'], [$abdulrahman->father_name_ar, $abdulrahman->grandfather_name_ar, $abdulrahman->family_name_ar]);
            $this->assertSame('2019-05-14', $abdulrahman->date_of_birth->toDateString());
            $this->assertSame($abdulrahman->family_id, $leen->family_id, 'siblings share a family through the guardian mobile');
            $this->assertSame('966551112222', $leen->guardians()->first()->phone);
            $this->assertSame($data['sectionA']->id, $abdulrahman->enrollments()->first()->section_id);
            $this->assertSame('3', Section::query()->find($leen->enrollments()->first()->section_id)->name);
        });

        Storage::disk('local')->assertDirectoryEmpty('imports/'.$school->id);
    }

    public function test_importing_the_same_file_twice_skips_existing_students(): void
    {
        Storage::fake('local');
        $school = $this->createSchool();
        $data = $this->seedSchoolData($school);
        $this->actingAs($this->memberOf($school, SchoolRole::Registrar));
        $row = [[1, 'سلمان فهد الدوسري', $this->saudiId(444), 'ذكر', '', 'الصف الأول الابتدائي', 'أ', '', '']];

        foreach ([1, 2] as $attempt) {
            $this->post('/students/import/preview', ['file' => $this->noorFile($row), 'academic_year_id' => $data['year']->id]);
            $this->post('/students/import');
        }

        $this->assertSame(1, $this->inSchool($school, fn () => Student::query()->where('first_name_ar', 'سلمان')->count()));
    }

    public function test_unrecognized_file_and_permissions(): void
    {
        Storage::fake('local');
        $school = $this->createSchool();
        $data = $this->seedSchoolData($school);

        $path = tempnam(sys_get_temp_dir(), 'bad').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['foo', 'bar']));
        $writer->close();
        $bad = new UploadedFile($path, 'bad.xlsx', null, null, true);

        $this->actingAs($this->memberOf($school, SchoolRole::Registrar))
            ->post('/students/import/preview', ['file' => $bad, 'academic_year_id' => $data['year']->id])
            ->assertSessionHasErrors('file');

        $this->get('/students/import/template')->assertOk()->assertDownload('students-template.xlsx');

        $this->actingAs($data['teacher'])->get('/students/import')->assertForbidden();
    }
}
