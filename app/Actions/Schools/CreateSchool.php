<?php

namespace App\Actions\Schools;

use App\Enums\SchoolRole;
use App\Models\AttendanceCode;
use App\Models\Campus;
use App\Models\GradeLevel;
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
            });

            $this->provisionRoles->handle($school);

            if ($admin !== null) {
                $this->addMember->handle($school, $admin, SchoolRole::SchoolAdmin);
            }

            return $school;
        });
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
