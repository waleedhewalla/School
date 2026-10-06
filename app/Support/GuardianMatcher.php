<?php

namespace App\Support;

use App\Models\Guardian;

/** Finds a guardian already in the school by national ID or mobile, so siblings share a family. */
class GuardianMatcher
{
    public static function find(?string $nationalId, ?string $phone): ?Guardian
    {
        $phone = PhoneNumber::normalize($phone);
        if ($nationalId === null && $phone === null) {
            return null;
        }

        return Guardian::query()
            ->where(fn ($q) => $q
                ->when($nationalId, fn ($q, $id) => $q->orWhere('national_id', $id))
                ->when($phone, fn ($q, $p) => $q->orWhere('phone', $p)))
            ->first();
    }

    /**
     * For self-service forms: the mobile must match, and when both sides
     * have a national ID it must match too. One value typed by a stranger
     * is not enough to join another family.
     */
    public static function findStrict(?string $nationalId, ?string $phone): ?Guardian
    {
        $phone = PhoneNumber::normalize($phone);
        if ($phone === null) {
            return null;
        }

        return Guardian::query()->where('phone', $phone)
            ->get()
            ->first(fn (Guardian $g) => blank($nationalId) || blank($g->national_id) || $g->national_id === $nationalId);
    }
}
