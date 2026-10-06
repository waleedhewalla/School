<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SubjectRequest;
use App\Http\Resources\V1\SubjectResource;
use App\Models\Subject;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SubjectController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return SubjectResource::collection(Subject::query()->orderBy('code')->get());
    }

    public function store(SubjectRequest $request): SubjectResource
    {
        return new SubjectResource(Subject::query()->create($request->validated()));
    }

    public function show(Subject $subject): SubjectResource
    {
        return new SubjectResource($subject);
    }

    public function update(SubjectRequest $request, Subject $subject): SubjectResource
    {
        $subject->update($request->validated());

        return new SubjectResource($subject);
    }

    public function destroy(Subject $subject): Response
    {
        $subject->delete();

        return response()->noContent();
    }
}
