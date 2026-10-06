<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Timetable\AssignTeacher;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\TeachingAssignmentResource;
use App\Models\Section;
use App\Models\TeachingAssignment;
use App\Rules\ExistsInCurrentSchool;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TeachingAssignmentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['section_id' => ['nullable', 'integer'], 'staff_member_id' => ['nullable', 'integer']]);

        return TeachingAssignmentResource::collection(
            TeachingAssignment::query()
                ->with('subject', 'staffMember')
                ->when($request->integer('section_id'), fn ($q, $id) => $q->where('section_id', $id))
                ->when($request->integer('staff_member_id'), fn ($q, $id) => $q->where('staff_member_id', $id))
                ->get()
        );
    }

    /** Assign (or reassign) the teacher of a subject in a section. */
    public function store(Request $request, AssignTeacher $assign): TeachingAssignmentResource
    {
        $data = $request->validate([
            'section_id' => ['required', 'integer', ExistsInCurrentSchool::in('sections')],
            'subject_id' => ['required', 'integer', ExistsInCurrentSchool::in('subjects')],
            'staff_member_id' => ['required', 'integer', ExistsInCurrentSchool::in('staff_members')],
            'is_homeroom' => ['sometimes', 'boolean'],
        ]);

        $assignment = $assign->handle(
            Section::query()->findOrFail($data['section_id']),
            (int) $data['subject_id'],
            (int) $data['staff_member_id'],
            (bool) ($data['is_homeroom'] ?? false),
        );

        return new TeachingAssignmentResource($assignment->load('subject', 'staffMember'));
    }

    public function destroy(TeachingAssignment $teachingAssignment): Response
    {
        $teachingAssignment->delete();

        return response()->noContent();
    }
}
