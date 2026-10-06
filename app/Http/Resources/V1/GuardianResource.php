<?php

namespace App\Http\Resources\V1;

use App\Models\Guardian;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Guardian */
class GuardianResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'national_id' => $this->national_id,
            'phone' => $this->phone,
            'email' => $this->email,
            'family_id' => $this->family_id,
            'has_account' => $this->user_id !== null,
            'relationship' => $this->whenPivotLoaded('guardian_student', fn () => $this->pivot->relationship),
            'is_primary' => $this->whenPivotLoaded('guardian_student', fn () => (bool) $this->pivot->is_primary),
        ];
    }
}
