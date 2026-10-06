<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'locale'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /** @return HasMany<Membership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /** @return BelongsToMany<School, $this> */
    public function schools(): BelongsToMany
    {
        return $this->belongsToMany(School::class, 'memberships')->withPivot(['status', 'campus_id'])->withTimestamps();
    }

    /**
     * Platform staff can reach every school, so outside local development
     * that power requires two-factor sign-in.
     */
    public function platformAccessAllowed(): bool
    {
        return $this->is_platform_admin
            && (app()->environment('local', 'testing') || $this->two_factor_confirmed_at !== null);
    }

    /** Whether the user may act inside the given school. */
    public function canEnterSchool(School $school): bool
    {
        if (! $school->isActive()) {
            return false;
        }

        if ($this->is_platform_admin) {
            return $this->platformAccessAllowed();
        }

        return $this->memberships()
            ->where('school_id', $school->getKey())
            ->where('status', 'active')
            ->exists();
    }
}
