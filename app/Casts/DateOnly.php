<?php

namespace App\Casts;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A calendar date stored as plain "Y-m-d" on every database. Laravel's
 * "date" cast writes "Y-m-d 00:00:00", which SQLite then compares as text,
 * so range checks on the first or last day go wrong there.
 */
class DateOnly implements CastsAttributes, SerializesCastableAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        return $value === null ? null : Carbon::parse(substr((string) $value, 0, 10))->startOfDay();
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return ($value instanceof DateTimeInterface ? CarbonImmutable::instance($value) : CarbonImmutable::parse($value))->toDateString();
    }

    public function serialize(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value?->toDateString();
    }
}
