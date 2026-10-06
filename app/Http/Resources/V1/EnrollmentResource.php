<?php

namespace App\Http\Resources\V1;

use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Enrollment */
class EnrollmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'academic_year_id' => $this->academic_year_id,
            'grade_level_id' => $this->grade_level_id,
            'section_id' => $this->section_id,
            'status' => $this->status,
            'enrolled_on' => $this->enrolled_on->toDateString(),
            'left_on' => $this->left_on?->toDateString(),
            'grade_level' => new GradeLevelResource($this->whenLoaded('gradeLevel')),
            'section' => new SectionResource($this->whenLoaded('section')),
        ];
    }
}
