<?php

namespace App\Http\Resources\V1;

use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin School */
class SchoolResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'country' => $this->country,
            'timezone' => $this->timezone,
            'default_locale' => $this->default_locale,
            'date_display' => $this->date_display,
            'ministry_code' => $this->ministry_code,
            'status' => $this->status,
        ];
    }
}
