<?php

namespace App\Http\Resources\V1;

use App\Models\AcademicYear;
use App\Support\Dates\SchoolDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AcademicYear */
class AcademicYearResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on->toDateString(),
            'starts_on_hijri' => SchoolDate::hijri($this->starts_on),
            'ends_on_hijri' => SchoolDate::hijri($this->ends_on),
            'is_current' => $this->is_current,
            'terms' => TermResource::collection($this->whenLoaded('terms')),
        ];
    }
}
