<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\HasReview;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A staff member's leave request, approved or rejected by management. */
#[Fillable(['staff_member_id', 'type', 'from_date', 'to_date', 'reason', 'attachment_path', 'attachment_name'])]
class LeaveRequest extends Model
{
    use BelongsToSchool, HasReview;

    public const TYPES = ['annual', 'sick', 'emergency', 'exam', 'other'];

    protected function casts(): array
    {
        return ['from_date' => DateOnly::class, 'to_date' => DateOnly::class, 'reviewed_at' => 'datetime'];
    }

    /** @return BelongsTo<StaffMember, $this> */
    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }

    /** Calendar days requested, both ends included. */
    public function days(): int
    {
        return (int) $this->from_date->diffInDays($this->to_date) + 1;
    }
}
