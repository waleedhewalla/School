<?php

namespace App\Http\Resources\V1;

use App\Models\Stage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Stage */
class StageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'grade_levels' => GradeLevelResource::collection($this->whenLoaded('gradeLevels')),
        ];
    }
}
