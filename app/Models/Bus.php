<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['number', 'plate', 'capacity', 'driver_name', 'driver_phone', 'supervisor_name', 'supervisor_phone'])]
class Bus extends Model
{
    use BelongsToSchool;

    /** @return HasMany<BusRoute, $this> */
    public function routes(): HasMany
    {
        return $this->hasMany(BusRoute::class);
    }
}
