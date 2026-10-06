<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\StageResource;
use App\Models\Stage;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GradeLevelController extends Controller
{
    /** Stages with their grade levels, in order. */
    public function index(): AnonymousResourceCollection
    {
        return StageResource::collection(
            Stage::query()->with('gradeLevels')->orderBy('sequence')->get()
        );
    }
}
