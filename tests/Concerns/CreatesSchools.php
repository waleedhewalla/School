<?php

namespace Tests\Concerns;

use App\Actions\Schools\AddSchoolMember;
use App\Actions\Schools\CreateSchool;
use App\Enums\SchoolRole;
use App\Models\School;
use App\Models\User;
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
}
