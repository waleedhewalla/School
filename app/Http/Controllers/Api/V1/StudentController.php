<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Students\AdmitStudent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StudentRequest;
use App\Http\Resources\V1\StudentResource;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class StudentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'section_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $students = Student::query()
            ->with('currentEnrollment.gradeLevel', 'currentEnrollment.section')
            ->search($request->string('search')->toString())
            ->when($request->integer('section_id'), fn ($query, $id) => $query->inSection($id))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderBy('family_name_ar')->orderBy('first_name_ar')
            ->paginate($request->integer('per_page', 50));

        return StudentResource::collection($students);
    }

    public function store(StudentRequest $request, AdmitStudent $admit): StudentResource
    {
        $student = $admit->handle($request->validated());

        return new StudentResource($student->load('guardians', 'enrollments'));
    }

    public function show(Request $request, Student $student): StudentResource
    {
        Gate::authorize('view', $student);

        return new StudentResource($student->load('guardians', 'enrollments.gradeLevel', 'enrollments.section'));
    }

    public function update(StudentRequest $request, Student $student): StudentResource
    {
        $student->update($request->validated());

        return new StudentResource($student);
    }

    public function destroy(Student $student): Response
    {
        $student->delete();

        return response()->noContent();
    }
}
