<?php

namespace App\Http\Resources\V1;

use App\Models\AttendanceRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AttendanceRecord */
class AttendanceRecordResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'student_id' => $this->student_id,
            'section_id' => $this->section_id,
            'date' => $this->date->toDateString(),
            'period' => $this->period,
            'code' => $this->code?->code,
            'kind' => $this->code?->kind,
            'note' => $this->note,
        ];
    }
}
