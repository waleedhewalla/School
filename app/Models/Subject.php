<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\HasBilingualName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['code', 'name_ar', 'name_en'])]
class Subject extends Model
{
    use BelongsToSchool, HasBilingualName, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
