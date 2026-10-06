<?php

use App\Enums\Permission;
use App\Http\Controllers\Api\V1\AcademicYearController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuthTokenController;
use App\Http\Controllers\Api\V1\EnrollmentController;
use App\Http\Controllers\Api\V1\GradeLevelController;
use App\Http\Controllers\Api\V1\GuardianController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\Portal\FamilyController;
use App\Http\Controllers\Api\V1\Portal\MyChildrenController;
use App\Http\Controllers\Api\V1\SchoolController;
use App\Http\Controllers\Api\V1\SectionController;
use App\Http\Controllers\Api\V1\StaffMemberController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\SubjectController;
use App\Http\Controllers\Api\V1\TeachingAssignmentController;
use App\Http\Controllers\Api\V1\TimetableController;
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

            // Students, guardians and enrollment.
            $studentsView = 'can:'.Permission::StudentsView;
            $studentsManage = 'can:'.Permission::StudentsManage;

            Route::get('students', [StudentController::class, 'index'])->middleware($studentsView)->name('students.index');
            // show is authorized by StudentPolicy (staff, the student's guardians, the student).
            Route::get('students/{student}', [StudentController::class, 'show'])->name('students.show');
            Route::middleware($studentsManage)->group(function () {
                Route::apiResource('students', StudentController::class)->only(['store', 'update', 'destroy']);
                Route::post('students/{student}/guardians', [GuardianController::class, 'attach'])->name('students.guardians.attach');
                Route::delete('students/{student}/guardians/{guardian}', [GuardianController::class, 'detach'])->name('students.guardians.detach');
                Route::patch('guardians/{guardian}', [GuardianController::class, 'update'])->name('guardians.update');
                Route::post('guardians/{guardian}/user', [GuardianController::class, 'linkUser'])->name('guardians.user');
                Route::post('enrollments', [EnrollmentController::class, 'store'])->name('enrollments.store');
                Route::patch('enrollments/{enrollment}', [EnrollmentController::class, 'update'])->name('enrollments.update');
                Route::post('promotions', [EnrollmentController::class, 'promote'])->name('promotions.store');
            });
            // The guardian directory (IDs, phones) is for registrars and admins only.
            Route::middleware($studentsManage)->group(function () {
                Route::get('guardians', [GuardianController::class, 'index'])->name('guardians.index');
                Route::get('guardians/{guardian}', [GuardianController::class, 'show'])->name('guardians.show');
            });

            // Staff and who teaches what.
            Route::apiResource('staff-members', StaffMemberController::class)->only(['index', 'show'])
                ->middleware('can:'.Permission::StaffView);
            Route::middleware('can:'.Permission::StaffManage)->group(function () {
                Route::apiResource('staff-members', StaffMemberController::class)->only(['store', 'update', 'destroy']);
                Route::post('teaching-assignments', [TeachingAssignmentController::class, 'store'])->name('teaching-assignments.store');
                Route::delete('teaching-assignments/{teaching_assignment}', [TeachingAssignmentController::class, 'destroy'])->name('teaching-assignments.destroy');
            });
            Route::get('teaching-assignments', [TeachingAssignmentController::class, 'index'])->middleware($view)->name('teaching-assignments.index');

            // Attendance registers; authorized per section by SectionPolicy.
            Route::get('attendance-codes', [AttendanceController::class, 'codes'])->name('attendance-codes.index');
            Route::get('sections/{section}/attendance', [AttendanceController::class, 'show'])->name('sections.attendance.show');
            Route::put('sections/{section}/attendance', [AttendanceController::class, 'store'])->middleware('throttle:60,1')->name('sections.attendance.store');

            // Bell schedule and timetables.
            Route::get('periods', [TimetableController::class, 'periods'])->name('periods.index');
            Route::put('periods', [TimetableController::class, 'savePeriods'])->middleware('can:'.Permission::TimetableManage)->name('periods.save');
            Route::get('sections/{section}/timetable', [TimetableController::class, 'section'])->middleware($view)->name('sections.timetable');
            Route::put('sections/{section}/timetable', [TimetableController::class, 'place'])->middleware('can:'.Permission::TimetableManage)->name('sections.timetable.place');
            Route::delete('sections/{section}/timetable', [TimetableController::class, 'clear'])->middleware('can:'.Permission::TimetableManage)->name('sections.timetable.clear');
            Route::get('my/timetable', [TimetableController::class, 'mine'])->name('my.timetable');

            // Guardian portal.
            Route::get('my/children', [MyChildrenController::class, 'index'])->name('my.children.index');
            Route::get('my/children/{student}/attendance', [MyChildrenController::class, 'attendance'])->name('my.children.attendance');
            Route::get('my/children/{student}/homework', [FamilyController::class, 'homework'])->whereNumber('student')->name('my.children.homework');
            Route::get('my/children/{student}/behaviour', [FamilyController::class, 'behaviour'])->whereNumber('student')->name('my.children.behaviour');
            Route::get('my/children/{student}/results', [FamilyController::class, 'results'])->whereNumber('student')->name('my.children.results');
            Route::get('my/children/{student}/transport', [FamilyController::class, 'transport'])->whereNumber('student')->name('my.children.transport');
            Route::get('my/announcements', [FamilyController::class, 'announcements'])->name('my.announcements');
            Route::get('my/excuses', [FamilyController::class, 'excuses'])->name('my.excuses.index');
            Route::post('my/excuses', [FamilyController::class, 'storeExcuse'])->middleware('throttle:10,1')->name('my.excuses.store');
        });
    });
});
