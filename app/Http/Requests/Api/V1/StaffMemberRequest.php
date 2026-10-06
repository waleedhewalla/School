<?php

namespace App\Http\Requests\Api\V1;

use App\Models\StaffMember;
use App\Rules\ExistsInCurrentSchool;
use App\Rules\SaudiNationalId;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffMemberRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var StaffMember|null $staff */
        $staff = $this->route('staff_member');
        $creating = $staff === null;
        $schoolId = app(CurrentSchool::class)->id();

        return [
            'employee_number' => [Rule::requiredIf($creating), 'string', 'alpha_dash:ascii', 'max:30',
                Rule::unique('staff_members')->where('school_id', $schoolId)->ignore($staff?->id)],
            'name_ar' => [Rule::requiredIf($creating), 'string', 'max:150'],
            'name_en' => ['nullable', 'string', 'max:150'],
            'national_id' => ['nullable', 'string', new SaudiNationalId],
            'job_title' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9]{9,15}$/'],
            'email' => ['nullable', 'email'],
            'campus_id' => ['nullable', 'integer', ExistsInCurrentSchool::in('campuses')],
            // Link to a login: the user must already be a member of this school.
            'user_id' => ['nullable', 'integer',
                Rule::exists('memberships', 'user_id')->where('school_id', $schoolId),
                Rule::unique('staff_members')->where('school_id', $schoolId)->ignore($staff?->id)],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}
