<?php

namespace Database\Seeders;

use App\Actions\Attendance\RecordAttendance;
use App\Actions\Grades\SaveAssessmentComponents;
use App\Actions\Schools\AddSchoolMember;
use App\Actions\Schools\CreateSchool;
use App\Actions\Students\AdmitStudent;
use App\Actions\Timetable\PlaceLesson;
use App\Enums\SchoolRole;
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\AssessmentComponent;
use App\Models\AssessmentScore;
use App\Models\GradeLevel;
use App\Models\Period;
use App\Models\ReportCardComment;
use App\Models\Section;
use App\Models\StaffMember;
use App\Models\Student;
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

        $currentSchool->run($school, function () use ($teacher, $parent) {
            $year = AcademicYear::query()->create([
                'name' => '1448',
                'starts_on' => '2026-08-23',
                'ends_on' => '2027-06-10',
            ]);
            $year->terms()->createMany([
                ['name_ar' => 'الفصل الدراسي الأول', 'name_en' => 'Term 1', 'sequence' => 1, 'starts_on' => '2026-08-23', 'ends_on' => '2026-11-19'],
                ['name_ar' => 'الفصل الدراسي الثاني', 'name_en' => 'Term 2', 'sequence' => 2, 'starts_on' => '2026-11-29', 'ends_on' => '2027-03-04'],
                ['name_ar' => 'الفصل الدراسي الثالث', 'name_en' => 'Term 3', 'sequence' => 3, 'starts_on' => '2027-03-14', 'ends_on' => '2027-06-10'],
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
        });
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
