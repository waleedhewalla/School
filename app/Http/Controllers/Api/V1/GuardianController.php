<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\GuardianRelationship;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\GuardianResource;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use App\Rules\ExistsInCurrentSchool;
use App\Rules\SaudiNationalId;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class GuardianController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $term = $request->string('search')->toString();

        return GuardianResource::collection(
            Guardian::query()
                ->when($term !== '', fn ($query) => $query->where(fn ($q) => $q
                    ->where('national_id', $term)->orWhere('phone', $term)->orWhere('name_ar', 'like', "%{$term}%")))
                ->orderBy('name_ar')
                ->paginate(50)
        );
    }

    public function show(Guardian $guardian): GuardianResource
    {
        return new GuardianResource($guardian);
    }

    public function update(Request $request, Guardian $guardian): GuardianResource
    {
        $guardian->update($request->validate([
            'name_ar' => ['sometimes', 'string', 'max:150'],
            'name_en' => ['nullable', 'string', 'max:150'],
            'national_id' => ['nullable', 'string', new SaudiNationalId,
                Rule::unique('guardians')->where('school_id', app(CurrentSchool::class)->id())->ignore($guardian->id)],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9]{9,15}$/'],
            'email' => ['nullable', 'email'],
        ]));

        return new GuardianResource($guardian);
    }

    /** Link an existing guardian to a student. */
    public function attach(Request $request, Student $student): GuardianResource
    {
        $data = $request->validate([
            'guardian_id' => ['required', 'integer', ExistsInCurrentSchool::in('guardians')],
            'relationship' => ['required', Rule::enum(GuardianRelationship::class)],
            'is_primary' => ['sometimes', 'boolean'],
        ]);

        $student->guardians()->syncWithoutDetaching([$data['guardian_id'] => [
            'relationship' => $data['relationship'],
            'is_primary' => $data['is_primary'] ?? false,
        ]]);

        return new GuardianResource($student->guardians()->findOrFail($data['guardian_id']));
    }

    public function detach(Student $student, Guardian $guardian): Response
    {
        $student->guardians()->detach($guardian->id);

        return response()->noContent();
    }

    /**
     * Give a guardian a portal login by linking an existing user who is a
     * member of this school (invitations come with the web UI).
     */
    public function linkUser(Request $request, Guardian $guardian): GuardianResource
    {
        $schoolId = app(CurrentSchool::class)->id();
        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('memberships', 'user_id')->where('school_id', $schoolId)],
        ]);

        $guardian->user()->associate(User::query()->findOrFail($data['user_id']))->save();

        return new GuardianResource($guardian);
    }
}
