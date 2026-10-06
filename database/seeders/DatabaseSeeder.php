<?php

namespace Database\Seeders;

use App\Actions\Schools\AddSchoolMember;
use App\Actions\Schools\CreateSchool;
use App\Enums\SchoolRole;
use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Database\Seeder;

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

        $currentSchool->run($school, function () {
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
            ] as [$code, $ar, $en]) {
                Subject::query()->create(['code' => $code, 'name_ar' => $ar, 'name_en' => $en]);
            }
        });
    }
}
