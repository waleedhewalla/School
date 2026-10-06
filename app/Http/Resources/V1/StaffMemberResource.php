<?php

namespace App\Http\Resources\V1;

use App\Models\StaffMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StaffMember */
class StaffMemberResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_number' => $this->employee_number,
            'name' => $this->name,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'national_id' => $this->national_id,
            'job_title' => $this->job_title,
            'phone' => $this->phone,
            'email' => $this->email,
            'campus_id' => $this->campus_id,
            'user_id' => $this->user_id,
            'status' => $this->status,
        ];
    }
}
