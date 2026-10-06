<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Requests that someone approves or rejects (status, reviewer, time, note). */
trait HasReview
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', self::PENDING);
    }

    public function review(string $status, User $by, ?string $note = null): void
    {
        $this->forceFill([
            'status' => $status, 'reviewed_by' => $by->id, 'reviewed_at' => now(), 'review_note' => $note,
        ])->save();
    }
}
