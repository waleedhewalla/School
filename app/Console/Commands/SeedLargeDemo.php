<?php

namespace App\Console\Commands;

use App\Actions\Grades\SaveAssessmentComponents;
use App\Actions\Schools\AddSchoolMember;
use App\Actions\Schools\CreateSchool;
use App\Enums\SchoolRole;
use App\Models\AcademicYear;
use App\Models\AssessmentComponent;
use App\Models\AttendanceCode;
use App\Models\Family;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\Section;
use App\Models\StaffMember;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * A realistic school for load testing: by default 600 primary students in
 * 18 sections, 18 teachers, ~60 school days of daily attendance and term
 * marks for six subjects. Development and staging only.
 */
#[Signature('madrasa:demo-large {--students=600} {--days=60} {--slug=large}')]
#[Description('Create a large demo school for load testing (never on production)')]
class SeedLargeDemo extends Command
{
    public function handle(CreateSchool $createSchool, AddSchoolMember $addMember, CurrentSchool $currentSchool): int
    {
        if (app()->isProduction()) {
            $this->error('Refusing to run in production.');

            return self::FAILURE;
        }

        $started = microtime(true);
        $password = Hash::make('password');
        $admin = User::query()->firstOrCreate(['email' => 'large-admin@example.com'], ['name' => 'مدير المدرسة الكبيرة', 'password' => $password, 'locale' => 'ar']);
        $school = $createSchool->handle(['slug' => $this->option('slug'), 'name_ar' => 'مدارس الاختبار الكبيرة', 'name_en' => 'Large Test Schools'], $admin);

        $currentSchool->run($school, function () use ($addMember, $school, $password) {
            $students = (int) $this->option('students');
            $days = (int) $this->option('days');

            $year = AcademicYear::query()->create(['name' => '1448', 'starts_on' => '2026-08-23', 'ends_on' => '2027-06-10']);
            $term = $year->terms()->create(['name_ar' => 'الفصل الدراسي الأول', 'name_en' => 'Term 1', 'sequence' => 1, 'starts_on' => '2026-08-23', 'ends_on' => '2026-11-19']);
            $year->makeCurrent();

            $subjects = collect([['QURAN', 'القرآن الكريم'], ['ISL', 'الدراسات الإسلامية'], ['ARB', 'اللغة العربية'], ['MATH', 'الرياضيات'], ['SCI', 'العلوم'], ['ENG', 'اللغة الإنجليزية']])
                ->map(fn ($s, $i) => Subject::query()->create(['code' => $s[0], 'sequence' => $i + 1, 'name_ar' => $s[1]]));

            $grades = GradeLevel::query()->whereHas('stage', fn ($q) => $q->where('code', 'primary'))->orderBy('sequence')->get();
            $sections = collect();
            foreach ($grades as $grade) {
                foreach (['أ', 'ب', 'ج'] as $name) {
                    $sections->push(Section::query()->create(['academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'name' => $name, 'capacity' => 40]));
                }
                foreach ($subjects as $subject) {
                    app(SaveAssessmentComponents::class)->handle($term, $grade, $subject, [
                        ['name_ar' => 'المشاركة', 'max_score' => 10, 'weight' => 20],
                        ['name_ar' => 'الاختبارات القصيرة', 'max_score' => 20, 'weight' => 30],
                        ['name_ar' => 'النهائي', 'max_score' => 50, 'weight' => 50],
                    ]);
                }
            }

            // One teacher per section, teaching all its subjects, as homeroom.
            foreach ($sections as $i => $section) {
                $user = User::query()->create(['name' => 'معلم '.($i + 1), 'email' => "large-teacher{$i}@example.com", 'password' => $password, 'locale' => 'ar']);
                $addMember->handle($school, $user, SchoolRole::Teacher);
                $staff = StaffMember::query()->create(['employee_number' => 'LT'.($i + 1), 'name_ar' => 'معلم '.($i + 1), 'user_id' => $user->id]);
                foreach ($subjects as $j => $subject) {
                    TeachingAssignment::query()->create(['academic_year_id' => $year->id, 'section_id' => $section->id, 'subject_id' => $subject->id, 'staff_member_id' => $staff->id, 'is_homeroom' => $j === 0]);
                }
            }

            $this->info("Students ({$students})…");
            $now = now();
            $firstNames = ['محمد', 'عبدالله', 'فهد', 'سارة', 'نورة', 'ريم', 'خالد', 'لمى', 'يوسف', 'جود', 'تركي', 'هيا'];
            $families = ['العتيبي', 'القحطاني', 'الشهري', 'الدوسري', 'الحربي', 'الغامدي', 'الزهراني', 'المطيري', 'السبيعي', 'العنزي'];
            $parent = User::query()->create(['name' => 'ولي أمر الاختبار', 'email' => 'large-parent@example.com', 'password' => $password, 'locale' => 'ar']);
            $addMember->handle($school, $parent, SchoolRole::Guardian);

            $studentIds = [];
            for ($n = 0; $n < $students; $n++) {
                // Two children per family on average.
                if ($n % 2 === 0) {
                    $family = Family::query()->create(['name' => $families[$n % 10]]);
                    $guardian = Guardian::query()->create(['family_id' => $family->id, 'name_ar' => 'ولي أمر '.$n, 'phone' => '05'.str_pad((string) (50000000 + $n), 8, '0', STR_PAD_LEFT)]);
                    if ($n === 0) {
                        $guardian->user()->associate($parent)->save();
                    }
                }
                $section = $sections[$n % $sections->count()];
                $student = Student::query()->create([
                    'family_id' => $family->id, 'student_number' => str_pad((string) ($n + 1), 6, '0', STR_PAD_LEFT),
                    'first_name_ar' => $firstNames[$n % 12], 'father_name_ar' => 'سعد', 'family_name_ar' => $families[$n % 10],
                    'gender' => in_array($n % 12, [3, 4, 5, 7, 9, 11], true) ? 'female' : 'male',
                ]);
                $student->guardians()->attach($guardian->id, ['relationship' => 'father', 'is_primary' => true]);
                $student->enrollments()->create(['academic_year_id' => $year->id, 'grade_level_id' => $section->grade_level_id, 'section_id' => $section->id, 'enrolled_on' => '2026-08-23']);
                $studentIds[$student->id] = $section->id;
            }

            $this->info("Attendance ({$days} days)…");
            $codes = AttendanceCode::query()->pluck('id', 'code');
            $teacherOf = TeachingAssignment::query()->where('is_homeroom', true)->with('staffMember')->get()->mapWithKeys(fn ($a) => [$a->section_id => $a->staffMember->user_id]);
            $dates = collect(CarbonPeriod::create('2026-08-23', '2026-11-19'))
                ->filter(fn ($d) => in_array($d->dayOfWeek, [0, 1, 2, 3, 4], true))
                ->take($days);
            foreach ($dates as $date) {
                $rows = [];
                foreach ($studentIds as $studentId => $sectionId) {
                    $r = mt_rand(1, 100);
                    $rows[] = [
                        'school_id' => $school->id, 'student_id' => $studentId, 'section_id' => $sectionId,
                        'attendance_code_id' => $codes[$r <= 90 ? 'P' : ($r <= 95 ? 'L' : ($r <= 98 ? 'A' : 'E'))],
                        'recorded_by' => $teacherOf[$sectionId], 'date' => CarbonImmutable::instance($date)->toDateString(), 'period' => 0,
                        'created_at' => $now, 'updated_at' => $now,
                    ];
                }
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('attendance_records')->insert($chunk);
                }
            }

            $this->info('Marks…');
            $components = AssessmentComponent::query()->get()->groupBy('grade_level_id');
            $gradeOfSection = $sections->pluck('grade_level_id', 'id');
            $rows = [];
            foreach ($studentIds as $studentId => $sectionId) {
                $level = mt_rand(45, 100) / 100;
                foreach ($components[$gradeOfSection[$sectionId]] as $component) {
                    $rows[] = [
                        'school_id' => $school->id, 'assessment_component_id' => $component->id, 'student_id' => $studentId,
                        'score' => round(min($component->max_score, $component->max_score * $level), 1), 'is_absent' => false,
                        'created_at' => $now, 'updated_at' => $now,
                    ];
                }
            }
            foreach (array_chunk($rows, 1000) as $chunk) {
                DB::table('assessment_scores')->insert($chunk);
            }
        });

        $this->info(sprintf('Done in %.1fs. Sign in as large-admin@example.com / large-teacher0@example.com / large-parent@example.com (password "password").', microtime(true) - $started));

        return self::SUCCESS;
    }
}
