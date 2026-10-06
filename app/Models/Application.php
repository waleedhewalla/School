<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Enums\Gender;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * An admission application. Families follow it through a private link;
 * only a hash of the link's token is stored.
 */
#[Fillable([
    'admission_window_id', 'student_id', 'reference', 'token_hash', 'token', 'status',
    'first_name_ar', 'father_name_ar', 'grandfather_name_ar', 'family_name_ar', 'name_en', 'gender', 'date_of_birth',
    'national_id', 'nationality', 'current_school', 'from_private_school', 'has_sibling',
    'guardian_name', 'guardian_national_id', 'guardian_phone', 'guardian_email', 'guardian_relationship',
    'age_check', 'assessment_at', 'staff_note', 'noor_transfer_done', 'consented_at', 'submitted_at', 'decided_at',
])]
#[Hidden(['token', 'token_hash'])]
class Application extends Model
{
    use BelongsToSchool;

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'token' => 'encrypted',
            'gender' => Gender::class,
            'date_of_birth' => 'date',
            'from_private_school' => 'boolean',
            'has_sibling' => 'boolean',
            'noor_transfer_done' => 'boolean',
            'assessment_at' => 'datetime',
            'consented_at' => 'datetime',
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    protected function studentName(): Attribute
    {
        return Attribute::get(fn () => collect([$this->first_name_ar, $this->father_name_ar, $this->grandfather_name_ar, $this->family_name_ar])->filter()->implode(' '));
    }

    /** @return BelongsTo<AdmissionWindow, $this> */
    public function window(): BelongsTo
    {
        return $this->belongsTo(AdmissionWindow::class, 'admission_window_id');
    }

    /** @return HasMany<ApplicationDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    /** @return HasMany<ApplicationEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ApplicationEvent::class)->latest('id');
    }

    /** @param  Builder<Application>  $query  siblings first, then first come */
    public function scopeWaitlistOrder(Builder $query): void
    {
        $query->orderByDesc('has_sibling')->orderBy('submitted_at')->orderBy('id');
    }

    /** @return array{0: string, 1: string} a new reference and private-link token */
    public static function newIdentifiers(AcademicYear $year): array
    {
        $count = static::query()->whereHas('window', fn ($q) => $q->where('academic_year_id', $year->id))->count();

        return [sprintf('%s-%04d', preg_replace('/\D/', '', $year->name) ?: $year->id, $count + 1), Str::random(40)];
    }

    /** Link families use to follow the application. */
    public function statusUrl(): string
    {
        return route('apply.status', [$this->school->slug, $this->token]);
    }

    /** Finds an application by its private-link token inside the current school. */
    public static function findByToken(string $token): ?self
    {
        return static::query()->where('token_hash', hash('sha256', $token))->first();
    }
}
