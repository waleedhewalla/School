<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One status change in an application's history (who, when, why). */
#[Fillable(['application_id', 'user_id', 'from_status', 'to_status', 'note'])]
class ApplicationEvent extends Model
{
    use BelongsToSchool;

    protected function casts(): array
    {
        return ['from_status' => ApplicationStatus::class, 'to_status' => ApplicationStatus::class];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
