<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['section_id', 'author_id', 'audience', 'title', 'body', 'send_sms', 'published_at'])]
class Announcement extends Model
{
    use BelongsToSchool;

    public const AUDIENCES = ['staff', 'guardians', 'everyone'];

    protected function casts(): array
    {
        return ['send_sms' => 'boolean', 'published_at' => 'datetime'];
    }

    /** @return BelongsTo<Section, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param  Builder<Announcement>  $query */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now())->latest('published_at');
    }

    /** @param  Builder<Announcement>  $query */
    public function scopeForStaff(Builder $query): void
    {
        $query->whereIn('audience', ['staff', 'everyone']);
    }

    /**
     * Guardian-facing announcements: school-wide ones, plus those for the
     * sections their children are in.
     *
     * @param  Builder<Announcement>  $query
     * @param  list<int>  $sectionIds
     */
    public function scopeForGuardianOf(Builder $query, array $sectionIds): void
    {
        $query->whereIn('audience', ['guardians', 'everyone'])
            ->where(fn (Builder $q) => $q->whereNull('section_id')->orWhereIn('section_id', $sectionIds));
    }
}
