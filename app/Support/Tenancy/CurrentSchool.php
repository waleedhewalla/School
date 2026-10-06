<?php

namespace App\Support\Tenancy;

use App\Models\School;
use Closure;

/**
 * Holds the school the current request (or job) is acting for.
 *
 * Every model using BelongsToSchool is filtered by this value, and the
 * Spatie permission "team" is kept in sync with it so role checks are
 * always evaluated inside the active school.
 */
class CurrentSchool
{
    private ?School $school = null;

    public function set(?School $school): void
    {
        $this->school = $school;

        setPermissionsTeamId($school?->getKey());
    }

    public function get(): ?School
    {
        return $this->school;
    }

    public function id(): ?int
    {
        return $this->school?->getKey();
    }

    public function check(): bool
    {
        return $this->school !== null;
    }

    public function forget(): void
    {
        $this->set(null);
    }

    /**
     * Run a callback inside a school's context, restoring the previous one
     * afterwards. Use it in seeders, jobs and console commands.
     *
     * @template T
     *
     * @param  Closure(School): T  $callback
     * @return T
     */
    public function run(School $school, Closure $callback): mixed
    {
        $previous = $this->school;
        $this->set($school);

        try {
            return $callback($school);
        } finally {
            $this->set($previous);
        }
    }
}
