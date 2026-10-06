<?php

namespace Database\Seeders;

use App\Actions\Admissions\ChangeApplicationStatus;
use App\Actions\Admissions\SubmitApplication;
use App\Actions\Attendance\RecordAttendance;
use App\Actions\Grades\SaveAssessmentComponents;
use App\Actions\Schools\AddSchoolMember;
use App\Actions\Schools\CreateSchool;
use App\Actions\Students\AdmitStudent;
use App\Actions\Timetable\PlaceLesson;
use App\Enums\ApplicationStatus;
use App\Enums\SchoolRole;
use App\Models\AbsenceExcuse;
use App\Models\AcademicYear;
use App\Models\AdmissionWindow;
use App\Models\Announcement;
use App\Models\AssessmentComponent;
use App\Models\AssessmentScore;
use App\Models\BehaviourCategory;
use App\Models\BehaviourIncident;
use App\Models\Bus;
use App\Models\BusRoute;
use App\Models\ClinicVisit;
use App\Models\GradeLevel;
use App\Models\HealthRecord;
use App\Models\Homework;
use App\Models\InventoryItem;
use App\Models\LeaveRequest;
use App\Models\LibraryBook;
use App\Models\LibraryLoan;
use App\Models\Period;
use App\Models\ReportCardComment;
use App\Models\Section;
use App\Models\StaffMember;
use App\Models\Student;
use App\Models\StudentTransport;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * A demo school for local development. Every account's password is
 * "password". Never run this against production.
 */
class DatabaseSeeder extends Seeder
{
    public function run(CreateSchool $createSchool, AddSchoolMember $addMember, CurrentSchool $currentSchool): void
    {
        User::factory()->create([
            'name' => 'Platform Admin',
            'email' => 'platform@example.com',
            'is_platform_admin' => true,
        ]);

        $admin = User::factory()->create(['name' => 'مدير المدرسة', 'email' => 'admin@example.com', 'locale' => 'ar']);
        $teacher = User::factory()->create(['name' => 'معلم تجريبي', 'email' => 'teacher@example.com', 'locale' => 'ar']);

        $school = $createSchool->handle([
            'slug' => 'demo',
            'name_ar' => 'مدارس النموذج الأهلية',
            'name_en' => 'Demo Private Schools',
            'ministry_code' => '000000',
        ], $admin);

        $addMember->handle($school, $teacher, SchoolRole::Teacher);

        $parent = User::factory()->create(['name' => 'ولي أمر تجريبي', 'email' => 'parent@example.com', 'locale' => 'ar']);
        $addMember->handle($school, $parent, SchoolRole::Guardian);

        $currentSchool->run($school, function () use ($teacher, $parent, $admin) {
            $year = AcademicYear::query()->create([
                'name' => '1448',
                'starts_on' => '2026-08-23',
                'ends_on' => '2027-06-10',
            ]);
            // Two semesters, as in Saudi general education from 1447 (dates illustrative).
            $year->terms()->createMany([
                ['name_ar' => 'الفصل الدراسي الأول', 'name_en' => 'Semester 1', 'sequence' => 1, 'starts_on' => '2026-08-23', 'ends_on' => '2027-01-07'],
                ['name_ar' => 'الفصل الدراسي الثاني', 'name_en' => 'Semester 2', 'sequence' => 2, 'starts_on' => '2027-01-17', 'ends_on' => '2027-06-10'],
            ]);
            $year->makeCurrent();

            GradeLevel::query()->each(function (GradeLevel $grade) use ($year) {
                foreach (['أ', 'ب'] as $name) {
                    Section::query()->create([
                        'academic_year_id' => $year->id,
                        'grade_level_id' => $grade->id,
                        'name' => $name,
                        'capacity' => 30,
                    ]);
                }
            });

            foreach ([
                ['QURAN', 'القرآن الكريم', 'Holy Quran'],
                ['ISL', 'الدراسات الإسلامية', 'Islamic Studies'],
                ['ARB', 'اللغة العربية', 'Arabic Language'],
                ['MATH', 'الرياضيات', 'Mathematics'],
                ['SCI', 'العلوم', 'Science'],
                ['ENG', 'اللغة الإنجليزية', 'English Language'],
                ['SOC', 'الدراسات الاجتماعية', 'Social Studies'],
                ['DIGI', 'المهارات الرقمية', 'Digital Skills'],
                ['ART', 'التربية الفنية', 'Art Education'],
                ['PE', 'التربية البدنية والدفاع عن النفس', 'Physical Education'],
            ] as $sequence => [$code, $ar, $en]) {
                Subject::query()->create(['code' => $code, 'sequence' => $sequence + 1, 'name_ar' => $ar, 'name_en' => $en]);
            }

            $this->seedStudents($year, $teacher, $parent);
            $this->seedAdmissions($admin);
            $this->seedSchoolLife($year, $teacher, $parent, $admin);
            $this->seedServices($teacher);
        });
    }

    /** A bus route, library books with loans, inventory and a few health records. */
    private function seedServices(User $teacher): void
    {
        $students = Student::query()->orderBy('id')->limit(12)->get();

        $bus = Bus::query()->create(['number' => '3', 'plate' => 'أ ب ج 1234', 'capacity' => 26,
            'driver_name' => 'سالم القرني', 'driver_phone' => '0550001111', 'supervisor_name' => 'محمد الزهراني', 'supervisor_phone' => '0550002222']);
        $route = BusRoute::query()->create(['name' => 'حي النرجس', 'bus_id' => $bus->id]);
        $stops = collect([['دوار النرجس', '06:15', '13:20'], ['مسجد الفرقان', '06:25', '13:30'], ['حديقة الحي', '06:35', '13:40']])
            ->map(fn ($s, $i) => $route->stops()->create(['name' => $s[0], 'sequence' => $i + 1, 'pickup_at' => $s[1], 'dropoff_at' => $s[2]]));
        foreach ($students->take(6) as $i => $student) {
            StudentTransport::query()->create(['student_id' => $student->id, 'bus_route_id' => $route->id, 'route_stop_id' => $stops[$i % 3]->id]);
        }

        foreach ([['قصص الأنبياء', 'ابن كثير', 'قصص', 3], ['كليلة ودمنة', 'ابن المقفع', 'أدب', 2], ['موسوعة العلوم للأطفال', null, 'علوم', 1], ['الرياضيات الممتعة', null, 'رياضيات', 2]] as [$title, $author, $category, $copies]) {
            LibraryBook::query()->create(['title' => $title, 'author' => $author, 'category' => $category, 'copies' => $copies, 'shelf' => 'A'.random_int(1, 9)]);
        }
        LibraryLoan::query()->create(['library_book_id' => LibraryBook::query()->value('id'), 'student_id' => $students[2]->id, 'borrowed_on' => today()->subDays(20), 'due_on' => today()->subDays(6)]);
        LibraryLoan::query()->create(['library_book_id' => LibraryBook::query()->skip(1)->value('id'), 'student_id' => $students[0]->id, 'borrowed_on' => today()->subDays(3), 'due_on' => today()->addDays(11)]);

        foreach ([['سبورة تفاعلية', 'أجهزة', 'الصف الأول أ', 1, 'good', 6500], ['جهاز حاسب محمول', 'أجهزة', 'غرفة المعلمين', 8, 'good', 2800], ['طاولة طالب', 'أثاث', 'الصف الأول أ', 30, 'good', 220], ['مكيف سبليت', 'تكييف', 'المكتبة', 2, 'needs_repair', 1900]] as [$name, $category, $location, $qty, $condition, $value]) {
            InventoryItem::query()->create(['name' => $name, 'category' => $category, 'location' => $location, 'quantity' => $qty, 'condition' => $condition, 'unit_value' => $value]);
        }

        HealthRecord::query()->create(['student_id' => $students[1]->id, 'blood_type' => 'O+', 'allergies' => 'حساسية من الفول السوداني', 'emergency_contact_phone' => '0551234567']);
        HealthRecord::query()->create(['student_id' => $students[4]->id, 'chronic_conditions' => 'ربو', 'medications' => 'بخاخ عند الحاجة']);
        ClinicVisit::query()->create(['student_id' => $students[4]->id, 'visited_at' => now()->subDay()->setTime(9, 40), 'complaint' => 'ضيق تنفس خفيف', 'outcome' => 'rested', 'recorded_by' => $teacher->id]);
    }

    /** Homework, behaviour records, a pending excuse and a pending leave request in grade 1 أ. */
    private function seedSchoolLife(AcademicYear $year, User $teacher, User $parent, User $admin): void
    {
        $section = Section::query()->where('academic_year_id', $year->id)->where('name', 'أ')
            ->whereHas('gradeLevel', fn ($q) => $q->where('name_en', 'Grade 1'))->firstOrFail();
        $subject = fn (string $code) => Subject::query()->where('code', $code)->value('id');

        Homework::query()->create(['section_id' => $section->id, 'subject_id' => $subject('MATH'), 'created_by' => $teacher->id,
            'title' => 'حل تمارين الجمع صفحة 24', 'body' => 'التمارين 1 إلى 6 في كتاب الطالب.', 'due_on' => today()->addDays(2)]);
        Homework::query()->create(['section_id' => $section->id, 'subject_id' => $subject('ARB'), 'created_by' => $teacher->id,
            'title' => 'قراءة درس "أسرتي" وكتابة 3 كلمات جديدة', 'due_on' => today()->addDays(4)]);

        $students = Student::query()->inSection($section->id)->orderBy('id')->get();
        $category = fn (int|string $degreeOrKind) => is_int($degreeOrKind)
            ? BehaviourCategory::query()->where('degree', $degreeOrKind)->first()
            : BehaviourCategory::query()->where('kind', $degreeOrKind)->first();
        foreach ([[0, 1, 3], [1, 'positive', 1], [2, 2, 2], [0, 'positive', 0]] as [$i, $which, $daysAgo]) {
            BehaviourIncident::query()->create([
                'academic_year_id' => $year->id, 'student_id' => $students[$i]->id, 'behaviour_category_id' => $category($which)->id,
                'recorded_by' => $teacher->id, 'occurred_on' => today()->subDays($daysAgo),
            ]);
        }

        $child = Student::query()->guardedBy($parent)->first();
        AbsenceExcuse::query()->create(['student_id' => $child->id, 'submitted_by' => $parent->id,
            'from_date' => today()->subDay(), 'to_date' => today()->subDay(), 'reason' => 'مراجعة في المستشفى']);

        LeaveRequest::query()->create(['staff_member_id' => StaffMember::query()->where('user_id', $teacher->id)->value('id'),
            'type' => 'annual', 'from_date' => today()->addDays(10), 'to_date' => today()->addDays(12), 'reason' => 'ظرف عائلي']);
    }

    /** Next year's intake: KG1 and grade 1 windows open now, with applications at different steps. */
    private function seedAdmissions(User $admin): void
    {
        $next = AcademicYear::query()->create(['name' => '1449', 'starts_on' => '2027-08-22', 'ends_on' => '2028-06-08']);
        $grade = fn (string $en) => GradeLevel::query()->where('name_en', $en)->value('id');

        $kg = AdmissionWindow::query()->create([
            'academic_year_id' => $next->id, 'grade_level_id' => $grade('KG 1'), 'seats' => 20,
            'opens_on' => today()->subDays(10), 'closes_on' => today()->addDays(45),
            'born_from' => '2023-01-01', 'born_to' => '2024-08-31',
        ]);
        $first = AdmissionWindow::query()->create([
            'academic_year_id' => $next->id, 'grade_level_id' => $grade('Grade 1'), 'seats' => 2,
            'opens_on' => today()->subDays(10), 'closes_on' => today()->addDays(45),
            'born_from' => '2020-09-01', 'born_to' => '2021-08-31', 'exception_days' => 90,
        ]);

        $submit = app(SubmitApplication::class);
        $change = app(ChangeApplicationStatus::class);
        $family = fn (string $first, string $father, string $familyName, string $gender, string $born, string $phone) => [
            'first_name_ar' => $first, 'father_name_ar' => $father, 'family_name_ar' => $familyName,
            'gender' => $gender, 'date_of_birth' => $born, 'nationality' => 'SA',
            'guardian_name' => $father.' '.$familyName, 'guardian_phone' => $phone, 'guardian_relationship' => 'father',
        ];

        $steps = [
            [$first, $family('يوسف', 'خالد', 'الشهري', 'male', '2021-03-14', '0551000001'), [ApplicationStatus::UnderReview, ApplicationStatus::Offered]],
            [$first, $family('لمى', 'فهد', 'القحطاني', 'female', '2021-10-02', '0551000002'), [ApplicationStatus::UnderReview]],
            [$first, $family('عمر', 'سعد', 'الدوسري', 'male', '2020-12-20', '0551000003'), [ApplicationStatus::UnderReview, ApplicationStatus::Waitlisted]],
            [$kg, $family('جود', 'ماجد', 'الغامدي', 'female', '2023-06-11', '0551000004'), [ApplicationStatus::UnderReview, ApplicationStatus::AssessmentScheduled]],
            [$kg, $family('سلمان', 'تركي', 'المطيري', 'male', '2023-09-30', '0551000005'), []],
        ];
        foreach ($steps as [$window, $data, $moves]) {
            [$application] = $submit->handle($window, $data);
            foreach ($moves as $to) {
                $change->handle($application, $to, $admin, assessmentAt: $to === ApplicationStatus::AssessmentScheduled ? now()->addDays(5)->setTime(9, 0) : null);
            }
        }
    }

    /** Grade 1 section أ: 12 students, a math teacher, a parent with two children, and a register for yesterday. */
    private function seedStudents(AcademicYear $year, User $teacher, User $parent): void
    {
        $section = Section::query()
            ->where('academic_year_id', $year->id)
            ->whereHas('gradeLevel', fn ($q) => $q->where('name_en', 'Grade 1'))
            ->where('name', 'أ')
            ->firstOrFail();

        $staff = StaffMember::query()->create(['employee_number' => 'T-001', 'name_ar' => 'معلم تجريبي', 'name_en' => 'Demo Teacher', 'user_id' => $teacher->id]);
        $math = TeachingAssignment::query()->create([
            'academic_year_id' => $year->id,
            'section_id' => $section->id,
            'subject_id' => Subject::query()->where('code', 'MATH')->value('id'),
            'staff_member_id' => $staff->id,
            'is_homeroom' => true,
        ]);

        // A second teacher for Arabic and Quran, and a partly filled week for the section.
        $arabicTeacher = StaffMember::query()->create(['employee_number' => 'T-002', 'name_ar' => 'أ. سعد الحربي', 'name_en' => 'Saad Al-Harbi']);
        $subjects = ['ARB' => $arabicTeacher, 'QURAN' => $arabicTeacher, 'SCI' => $staff];
        $assignments = ['MATH' => $math];
        foreach ($subjects as $code => $teacherStaff) {
            $assignments[$code] = TeachingAssignment::query()->create([
                'academic_year_id' => $year->id,
                'section_id' => $section->id,
                'subject_id' => Subject::query()->where('code', $code)->value('id'),
                'staff_member_id' => $teacherStaff->id,
            ]);
        }

        $lessons = Period::query()->lessons()->get();
        $week = [['QURAN', 'ARB', 'MATH', 'SCI'], ['ARB', 'MATH', 'QURAN', 'ARB'], ['MATH', 'ARB', 'SCI', 'QURAN'], ['ARB', 'QURAN', 'MATH', 'SCI'], ['MATH', 'SCI', 'ARB', 'QURAN']];
        foreach ($week as $day => $codes) {
            foreach ($codes as $index => $code) {
                app(PlaceLesson::class)->handle($section, $day, $lessons[$index], $assignments[$code], $code === 'SCI' ? 'مختبر 1' : null);
            }
        }

        $names = [
            ['عبدالله', 'محمد', 'الشهري', 'male'], ['فيصل', 'سعد', 'القحطاني', 'male'], ['ريم', 'خالد', 'العتيبي', 'female'],
            ['نورة', 'خالد', 'العتيبي', 'female'], ['سلمان', 'فهد', 'الدوسري', 'male'], ['لمى', 'ناصر', 'الحربي', 'female'],
            ['تركي', 'عبدالعزيز', 'المطيري', 'male'], ['جود', 'إبراهيم', 'الزهراني', 'female'], ['يوسف', 'علي', 'الغامدي', 'male'],
            ['هيا', 'سلطان', 'السبيعي', 'female'], ['راكان', 'ماجد', 'العنزي', 'male'], ['دانة', 'عمر', 'الشمري', 'female'],
        ];

        $admit = app(AdmitStudent::class);
        $alOtaibiGuardian = null;
        $students = [];

        foreach ($names as [$first, $father, $family, $gender]) {
            $guardian = $family === 'العتيبي' && $alOtaibiGuardian
                ? ['guardian_id' => $alOtaibiGuardian, 'relationship' => 'father', 'is_primary' => true]
                : ['name_ar' => $father.' '.$family, 'phone' => '+9665'.random_int(10000000, 99999999), 'relationship' => 'father', 'is_primary' => true];

            $student = $admit->handle([
                'first_name_ar' => $first,
                'father_name_ar' => $father,
                'family_name_ar' => $family,
                'gender' => $gender,
                'date_of_birth' => '2020-0'.random_int(1, 9).'-1'.random_int(0, 9),
                'guardians' => [$guardian],
                'enrollment' => ['academic_year_id' => $year->id, 'grade_level_id' => $section->grade_level_id, 'section_id' => $section->id],
            ]);

            if ($family === 'العتيبي' && $alOtaibiGuardian === null) {
                $alOtaibiGuardian = $student->guardians()->first()->id;
                $student->guardians()->first()->user()->associate($parent)->save();
            }

            $students[] = $student;
        }

        // Term 1 assessment for the four scheduled subjects, with marks for everyone.
        $term = $year->terms()->orderBy('sequence')->first();
        foreach (['MATH', 'ARB', 'QURAN', 'SCI'] as $code) {
            $subject = Subject::query()->where('code', $code)->first();
            app(SaveAssessmentComponents::class)->handle($term, $section->gradeLevel, $subject, [
                ['name_ar' => 'المشاركة والواجبات', 'name_en' => 'Participation & homework', 'max_score' => 10, 'weight' => 20],
                ['name_ar' => 'الاختبارات القصيرة', 'name_en' => 'Quizzes', 'max_score' => 20, 'weight' => 30],
                ['name_ar' => 'الاختبار النهائي', 'name_en' => 'Final exam', 'max_score' => 50, 'weight' => 50],
            ]);

            $components = AssessmentComponent::query()->where('term_id', $term->id)->where('subject_id', $subject->id)->orderBy('sequence')->get();
            foreach ($students as $index => $student) {
                $level = [0.95, 0.88, 0.72, 0.6, 0.83, 0.91, 0.45, 0.79, 0.99, 0.67, 0.84, 0.74][$index];
                foreach ($components as $component) {
                    AssessmentScore::query()->create([
                        'assessment_component_id' => $component->id,
                        'student_id' => $student->id,
                        'score' => round(min($component->max_score, $component->max_score * $level + random_int(-1, 1)), 1),
                    ]);
                }
            }
        }

        Announcement::query()->create([
            'audience' => 'everyone', 'title' => 'بداية الفصل الدراسي الأول',
            'body' => "نرحب بأبنائنا الطلاب في العام الدراسي الجديد.\nالدوام يبدأ الساعة 6:45 صباحًا.", 'published_at' => now()->subDays(20),
        ]);
        Announcement::query()->create([
            'audience' => 'guardians', 'section_id' => $section->id, 'title' => 'اجتماع أولياء أمور الصف الأول',
            'body' => 'يسعدنا حضوركم يوم الأربعاء القادم بعد صلاة العصر.', 'published_at' => now()->subDays(2),
        ]);
        Announcement::query()->create([
            'audience' => 'staff', 'title' => 'موعد رصد درجات الفصل الأول',
            'body' => 'آخر موعد لرصد الدرجات نهاية الأسبوع القادم.', 'published_at' => now()->subDay(),
        ]);
        ReportCardComment::query()->create(['term_id' => $term->id, 'student_id' => $students[0]->id, 'comment' => 'طالب مجتهد ومنضبط، نتمنى له دوام التوفيق.']);

        $codes = ['P', 'P', 'P', 'A', 'P', 'L', 'P', 'P', 'P', 'P', 'E', 'P'];
        app(RecordAttendance::class)->handle(
            $section,
            Carbon::yesterday(),
            0,
            array_map(fn (Student $student, string $code) => ['student_id' => $student->id, 'code' => $code], $students, $codes),
            $teacher,
        );
    }
}
