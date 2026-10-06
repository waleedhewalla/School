<?php

namespace App\Http\Resources\V1;

use App\Enums\Permission;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Student */
class StudentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_number' => $this->student_number,
            'has_account' => $this->user_id !== null,
            // National IDs only for staff who manage student records.
            'national_id' => $this->when($request->user()?->can(Permission::StudentsManage) ?? false, $this->national_id),
            'name' => $this->name,
            'name_ar' => $this->name_ar,
            'first_name_ar' => $this->first_name_ar,
            'father_name_ar' => $this->father_name_ar,
            'grandfather_name_ar' => $this->grandfather_name_ar,
            'family_name_ar' => $this->family_name_ar,
            'name_en' => $this->name_en,
            'gender' => $this->gender,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'nationality' => $this->nationality,
            'status' => $this->status,
            'family_id' => $this->family_id,
            'current_enrollment' => new EnrollmentResource($this->whenLoaded('currentEnrollment')),
            'enrollments' => EnrollmentResource::collection($this->whenLoaded('enrollments')),
            'guardians' => GuardianResource::collection($this->whenLoaded('guardians')),
        ];
    }
}
