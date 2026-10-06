<?php

use App\Enums\Permission;
use App\Http\Controllers\Web\AccountController;
use App\Http\Controllers\Web\AdmissionsController;
use App\Http\Controllers\Web\AdmissionWindowController;
use App\Http\Controllers\Web\AnnouncementController;
use App\Http\Controllers\Web\ApplyController;
use App\Http\Controllers\Web\AttendanceController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\EnrollmentController;
use App\Http\Controllers\Web\GradingSetupController;
use App\Http\Controllers\Web\GuardianLookupController;
use App\Http\Controllers\Web\InvitationController;
use App\Http\Controllers\Web\LoginController;
use App\Http\Controllers\Web\MarksController;
use App\Http\Controllers\Web\PasswordResetController;
use App\Http\Controllers\Web\PortalController;
use App\Http\Controllers\Web\PromotionController;
use App\Http\Controllers\Web\ResultsController;
use App\Http\Controllers\Web\SchoolSwitchController;
use App\Http\Controllers\Web\SettingsController;
use App\Http\Controllers\Web\StudentController;
use App\Http\Controllers\Web\StudentImportController;
use App\Http\Controllers\Web\TimetableController;
use App\Http\Controllers\Web\UserController;
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
    Route::get('/two-factor-challenge', [LoginController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('/two-factor-challenge', [LoginController::class, 'verifyChallenge'])->middleware('throttle:20,1');
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');
});

// Open to guests and signed-in users alike; the token is the credential.
Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
Route::post('/invitations/{token}', [InvitationController::class, 'accept'])->middleware('throttle:10,1')->name('invitations.accept');

// Public admissions: the form and the family's status page (the token is the credential).
Route::prefix('/apply/{school}')->middleware('public-school')->where(['school' => '[a-z0-9-]+'])->group(function () {
    Route::get('/', [ApplyController::class, 'create'])->name('apply.create');
    Route::post('/', [ApplyController::class, 'store'])->middleware('throttle:5,1')->name('apply.store');
    Route::get('/status/{token}', [ApplyController::class, 'status'])->middleware('throttle:60,1')->name('apply.status');
    Route::post('/status/{token}/documents', [ApplyController::class, 'upload'])->middleware('throttle:20,1')->name('apply.upload');
    Route::post('/status/{token}/respond', [ApplyController::class, 'respond'])->middleware('throttle:10,1')->name('apply.respond');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/schools', [SchoolSwitchController::class, 'index'])->name('schools.select');

    Route::post('/schools', [SchoolSwitchController::class, 'store'])->name('schools.switch');

    Route::middleware('school')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        // Inside the school context so the menu and permissions render normally.
        Route::get('/account', [AccountController::class, 'show'])->name('account');
        Route::put('/account', [AccountController::class, 'updateProfile'])->name('account.update');
        Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password');
        Route::post('/account/two-factor', [AccountController::class, 'enableTwoFactor'])->name('account.two-factor.enable');
        Route::post('/account/two-factor/confirm', [AccountController::class, 'confirmTwoFactor'])->name('account.two-factor.confirm');
        Route::delete('/account/two-factor', [AccountController::class, 'disableTwoFactor'])->name('account.two-factor.disable');

        Route::get('/students', [StudentController::class, 'index'])
            ->middleware('can:'.Permission::StudentsView)->name('students.index');

        Route::middleware('can:'.Permission::StudentsManage)->group(function () {
            Route::get('/students/create', [StudentController::class, 'create'])->name('students.create');
            Route::post('/students', [StudentController::class, 'store'])->name('students.store');
            Route::get('/students/import', [StudentImportController::class, 'create'])->name('students.import');
            Route::post('/students/import/preview', [StudentImportController::class, 'preview'])->name('students.import.preview');
            Route::post('/students/import', [StudentImportController::class, 'store'])->name('students.import.store');
            Route::delete('/students/import', [StudentImportController::class, 'destroy'])->name('students.import.destroy');
            Route::get('/students/import/template', [StudentImportController::class, 'template'])->name('students.import.template');
            Route::get('/students/{student}/edit', [StudentController::class, 'edit'])->whereNumber('student')->name('students.edit');
            Route::put('/students/{student}', [StudentController::class, 'update'])->whereNumber('student')->name('students.update');
            Route::patch('/enrollments/{enrollment}', [EnrollmentController::class, 'update'])->name('enrollments.update');
            Route::get('/guardians/lookup', GuardianLookupController::class)->name('guardians.lookup');
            Route::get('/promotions', [PromotionController::class, 'index'])->name('promotions.index');
            Route::post('/promotions', [PromotionController::class, 'store'])->name('promotions.store');
        });

        // Authorized by StudentPolicy (staff, the student's guardians, the student).
        Route::get('/students/{student}', [StudentController::class, 'show'])->whereNumber('student')->name('students.show');

        Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::post('/attendance/{section}', [AttendanceController::class, 'store'])->middleware('throttle:60,1')->name('attendance.store');

        Route::get('/my/children', [PortalController::class, 'children'])->name('portal.children');

        Route::get('/timetable', [TimetableController::class, 'index'])
            ->middleware('can:'.Permission::AcademicStructureView)->name('timetable.index');
        Route::post('/timetable/{section}', [TimetableController::class, 'place'])
            ->middleware('can:'.Permission::TimetableManage)->name('timetable.place');
        Route::get('/my/timetable', [TimetableController::class, 'mine'])->name('timetable.mine');

        Route::middleware('can:'.Permission::TimetableManage)->group(function () {
            Route::get('/settings/periods', [SettingsController::class, 'periods'])->name('settings.periods');
            Route::put('/settings/periods', [SettingsController::class, 'savePeriods'])->name('settings.periods.save');
        });
        Route::get('/marks', [MarksController::class, 'index'])->name('marks.index');
        Route::post('/marks', [MarksController::class, 'store'])->name('marks.store');
        Route::get('/results', [ResultsController::class, 'index'])->middleware('can:'.Permission::GradesView)->name('results.index');
        // Staff with grades.view, or a guardian for their own child once results are published.
        Route::get('/report-cards/{section}/{term}', [ResultsController::class, 'print'])->name('report-cards.print');
        Route::get('/report-cards/{section}/{term}/pdf', [ResultsController::class, 'pdf'])->name('report-cards.pdf');
        Route::post('/report-cards/{section}/{term}/comments', [ResultsController::class, 'comment'])->name('report-cards.comment');

        Route::get('/announcements', [AnnouncementController::class, 'index'])
            ->middleware('can:'.Permission::AcademicStructureView)->name('announcements.index');
        Route::middleware('can:'.Permission::AnnouncementsManage)->group(function () {
            Route::post('/announcements', [AnnouncementController::class, 'store'])->middleware('throttle:10,1')->name('announcements.store');
            Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');
        });

        Route::middleware('can:'.Permission::GradesManage)->group(function () {
            Route::get('/grading', [GradingSetupController::class, 'index'])->name('grading.setup');
            Route::post('/grading', [GradingSetupController::class, 'save'])->name('grading.save');
            Route::post('/grading/copy', [GradingSetupController::class, 'copy'])->name('grading.copy');
            Route::patch('/terms/{term}', [ResultsController::class, 'updateTerm'])->name('terms.update');
            Route::get('/settings/grading', [GradingSetupController::class, 'scale'])->name('settings.grading');
            Route::put('/settings/grading', [GradingSetupController::class, 'saveScale'])->name('settings.grading.save');
        });

        Route::middleware('can:'.Permission::MembersManage)->group(function () {
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::post('/users/invitations', [UserController::class, 'invite'])->name('users.invite');
            Route::delete('/users/invitations/{invitation}', [UserController::class, 'revoke'])->name('users.invitations.revoke');
            Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
        });

        Route::middleware('can:'.Permission::AdmissionsManage)->group(function () {
            Route::get('/admissions', [AdmissionsController::class, 'index'])->name('admissions.index');
            Route::get('/admissions/windows', [AdmissionWindowController::class, 'index'])->name('admissions.windows');
            Route::post('/admissions/windows', [AdmissionWindowController::class, 'store'])->name('admissions.windows.store');
            Route::put('/admissions/windows/{window}', [AdmissionWindowController::class, 'update'])->name('admissions.windows.update');
            Route::delete('/admissions/windows/{window}', [AdmissionWindowController::class, 'destroy'])->name('admissions.windows.destroy');
            Route::get('/admissions/{application}', [AdmissionsController::class, 'show'])->whereNumber('application')->name('admissions.show');
            Route::patch('/admissions/{application}', [AdmissionsController::class, 'update'])->whereNumber('application')->name('admissions.update');
            Route::post('/admissions/{application}/status', [AdmissionsController::class, 'transition'])->name('admissions.transition');
            Route::post('/admissions/{application}/enrol', [AdmissionsController::class, 'enrol'])->name('admissions.enrol');
            Route::patch('/admission-documents/{document}', [AdmissionsController::class, 'reviewDocument'])->name('admissions.documents.review');
            Route::get('/admission-documents/{document}', [AdmissionsController::class, 'document'])->name('admissions.documents.show');
        });

        Route::middleware('can:'.Permission::SchoolManage)->group(function () {
            Route::get('/settings/notifications', [SettingsController::class, 'notifications'])->name('settings.notifications');
            Route::put('/settings/notifications', [SettingsController::class, 'saveNotifications'])->name('settings.notifications.save');
        });
    });
});
