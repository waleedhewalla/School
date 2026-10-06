<?php

namespace App\Actions\Timetable;

use App\Models\Period;
use App\Rules\ExistsInCurrentSchool;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves the bell schedule. Rows with an id are updated, rows without one
 * are added, and periods left out are deleted — unless lessons are
 * scheduled in them, which would silently wipe timetables.
 */
class SavePeriods
{
    /** @param  list<array{id?: int|null, name_ar: string, name_en?: string|null, starts_at: string, ends_at: string, is_break?: bool}>  $rows */
    public function handle(array $rows): void
    {
        foreach ($rows as $i => $row) {
            if ($row['ends_at'] <= $row['starts_at']) {
                throw ValidationException::withMessages(["periods.$i.ends_at" => __('timetable.ends_before_start')]);
            }
            if ($i > 0 && $row['starts_at'] < $rows[$i - 1]['ends_at']) {
                throw ValidationException::withMessages(["periods.$i.starts_at" => __('timetable.overlaps_previous')]);
            }
        }

        DB::transaction(function () use ($rows) {
            $keep = collect($rows)->pluck('id')->filter()->map(fn ($id) => (int) $id);
            $removed = Period::query()->whereNotIn('id', $keep)->withCount(['timetableEntries'])->get();

            if ($used = $removed->firstWhere('timetable_entries_count', '>', 0)) {
                throw ValidationException::withMessages(['periods' => __('timetable.period_in_use', ['period' => $used->name])]);
            }
            Period::query()->whereKey($removed->modelKeys())->delete();

            // Park sequences out of the way first so the unique (school, sequence) index never collides.
            Period::query()->update(['sequence' => DB::raw('sequence + 100')]);

            foreach ($rows as $i => $row) {
                $attributes = [
                    'sequence' => $i + 1,
                    'name_ar' => $row['name_ar'],
                    'name_en' => $row['name_en'] ?? null,
                    'starts_at' => $row['starts_at'],
                    'ends_at' => $row['ends_at'],
                    'is_break' => (bool) ($row['is_break'] ?? false),
                ];

                if (! empty($row['id'])) {
                    Period::query()->findOrFail($row['id'])->update($attributes);
                } else {
                    Period::query()->create($attributes);
                }
            }
        });
    }

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'periods' => ['required', 'array', 'min:1', 'max:14'],
            'periods.*.id' => ['nullable', 'integer', ExistsInCurrentSchool::inTable('periods')],
            'periods.*.name_ar' => ['required', 'string', 'max:50'],
            'periods.*.name_en' => ['nullable', 'string', 'max:50'],
            'periods.*.starts_at' => ['required', 'date_format:H:i'],
            'periods.*.ends_at' => ['required', 'date_format:H:i'],
            'periods.*.is_break' => ['sometimes', 'boolean'],
        ];
    }
}
