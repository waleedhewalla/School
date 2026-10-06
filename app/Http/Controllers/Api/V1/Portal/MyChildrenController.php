<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AttendanceRecordResource;
use App\Http\Resources\V1\StudentResource;
use App\Models\AttendanceRecord;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Guardian portal: a guardian sees only the children linked to them. */
class MyChildrenController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return StudentResource::collection(
            $this->children($request)->with('currentEnrollment.gradeLevel', 'currentEnrollment.section')->get()
        );
    }

    public function attendance(Request $request, int $student): AnonymousResourceCollection
    {
        $child = $this->children($request)->findOrFail($student);
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return AttendanceRecordResource::collection(
            AttendanceRecord::query()
                ->with('code')
                ->where('student_id', $child->id)
                ->when($data['from'] ?? null, fn ($q, $from) => $q->where('date', '>=', $from))
                ->when($data['to'] ?? null, fn ($q, $to) => $q->where('date', '<=', $to))
                ->orderByDesc('date')->orderBy('period')
                ->limit(500)
                ->get()
        );
    }

    /** @return Builder<Student> */
    private function children(Request $request)
    {
        return Student::query()->whereHas('guardians', fn ($q) => $q->where('guardians.user_id', $request->user()->id));
    }
}
