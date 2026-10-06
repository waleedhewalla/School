<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['code', 'name', 'category', 'location', 'quantity', 'condition', 'purchased_on', 'unit_value', 'custodian_id', 'notes'])]
class InventoryItem extends Model
{
    use BelongsToSchool, LogsActivity;

    protected function casts(): array
    {
        return ['purchased_on' => DateOnly::class, 'unit_value' => 'decimal:2'];
    }

    public const CONDITIONS = ['good', 'needs_repair', 'damaged', 'disposed'];

    /** @return BelongsTo<StaffMember, $this> */
    public function custodian(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class, 'custodian_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
