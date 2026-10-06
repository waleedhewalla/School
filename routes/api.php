<?php

use App\Enums\Permission;
use App\Http\Controllers\Api\V1\AcademicYearController;
use App\Http\Controllers\Api\V1\AuthTokenController;
use App\Http\Controllers\Api\V1\GradeLevelController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\SchoolController;
use App\Http\Controllers\Api\V1\SectionController;
use App\Http\Controllers\Api\V1\SubjectController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->middleware('locale')->group(function () {
    Route::post('auth/token', [AuthTokenController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('auth.token.store');

    Route::middleware('auth:sanctum')->group(function () {
        Route::delete('auth/token', [AuthTokenController::class, 'destroy'])->name('auth.token.destroy');
        Route::get('me', MeController::class)->name('me');
        Route::post('schools', [SchoolController::class, 'store'])->name('schools.store');

        // Everything below acts inside one school (X-School header).
        Route::middleware('school')->group(function () {
            Route::get('school', [SchoolController::class, 'current'])->name('school.current');

            $view = 'can:'.Permission::AcademicStructureView;
            $manage = 'can:'.Permission::AcademicStructureManage;

            Route::get('grade-levels', [GradeLevelController::class, 'index'])->middleware($view)->name('grade-levels.index');

            foreach ([
                'academic-years' => AcademicYearController::class,
                'sections' => SectionController::class,
                'subjects' => SubjectController::class,
            ] as $uri => $controller) {
                Route::apiResource($uri, $controller)->only(['index', 'show'])->middleware($view);
                Route::apiResource($uri, $controller)->only(['store', 'update', 'destroy'])->middleware($manage);
            }
        });
    });
});
