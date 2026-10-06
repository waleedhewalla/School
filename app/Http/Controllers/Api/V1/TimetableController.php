<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Timetable\PlaceLesson;
use App\Actions\Timetable\SavePeriods;
use App\Http\Controllers\Controller;
use App\Models\Period;
use App\Models\Section;
use App\Models\TeachingAssignment;
use App\Models\TimetableEntry;
use App\Rules\ExistsInCurrentSchool;
use App\Support\Tenancy\CurrentSchool;
use App\Support\TimetableGrid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TimetableController extends Controller
{
    public function periods(): JsonResponse
    {
        return response()->json(['data' => TimetableGrid::build(TimetableEntry::query()->whereRaw('1 = 0'), [])['periods']]);
    }

    public function savePeriods(Request $request, SavePeriods $save): JsonResponse
    {
        $save->handle($request->validate(SavePeriods::rules())['periods']);

        return $this->periods();
    }

    public function section(Section $section, CurrentSchool $currentSchool): JsonResponse
    {
        return response()->json(['data' => TimetableGrid::build(
            TimetableEntry::query()->where('section_id', $section->id),
            $currentSchool->get()->schoolDays(),
        )]);
    }

    public function place(Request $request, Section $section, PlaceLesson $place): JsonResponse
    {
        $data = $request->validate([
            'day' => ['required', 'integer', 'between:0,6'],
            'period_id' => ['required', 'integer', ExistsInCurrentSchool::inTable('periods')],
            'teaching_assignment_id' => ['required', 'integer', ExistsInCurrentSchool::inTable('teaching_assignments')],
            'room' => ['nullable', 'string', 'max:30'],
        ]);

        $entry = $place->handle(
            $section,
            (int) $data['day'],
            Period::query()->findOrFail($data['period_id']),
            TeachingAssignment::query()->findOrFail($data['teaching_assignment_id']),
            $data['room'] ?? null,
        );

        return response()->json(['data' => ['id' => $entry->id]], 201);
    }

    public function clear(Request $request, Section $section): Response
    {
        $data = $request->validate([
            'day' => ['required', 'integer', 'between:0,6'],
            'period_id' => ['required', 'integer'],
        ]);

        TimetableEntry::query()->where('section_id', $section->id)
            ->where('day', $data['day'])->where('period_id', $data['period_id'])->delete();

        return response()->noContent();
    }

    /** The signed-in teacher's own week. */
    public function mine(Request $request, CurrentSchool $currentSchool): JsonResponse
    {
        return response()->json(['data' => TimetableGrid::build(
            TimetableEntry::query()->whereHas('staffMember', fn ($q) => $q->where('user_id', $request->user()->id)),
            $currentSchool->get()->schoolDays(),
            withSection: true,
        )]);
    }
}
