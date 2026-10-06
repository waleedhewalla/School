<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StaffMemberRequest;
use App\Http\Resources\V1\StaffMemberResource;
use App\Models\StaffMember;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class StaffMemberController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return StaffMemberResource::collection(StaffMember::query()->orderBy('name_ar')->paginate(100));
    }

    public function store(StaffMemberRequest $request): StaffMemberResource
    {
        return new StaffMemberResource(StaffMember::query()->create($request->validated()));
    }

    public function show(StaffMember $staffMember): StaffMemberResource
    {
        return new StaffMemberResource($staffMember);
    }

    public function update(StaffMemberRequest $request, StaffMember $staffMember): StaffMemberResource
    {
        $staffMember->update($request->validated());

        return new StaffMemberResource($staffMember);
    }

    public function destroy(StaffMember $staffMember): Response
    {
        $staffMember->delete();

        return response()->noContent();
    }
}
