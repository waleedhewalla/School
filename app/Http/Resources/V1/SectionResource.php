<?php

namespace App\Http\Resources\V1;

use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Section */
class SectionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'academic_year_id' => $this->academic_year_id,
            'grade_level_id' => $this->grade_level_id,
            'campus_id' => $this->campus_id,
            'name' => $this->name,
            'capacity' => $this->capacity,
            'grade_level' => new GradeLevelResource($this->whenLoaded('gradeLevel')),
        ];
    }
}
