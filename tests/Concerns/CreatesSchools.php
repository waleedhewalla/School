<?php

namespace Tests\Concerns;

use App\Actions\Attendance\RecordAttendance;
use App\Actions\Schools\AddSchoolMember;
use App\Actions\Schools\CreateSchool;
use App\Actions\Students\AdmitStudent;
use App\Enums\SchoolRole;
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\AssessmentComponent;
use App\Models\AssessmentScore;
use App\Models\GradeLevel;
use App\Models\MessageLog;
use App\Models\Period;
use App\Models\ReportCardComment;
use App\Models\School;
use App\Models\Section;
use App\Models\StaffMember;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\TimetableEntry;
use App\Models\User;
use App\Rules\SaudiNationalId;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

trait CreatesSchools
{
    protected function createSchool(?User $admin = null, array $attributes = []): School
    {
        return app(CreateSchool::class)->handle($attributes + [
            'slug' => 'school-'.Str::lower(Str::random(6)),
            'name_ar' => 'مدرسة اختبار',
            'name_en' => 'Test School',
        ], $admin);
    }

    protected function memberOf(School $school, SchoolRole ...$roles): User
    {
        $user = User::factory()->create();
        app(AddSchoolMember::class)->handle($school, $user, ...$roles);

        return $user;
    }

    /** @return array<string, string> */
    protected function schoolHeader(School $school): array
    {
        return [config('madrasa.school_header') => $school->slug];
    }

    /** Runs a callback inside the school's context. */
    protected function inSchool(School $school, callable $callback): mixed
    {
        return app(CurrentSchool::class)->run($school, fn () => $callback());
    }

    /**
     * A current year with two sections of grade 1 (primary), a subject, a
     * teacher assigned to section A, and two enrolled students in A.
     *
     * @return array{year: AcademicYear, grade: GradeLevel, sectionA: Section, sectionB: Section, subject: Subject, teacher: User, staff: StaffMember, students: list<Student>}
     */
    protected function seedSchoolData(School $school): array
    {
        $teacher = $this->memberOf($school, SchoolRole::Teacher);

        return $this->inSchool($school, function () use ($teacher) {
            $year = AcademicYear::query()->create(['name' => '1448', 'starts_on' => '2026-08-23', 'ends_on' => '2027-06-10']);
            $year->terms()->create(['name_ar' => 'الأول', 'sequence' => 1, 'starts_on' => '2026-08-23', 'ends_on' => '2026-11-19']);
            $year->makeCurrent();

            $grade = GradeLevel::query()->whereHas('stage', fn ($q) => $q->where('code', 'primary'))->orderBy('sequence')->first();
            $sectionA = Section::query()->create(['academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'name' => 'أ', 'capacity' => 30]);
            $sectionB = Section::query()->create(['academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'name' => 'ب']);
            $subject = Subject::query()->create(['code' => 'MATH', 'name_ar' => 'الرياضيات']);

            $staff = StaffMember::query()->create(['employee_number' => 'T1', 'name_ar' => 'معلم', 'user_id' => $teacher->id]);
            TeachingAssignment::query()->create([
                'academic_year_id' => $year->id, 'section_id' => $sectionA->id,
                'subject_id' => $subject->id, 'staff_member_id' => $staff->id,
            ]);

            $students = collect(['محمد', 'سارة'])->map(fn ($first, $i) => app(AdmitStudent::class)->handle([
                'first_name_ar' => $first,
                'family_name_ar' => 'العتيبي',
                'gender' => $i === 0 ? 'male' : 'female',
                'guardians' => [['name_ar' => 'ولي أمر '.$first, 'relationship' => 'father', 'is_primary' => true]],
                'enrollment' => ['academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'section_id' => $sectionA->id],
            ]))->all();

            app(RecordAttendance::class)->handle($sectionA, Carbon::parse('2026-09-01'), 0, [
                ['student_id' => $students[0]->id, 'code' => 'P'],
            ], $teacher);

            // Ahmad's teacher teaches section A math on Sunday, period 1.
            TimetableEntry::query()->create([
                'academic_year_id' => $year->id, 'section_id' => $sectionA->id,
                'period_id' => Period::query()->where('sequence', 1)->value('id'),
                'teaching_assignment_id' => TeachingAssignment::query()->where('section_id', $sectionA->id)->value('id'),
                'staff_member_id' => $staff->id, 'day' => 0,
            ]);

            // Math in grade 1, term 1: classwork 40% (out of 20) + final 60% (out of 40).
            $term = $year->terms()->first();
            $classwork = AssessmentComponent::query()->create(['term_id' => $term->id, 'grade_level_id' => $grade->id, 'subject_id' => $subject->id, 'name_ar' => 'أعمال السنة', 'max_score' => 20, 'weight' => 40, 'sequence' => 1]);
            AssessmentComponent::query()->create(['term_id' => $term->id, 'grade_level_id' => $grade->id, 'subject_id' => $subject->id, 'name_ar' => 'الاختبار النهائي', 'max_score' => 40, 'weight' => 60, 'sequence' => 2]);
            AssessmentScore::query()->create(['assessment_component_id' => $classwork->id, 'student_id' => $students[0]->id, 'score' => 18]);

            ReportCardComment::query()->create(['term_id' => $term->id, 'student_id' => $students[0]->id, 'comment' => 'طالب متميز']);
            Announcement::query()->create(['audience' => 'everyone', 'title' => 'ترحيب', 'body' => 'أهلًا بكم', 'published_at' => now()->subDay()]);

            MessageLog::query()->create([
                'student_id' => $students[0]->id, 'channel' => 'sms', 'purpose' => 'attendance',
                'to' => '966500000000', 'body' => 'test', 'status' => 'sent',
            ]);

            return compact('year', 'grade', 'sectionA', 'sectionB', 'subject', 'teacher', 'staff', 'students');
        });
    }

    /** A valid Saudi national ID (or iqama when $first is 2) built from a seed. */
    protected function saudiId(int $seed, int $first = 1): string
    {
        $body = $first.str_pad((string) $seed, 8, '0', STR_PAD_LEFT);

        for ($check = 0; $check <= 9; $check++) {
            if (SaudiNationalId::isValid($body.$check)) {
                return $body.$check;
            }
        }

        throw new \LogicException('unreachable');
    }
}
