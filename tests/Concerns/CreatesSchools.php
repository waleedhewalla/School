<?php

namespace Tests\Concerns;

use App\Actions\Attendance\RecordAttendance;
use App\Actions\Schools\AddSchoolMember;
use App\Actions\Schools\CreateSchool;
use App\Actions\Students\AdmitStudent;
use App\Enums\SchoolRole;
use App\Models\AbsenceExcuse;
use App\Models\AcademicYear;
use App\Models\AdmissionWindow;
use App\Models\Announcement;
use App\Models\Application;
use App\Models\AssessmentComponent;
use App\Models\AssessmentScore;
use App\Models\BehaviourCategory;
use App\Models\BehaviourIncident;
use App\Models\Bus;
use App\Models\BusRoute;
use App\Models\ClinicVisit;
use App\Models\ExternalExam;
use App\Models\ExternalExamResult;
use App\Models\GradeLevel;
use App\Models\HealthRecord;
use App\Models\Homework;
use App\Models\InventoryItem;
use App\Models\Invitation;
use App\Models\LeaveRequest;
use App\Models\LibraryBook;
use App\Models\LibraryLoan;
use App\Models\MessageLog;
use App\Models\PayrollRun;
use App\Models\Period;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\ReportCardComment;
use App\Models\School;
use App\Models\Section;
use App\Models\StaffContract;
use App\Models\StaffMember;
use App\Models\Student;
use App\Models\StudentTransport;
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
     * @return array{year: AcademicYear, grade: GradeLevel, sectionA: Section, sectionB: Section, subject: Subject, teacher: User, staff: StaffMember, students: list<Student>, window: AdmissionWindow, application: Application}
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

            Invitation::issue(['email' => 'invited@example.com', 'name' => 'مدعو', 'roles' => ['teacher']]);

            MessageLog::query()->create([
                'student_id' => $students[0]->id, 'channel' => 'sms', 'purpose' => 'attendance',
                'to' => '966500000000', 'body' => 'test', 'status' => 'sent',
            ]);

            // Admissions: an open grade-1 window with one submitted application.
            $window = AdmissionWindow::query()->create([
                'academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'seats' => 2,
                'opens_on' => now()->subDays(5), 'closes_on' => now()->addDays(30),
            ]);
            // Created directly: SubmitApplication would text the family and upset messaging tests.
            [$reference, $token] = Application::newIdentifiers($year);
            $application = Application::query()->create($this->applicationData() + [
                'admission_window_id' => $window->id, 'reference' => $reference, 'token' => $token,
                'token_hash' => hash('sha256', $token), 'status' => 'submitted', 'age_check' => 'ok',
                'consented_at' => now(), 'submitted_at' => now(),
            ]);
            $application->events()->create(['to_status' => 'submitted']);
            $application->documents()->create(['type' => 'photo', 'path' => 'admissions/x/photo.jpg', 'original_name' => 'photo.jpg']);

            return compact('year', 'grade', 'sectionA', 'sectionB', 'subject', 'teacher', 'staff', 'students', 'window', 'application');
        });
    }

    /** @return array<string, mixed> a valid public admission form submission (minus the window and consent) */
    protected function applicationData(array $overrides = []): array
    {
        return $overrides + [
            'first_name_ar' => 'ريم', 'father_name_ar' => 'سعد', 'family_name_ar' => 'الزهراني',
            'gender' => 'female', 'date_of_birth' => '2020-03-01', 'nationality' => 'SA',
            'guardian_name' => 'سعد الزهراني', 'guardian_phone' => '0559998877', 'guardian_relationship' => 'father',
        ];
    }

    /** One row of every school-life and service model (for tenant isolation checks). */
    protected function seedServiceData(School $school, array $d): void
    {
        $this->inSchool($school, function () use ($d) {
            ['year' => $year, 'sectionA' => $sectionA, 'subject' => $subject, 'staff' => $staff, 'students' => $students] = $d;
            Homework::query()->create(['section_id' => $sectionA->id, 'subject_id' => $subject->id, 'title' => 'واجب', 'due_on' => '2026-09-06']);
            BehaviourIncident::query()->create(['academic_year_id' => $year->id, 'student_id' => $students[1]->id,
                'behaviour_category_id' => BehaviourCategory::query()->value('id'), 'occurred_on' => '2026-09-01']);
            AbsenceExcuse::query()->create(['student_id' => $students[1]->id, 'from_date' => '2026-09-01', 'to_date' => '2026-09-01', 'reason' => 'مرض']);
            LeaveRequest::query()->create(['staff_member_id' => $staff->id, 'type' => 'annual', 'from_date' => '2026-10-01', 'to_date' => '2026-10-02']);
            HealthRecord::query()->create(['student_id' => $students[1]->id, 'blood_type' => 'A+']);
            ClinicVisit::query()->create(['student_id' => $students[1]->id, 'visited_at' => now(), 'complaint' => 'صداع', 'outcome' => 'returned_to_class']);
            $book = LibraryBook::query()->create(['title' => 'كتاب', 'copies' => 2]);
            LibraryLoan::query()->create(['library_book_id' => $book->id, 'student_id' => $students[1]->id, 'borrowed_on' => '2026-09-01', 'due_on' => '2026-09-15']);
            $bus = Bus::query()->create(['number' => '1', 'capacity' => 30]);
            $route = BusRoute::query()->create(['name' => 'خط', 'bus_id' => $bus->id]);
            $stop = $route->stops()->create(['name' => 'محطة', 'sequence' => 1]);
            StudentTransport::query()->create(['student_id' => $students[1]->id, 'bus_route_id' => $route->id, 'route_stop_id' => $stop->id]);
            InventoryItem::query()->create(['name' => 'طاولة', 'quantity' => 10]);

            $quiz = Quiz::query()->create(['section_id' => $sectionA->id, 'subject_id' => $subject->id, 'title' => 'اختبار',
                'opens_at' => now(), 'closes_at' => now()->addDay(), 'published' => true]);
            $quiz->questions()->create(['type' => 'true_false', 'body' => 'سؤال', 'correct' => [true], 'points' => 1]);
            QuizAttempt::query()->create(['quiz_id' => $quiz->id, 'student_id' => $students[0]->id, 'started_at' => now()]);
            StaffContract::query()->create(['staff_member_id' => $staff->id, 'basic_salary' => 5000]);
            $run = PayrollRun::query()->create(['month' => '2026-09', 'status' => 'draft']);
            $run->lines()->create(['staff_member_id' => $staff->id, 'basic' => 5000, 'housing' => 0, 'transport' => 0, 'other' => 0,
                'gosi_employee' => 0, 'gosi_employer' => 0, 'net' => 5000]);
            $exam = ExternalExam::query()->create(['academic_year_id' => $year->id, 'type' => 'nafes', 'name' => 'نافس']);
            ExternalExamResult::query()->create(['external_exam_id' => $exam->id, 'student_id' => $students[0]->id, 'score' => 80]);

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
