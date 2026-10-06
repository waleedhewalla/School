<?php

namespace App\Http\Resources\V1;

use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Term */
class TermResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sequence' => $this->sequence,
            'name' => $this->name,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on->toDateString(),
        ];
    }
}
