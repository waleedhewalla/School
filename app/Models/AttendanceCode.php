<?php

namespace App\Models;

use App\Enums\AttendanceKind;
use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\HasBilingualName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name_ar', 'name_en', 'kind', 'notify_guardian', 'is_default', 'sequence'])]
class AttendanceCode extends Model
{
    use BelongsToSchool, HasBilingualName;

    protected function casts(): array
    {
        return [
            'kind' => AttendanceKind::class,
            'notify_guardian' => 'boolean',
            'is_default' => 'boolean',
        ];
    }
}
