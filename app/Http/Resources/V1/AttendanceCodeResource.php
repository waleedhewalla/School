<?php

namespace App\Http\Resources\V1;

use App\Models\AttendanceCode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AttendanceCode */
class AttendanceCodeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'kind' => $this->kind,
            'notify_guardian' => $this->notify_guardian,
            'is_default' => $this->is_default,
        ];
    }
}
