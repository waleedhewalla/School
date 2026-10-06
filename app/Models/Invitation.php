<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Support\Tenancy\SchoolScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * An invitation to join a school. Only a hash of the token is stored; the
 * token itself is sent once by email.
 */
#[Fillable(['email', 'name', 'roles', 'token_hash', 'invited_by', 'expires_at', 'accepted_at'])]
class Invitation extends Model
{
    use BelongsToSchool;

    protected function casts(): array
    {
        return ['roles' => 'array', 'expires_at' => 'datetime', 'accepted_at' => 'datetime'];
    }

    /** @return array{0: Invitation, 1: string} the invitation and its plain token */
    public static function issue(array $attributes): array
    {
        $token = Str::random(48);
        $invitation = static::query()->create($attributes + [
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(7),
        ]);

        return [$invitation, $token];
    }

    /**
     * Looks up a pending invitation by token. The person accepting is not
     * signed in to any school yet, so this deliberately bypasses the school
     * scope; the token itself is the credential.
     */
    public static function findPending(string $token): ?self
    {
        return static::query()->withoutGlobalScope(SchoolScope::class)
            ->with('school')
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
