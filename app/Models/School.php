<?php

namespace App\Models;

use App\Enums\DateDisplay;
use App\Models\Concerns\HasBilingualName;
use Database\Factories\SchoolFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['slug', 'name_ar', 'name_en', 'country', 'timezone', 'default_locale', 'date_display', 'ministry_code', 'vat_number', 'status', 'school_days', 'notification_settings'])]
class School extends Model
{
    /** @use HasFactory<SchoolFactory> */
    use HasBilingualName, HasFactory, LogsActivity, SoftDeletes;

    /** Defaults mirrored from the migration so new instances know them before a reload. */
    protected $attributes = [
        'country' => 'SA',
        'timezone' => 'Asia/Riyadh',
        'default_locale' => 'ar',
        'date_display' => 'both',
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'date_display' => DateDisplay::class,
            'school_days' => 'array',
            'notification_settings' => 'array',
        ];
    }

    /** Teaching days, 0 = Sunday … 6 = Saturday. Saudi schools default to Sunday–Thursday. */
    public function schoolDays(): array
    {
        return $this->school_days ?: [0, 1, 2, 3, 4];
    }

    /**
     * Guardian notification settings merged over the defaults.
     *
     * @return array{channels: array{sms: bool, whatsapp: bool, email: bool}, quiet_hours: array{enabled: bool, start: string, end: string}}
     */
    public function notificationSettings(): array
    {
        return array_replace_recursive([
            'channels' => ['sms' => true, 'whatsapp' => false, 'email' => false],
            'quiet_hours' => ['enabled' => true, 'start' => '21:00', 'end' => '06:30'],
        ], $this->notification_settings ?? []);
    }

    /** @return HasMany<Campus, $this> */
    public function campuses(): HasMany
    {
        return $this->hasMany(Campus::class);
    }

    /** @return HasMany<Membership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'memberships')->withPivot(['status', 'campus_id'])->withTimestamps();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
