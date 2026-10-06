<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['library_book_id', 'student_id', 'staff_member_id', 'borrowed_on', 'due_on', 'returned_on', 'issued_by'])]
class LibraryLoan extends Model
{
    use BelongsToSchool;

    protected function casts(): array
    {
        return ['borrowed_on' => DateOnly::class, 'due_on' => DateOnly::class, 'returned_on' => DateOnly::class];
    }

    /** @return BelongsTo<LibraryBook, $this> */
    public function book(): BelongsTo
    {
        return $this->belongsTo(LibraryBook::class, 'library_book_id');
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<StaffMember, $this> */
    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }

    public function borrowerName(): string
    {
        return $this->student?->name ?? $this->staffMember?->name ?? '';
    }

    public function isOverdue(): bool
    {
        return $this->returned_on === null && $this->due_on->lt(today());
    }
}
