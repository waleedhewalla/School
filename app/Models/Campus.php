<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\HasBilingualName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name_ar', 'name_en', 'gender'])]
class Campus extends Model
{
    use BelongsToSchool, HasBilingualName, SoftDeletes;
}
