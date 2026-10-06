<?php

namespace App\Http\Resources\V1;

use App\Models\GradeLevel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GradeLevel */
class GradeLevelResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'stage_id' => $this->stage_id,
            'sequence' => $this->sequence,
            'name' => $this->name,
        ];
    }
}
