<?php

namespace App\Actions\Schools;

use App\Enums\SchoolRole;
use App\Models\AttendanceCode;
use App\Models\Campus;
use App\Models\GradeLevel;
use App\Models\GradingScale;
use App\Models\Period;
use App\Models\School;
use App\Models\Stage;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Facades\DB;

/**
 * Onboards a school: the school row, a default campus, the Saudi general
 * education stages and grade levels, attendance codes, default roles, and
 * its first admin.
 */
class CreateSchool
{
    public function __construct(
        private ProvisionSchoolRoles $provisionRoles,
        private AddSchoolMember $addMember,
        private CurrentSchool $currentSchool,
    ) {}

    /** @param  array<string, mixed>  $attributes */
    public function handle(array $attributes, ?User $admin = null): School
    {
        return DB::transaction(function () use ($attributes, $admin) {
            $school = School::query()->create($attributes);

            $this->currentSchool->run($school, function (School $school) {
                Campus::query()->create([
                    'name_ar' => 'المقر الرئيسي',
                    'name_en' => 'Main campus',
                    'gender' => 'mixed',
                ]);

                $this->seedStages();
                $this->seedAttendanceCodes();
                $this->seedPeriods();
                $this->seedGradingScales();
            });

            $this->provisionRoles->handle($school);

            if ($admin !== null) {
                $this->addMember->handle($school, $admin, SchoolRole::SchoolAdmin);
            }

            return $school;
        });
    }

    /** Starting scales per stage / grade from config/madrasa_grading.php (Ministry-based, editable). */
    private function seedGradingScales(): void
    {
        foreach (config('madrasa_grading') as $definition) {
            [$stageId, $gradeId] = [null, null];

            if (str_starts_with($definition['scope'], 'grade:')) {
                [, $stageCode, $sequence] = explode(':', $definition['scope']);
                $gradeId = GradeLevel::query()->where('sequence', (int) $sequence)
                    ->whereHas('stage', fn ($q) => $q->where('code', $stageCode))->value('id');
                if ($gradeId === null) {
                    continue;
                }
            } elseif ($definition['scope'] !== 'default') {
                $stageId = Stage::query()->where('code', $definition['scope'])->value('id');
                if ($stageId === null) {
                    continue;
                }
            }

            $scale = GradingScale::query()->create([
                'stage_id' => $stageId,
                'grade_level_id' => $gradeId,
                'name' => $definition['name'],
                'is_default' => $definition['scope'] === 'default',
                'pass_percent' => $definition['pass_percent'],
            ]);

            foreach ($definition['bands'] as [$min, $ar, $en]) {
                $scale->bands()->create(['min_percent' => $min, 'label_ar' => $ar, 'label_en' => $en]);
            }
        }
    }

    /** A typical Saudi day: seven 45-minute lessons with a break after the third. */
    private function seedPeriods(): void
    {
        foreach ([
            ['الحصة الأولى', 'Period 1', '07:00', '07:45', false],
            ['الحصة الثانية', 'Period 2', '07:45', '08:30', false],
            ['الحصة الثالثة', 'Period 3', '08:30', '09:15', false],
            ['الفسحة', 'Break', '09:15', '09:40', true],
            ['الحصة الرابعة', 'Period 4', '09:40', '10:25', false],
            ['الحصة الخامسة', 'Period 5', '10:25', '11:10', false],
            ['الحصة السادسة', 'Period 6', '11:10', '11:55', false],
            ['الحصة السابعة', 'Period 7', '11:55', '12:40', false],
        ] as $index => [$ar, $en, $start, $end, $break]) {
            Period::query()->create([
                'sequence' => $index + 1,
                'name_ar' => $ar,
                'name_en' => $en,
                'starts_at' => $start,
                'ends_at' => $end,
                'is_break' => $break,
            ]);
        }
    }

    private function seedAttendanceCodes(): void
    {
        foreach ([
            ['P', 'حاضر', 'Present', 'present', false, true],
            ['A', 'غائب', 'Absent', 'absent', true, false],
            ['L', 'متأخر', 'Late', 'late', true, false],
            ['E', 'غائب بعذر', 'Excused absence', 'excused', false, false],
        ] as $sequence => [$code, $ar, $en, $kind, $notify, $default]) {
            AttendanceCode::query()->create([
                'code' => $code,
                'name_ar' => $ar,
                'name_en' => $en,
                'kind' => $kind,
                'notify_guardian' => $notify,
                'is_default' => $default,
                'sequence' => $sequence + 1,
            ]);
        }
    }

    private function seedStages(): void
    {
        foreach (config('madrasa_stages') as $stageSequence => $stage) {
            $model = Stage::query()->create([
                'code' => $stage['code'],
                'name_ar' => $stage['name_ar'],
                'name_en' => $stage['name_en'],
                'sequence' => $stageSequence + 1,
            ]);

            foreach ($stage['grades'] as $gradeSequence => [$nameAr, $nameEn]) {
                GradeLevel::query()->create([
                    'stage_id' => $model->getKey(),
                    'name_ar' => $nameAr,
                    'name_en' => $nameEn,
                    'sequence' => $gradeSequence + 1,
                ]);
            }
        }
    }
}
