<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['bus_route_id', 'name', 'sequence', 'pickup_at', 'dropoff_at'])]
class RouteStop extends Model
{
    use BelongsToSchool;

    /** @return BelongsTo<BusRoute, $this> */
    public function route(): BelongsTo
    {
        return $this->belongsTo(BusRoute::class, 'bus_route_id');
    }
}
