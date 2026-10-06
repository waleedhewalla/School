<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\HasReview;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A guardian's request to excuse a child's absence over a date range. */
#[Fillable(['student_id', 'submitted_by', 'from_date', 'to_date', 'reason', 'attachment_path', 'attachment_name'])]
class AbsenceExcuse extends Model
{
    use BelongsToSchool, HasReview;

    protected function casts(): array
    {
        return ['from_date' => DateOnly::class, 'to_date' => DateOnly::class, 'reviewed_at' => 'datetime'];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
