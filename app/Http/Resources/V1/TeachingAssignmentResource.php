<?php

namespace App\Http\Resources\V1;

use App\Models\TeachingAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TeachingAssignment */
class TeachingAssignmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'academic_year_id' => $this->academic_year_id,
            'section_id' => $this->section_id,
            'subject_id' => $this->subject_id,
            'staff_member_id' => $this->staff_member_id,
            'is_homeroom' => $this->is_homeroom,
            'subject' => new SubjectResource($this->whenLoaded('subject')),
            'staff_member' => new StaffMemberResource($this->whenLoaded('staffMember')),
        ];
    }
}
