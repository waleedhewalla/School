<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AcademicYearRequest;
use App\Http\Resources\V1\AcademicYearResource;
use App\Models\AcademicYear;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class AcademicYearController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return AcademicYearResource::collection(
            AcademicYear::query()->with('terms')->orderByDesc('starts_on')->get()
        );
    }

    public function store(AcademicYearRequest $request): AcademicYearResource
    {
        $year = DB::transaction(function () use ($request) {
            $year = AcademicYear::query()->create($request->safe()->except(['terms', 'is_current']));

            foreach ($request->validated('terms', []) as $index => $term) {
                $year->terms()->create($term + ['sequence' => $index + 1]);
            }

            if ($request->boolean('is_current')) {
                $year->makeCurrent();
            }

            return $year;
        });

        return new AcademicYearResource($year->load('terms'));
    }

    public function show(AcademicYear $academicYear): AcademicYearResource
    {
        return new AcademicYearResource($academicYear->load('terms'));
    }

    public function update(AcademicYearRequest $request, AcademicYear $academicYear): AcademicYearResource
    {
        $academicYear->update($request->safe()->except(['terms', 'is_current']));

        if ($request->boolean('is_current')) {
            $academicYear->makeCurrent();
        }

        return new AcademicYearResource($academicYear->load('terms'));
    }

    public function destroy(AcademicYear $academicYear): Response
    {
        $academicYear->delete();

        return response()->noContent();
    }
}
