<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SectionRequest;
use App\Http\Resources\V1\SectionResource;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SectionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['academic_year_id' => ['sometimes', 'integer']]);

        return SectionResource::collection(
            Section::query()
                ->with('gradeLevel')
                ->when($request->integer('academic_year_id'), fn ($query, $yearId) => $query->where('academic_year_id', $yearId))
                ->orderBy('grade_level_id')->orderBy('name')
                ->get()
        );
    }

    public function store(SectionRequest $request): SectionResource
    {
        return new SectionResource(Section::query()->create($request->validated())->load('gradeLevel'));
    }

    public function show(Section $section): SectionResource
    {
        return new SectionResource($section->load('gradeLevel'));
    }

    public function update(SectionRequest $request, Section $section): SectionResource
    {
        $section->update($request->validated());

        return new SectionResource($section->load('gradeLevel'));
    }

    public function destroy(Section $section): Response
    {
        $section->delete();

        return response()->noContent();
    }
}
