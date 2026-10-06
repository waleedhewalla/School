<?php

use App\Enums\Permission;
use App\Http\Controllers\Web\AttendanceController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\LoginController;
use App\Http\Controllers\Web\PortalController;
use App\Http\Controllers\Web\SchoolSwitchController;
use App\Http\Controllers\Web\StudentController;
use App\Support\Locale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/locale/{locale}', function (Request $request, string $locale) {
    abort_unless(Locale::isSupported($locale), 404);

    $request->session()->put('locale', $locale);
    $request->user()?->update(['locale' => $locale]);

    return redirect()->back(fallback: route('home'));
})->name('locale.switch');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:20,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/schools', [SchoolSwitchController::class, 'index'])->name('schools.select');
    Route::post('/schools', [SchoolSwitchController::class, 'store'])->name('schools.switch');

    Route::middleware('school')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::get('/students', [StudentController::class, 'index'])
            ->middleware('can:'.Permission::StudentsView)->name('students.index');
        Route::get('/students/{student}', [StudentController::class, 'show'])->name('students.show');

        Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::post('/attendance/{section}', [AttendanceController::class, 'store'])->name('attendance.store');

        Route::get('/my/children', [PortalController::class, 'children'])->name('portal.children');
    });
});
