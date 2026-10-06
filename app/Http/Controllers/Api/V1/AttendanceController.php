<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Attendance\RecordAttendance;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AttendanceCodeResource;
use App\Http\Resources\V1\AttendanceRecordResource;
use App\Models\AttendanceCode;
use App\Models\AttendanceRecord;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class AttendanceController extends Controller
{
    public function codes(): AnonymousResourceCollection
    {
        return AttendanceCodeResource::collection(AttendanceCode::query()->orderBy('sequence')->get());
    }

    /** The register for a section: every enrolled student with their mark, if any. */
    public function show(Request $request, Section $section): JsonResponse
    {
        Gate::authorize('viewAttendance', $section);
        [$date, $period] = $this->registerKey($request);

        $records = AttendanceRecord::query()
            ->with('code')
            ->where('date', $date->toDateString())
            ->where('period', $period)
            ->whereIn('student_id', Student::query()->inSection($section->id)->select('id'))
            ->get()
            ->keyBy('student_id');

        $students = Student::query()->inSection($section->id)->orderBy('family_name_ar')->orderBy('first_name_ar')->get();

        return response()->json(['data' => [
            'section_id' => $section->id,
            'date' => $date->toDateString(),
            'period' => $period,
            'taken' => $records->isNotEmpty(),
            'students' => $students->map(fn (Student $student) => [
                'student_id' => $student->id,
                'student_number' => $student->student_number,
                'name' => $student->name,
                'code' => $records->get($student->id)?->code->code,
                'note' => $records->get($student->id)?->note,
            ])->values(),
        ]]);
    }

    public function store(Request $request, Section $section, RecordAttendance $record): AnonymousResourceCollection
    {
        Gate::authorize('recordAttendance', $section);
        [$date, $period] = $this->registerKey($request);

        $data = $request->validate([
            'records' => ['required', 'array', 'min:1', 'max:200'],
            'records.*.student_id' => ['required', 'integer', 'distinct'],
            'records.*.code' => ['required', 'string', 'max:5'],
            'records.*.note' => ['nullable', 'string', 'max:255'],
        ]);

        return AttendanceRecordResource::collection(
            $record->handle($section, $date, $period, $data['records'], $request->user())
        );
    }

    /** @return array{0: Carbon, 1: int} */
    private function registerKey(Request $request): array
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'period' => ['sometimes', 'integer', 'min:0', 'max:20'],
        ]);

        return [Carbon::parse($data['date']), (int) ($data['period'] ?? AttendanceRecord::DAILY)];
    }
}
