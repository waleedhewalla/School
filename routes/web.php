<?php

use App\Enums\Permission;
use App\Http\Controllers\Web\AccountController;
use App\Http\Controllers\Web\AdmissionsController;
use App\Http\Controllers\Web\AdmissionWindowController;
use App\Http\Controllers\Web\AnnouncementController;
use App\Http\Controllers\Web\ApplyController;
use App\Http\Controllers\Web\AttendanceController;
use App\Http\Controllers\Web\BehaviourController;
use App\Http\Controllers\Web\ClinicController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\EnrollmentController;
use App\Http\Controllers\Web\ExportController;
use App\Http\Controllers\Web\GradingSetupController;
use App\Http\Controllers\Web\GuardianLookupController;
use App\Http\Controllers\Web\HomeworkController;
use App\Http\Controllers\Web\InventoryController;
use App\Http\Controllers\Web\InvitationController;
use App\Http\Controllers\Web\LibraryController;
use App\Http\Controllers\Web\LoginController;
use App\Http\Controllers\Web\MarksController;
use App\Http\Controllers\Web\MyQuizController;
use App\Http\Controllers\Web\PasswordResetController;
use App\Http\Controllers\Web\PlatformController;
use App\Http\Controllers\Web\PortalController;
use App\Http\Controllers\Web\PortalInviteController;
use App\Http\Controllers\Web\PromotionController;
use App\Http\Controllers\Web\QuizController;
use App\Http\Controllers\Web\RequestsController;
use App\Http\Controllers\Web\ResultsController;
use App\Http\Controllers\Web\SchoolSwitchController;
use App\Http\Controllers\Web\SettingsController;
use App\Http\Controllers\Web\SetupController;
use App\Http\Controllers\Web\StaffController;
use App\Http\Controllers\Web\StudentController;
use App\Http\Controllers\Web\StudentImportController;
use App\Http\Controllers\Web\TimetableController;
use App\Http\Controllers\Web\TransportController;
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

    // SaaS operator console (platform admins with two-factor sign-in).
    Route::middleware('can:platform')->prefix('/platform')->group(function () {
        Route::get('/', [PlatformController::class, 'index'])->name('platform.index');
        Route::post('/schools', [PlatformController::class, 'store'])->name('platform.schools.store');
        Route::patch('/schools/{school}', [PlatformController::class, 'update'])->name('platform.schools.update');
        Route::post('/schools/{school}/enter', [PlatformController::class, 'enter'])->name('platform.schools.enter');
    });

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
            Route::post('/guardians/invite-all', [PortalInviteController::class, 'allGuardians'])->middleware('throttle:3,10')->name('guardians.invite-all');
            Route::post('/guardians/{guardian}/invite', [PortalInviteController::class, 'guardian'])->middleware('throttle:20,1')->name('guardians.invite');
            Route::post('/students/{student}/invite', [PortalInviteController::class, 'student'])->whereNumber('student')->middleware('throttle:20,1')->name('students.invite');
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

        // Each export checks its own permission (students, attendance or grades).
        Route::get('/exports', [ExportController::class, 'index'])->middleware('can:'.Permission::AcademicStructureView)->name('exports.index');
        Route::middleware('throttle:20,1')->group(function () {
            Route::get('/exports/students', [ExportController::class, 'students'])->name('exports.students');
            Route::get('/exports/attendance', [ExportController::class, 'attendance'])->name('exports.attendance');
            Route::get('/exports/results', [ExportController::class, 'results'])->name('exports.results');
        });

        // Homework: teachers for their subjects (checked in the controller), families read theirs.
        Route::get('/homework', [HomeworkController::class, 'index'])->name('homework.index');
        Route::post('/homework', [HomeworkController::class, 'store'])->middleware('throttle:30,1')->name('homework.store');
        Route::delete('/homework/{homework}', [HomeworkController::class, 'destroy'])->name('homework.destroy');
        Route::get('/homework/{homework}/attachment', [HomeworkController::class, 'attachment'])->name('homework.attachment');
        Route::get('/my/homework', [HomeworkController::class, 'mine'])->name('homework.mine');

        // Behaviour: teachers for students they teach, counsellors and managers for all.
        Route::get('/behaviour', [BehaviourController::class, 'index'])->name('behaviour.index');
        Route::post('/behaviour', [BehaviourController::class, 'store'])->middleware('throttle:60,1')->name('behaviour.store');
        Route::delete('/behaviour/{incident}', [BehaviourController::class, 'destroy'])->name('behaviour.destroy');
        Route::middleware('can:'.Permission::BehaviourManage)->group(function () {
            Route::get('/behaviour/categories', [BehaviourController::class, 'categories'])->name('behaviour.categories');
            Route::post('/behaviour/categories', [BehaviourController::class, 'saveCategory'])->name('behaviour.categories.store');
            Route::put('/behaviour/categories/{category}', [BehaviourController::class, 'saveCategory'])->name('behaviour.categories.update');
            Route::delete('/behaviour/categories/{category}', [BehaviourController::class, 'destroyCategory'])->name('behaviour.categories.destroy');
        });

        // Absence excuses (guardians ask, attendance managers decide) and staff leave.
        Route::get('/my/excuses', [RequestsController::class, 'myExcuses'])->name('excuses.mine');
        Route::post('/my/excuses', [RequestsController::class, 'storeExcuse'])->middleware('throttle:10,1')->name('excuses.store');
        Route::get('/excuses', [RequestsController::class, 'excuses'])->name('excuses.index');
        Route::post('/excuses/{excuse}/review', [RequestsController::class, 'reviewExcuse'])->name('excuses.review');
        Route::get('/excuses/{excuse}/attachment', [RequestsController::class, 'excuseAttachment'])->name('excuses.attachment');
        Route::get('/my/leave', [RequestsController::class, 'myLeave'])->name('leave.mine');
        Route::post('/my/leave', [RequestsController::class, 'storeLeave'])->middleware('throttle:10,1')->name('leave.store');
        Route::get('/leave', [RequestsController::class, 'leave'])->name('leave.index');
        Route::post('/leave/{leave}/review', [RequestsController::class, 'reviewLeave'])->name('leave.review');
        Route::get('/leave/{leave}/attachment', [RequestsController::class, 'leaveAttachment'])->name('leave.attachment');

        // Online quizzes: teachers for their classes; students take them, guardians see results.
        Route::get('/quizzes', [QuizController::class, 'index'])->name('quizzes.index');
        Route::post('/quizzes', [QuizController::class, 'store'])->middleware('can:'.Permission::HomeworkAssign)->name('quizzes.store');
        Route::get('/quizzes/{quiz}/edit', [QuizController::class, 'edit'])->name('quizzes.edit');
        Route::put('/quizzes/{quiz}', [QuizController::class, 'update'])->name('quizzes.update');
        Route::delete('/quizzes/{quiz}', [QuizController::class, 'destroy'])->name('quizzes.destroy');
        Route::post('/quizzes/{quiz}/questions', [QuizController::class, 'saveQuestion'])->name('quizzes.questions.store');
        Route::put('/quizzes/{quiz}/questions/{question}', [QuizController::class, 'saveQuestion'])->name('quizzes.questions.update');
        Route::delete('/quizzes/{quiz}/questions/{question}', [QuizController::class, 'destroyQuestion'])->name('quizzes.questions.destroy');
        Route::get('/quizzes/{quiz}/results', [QuizController::class, 'results'])->name('quizzes.results');
        Route::post('/quizzes/{quiz}/marks', [QuizController::class, 'sendToMarks'])->name('quizzes.marks');
        Route::get('/my/quizzes', [MyQuizController::class, 'index'])->name('quizzes.mine');
        Route::get('/my/quizzes/{quiz}', [MyQuizController::class, 'take'])->name('quizzes.take');
        Route::post('/my/quizzes/{quiz}', [MyQuizController::class, 'submit'])->middleware('throttle:20,1')->name('quizzes.submit');

        // School services.
        Route::middleware('can:'.Permission::ClinicManage)->group(function () {
            Route::get('/clinic', [ClinicController::class, 'index'])->name('clinic.index');
            Route::post('/clinic/visits', [ClinicController::class, 'storeVisit'])->name('clinic.visits.store');
            Route::get('/clinic/students/{student}', [ClinicController::class, 'student'])->name('clinic.student');
            Route::put('/clinic/students/{student}', [ClinicController::class, 'saveRecord'])->name('clinic.record');
        });
        Route::get('/my/health', [ClinicController::class, 'myHealth'])->name('health.mine');
        Route::put('/my/health/{student}', [ClinicController::class, 'saveMyHealth'])->middleware('throttle:20,1')->name('health.mine.save');

        Route::middleware('can:'.Permission::LibraryManage)->group(function () {
            Route::get('/library', [LibraryController::class, 'index'])->name('library.index');
            Route::post('/library/books', [LibraryController::class, 'storeBook'])->name('library.books.store');
            Route::put('/library/books/{book}', [LibraryController::class, 'updateBook'])->name('library.books.update');
            Route::delete('/library/books/{book}', [LibraryController::class, 'destroyBook'])->name('library.books.destroy');
            Route::post('/library/loans', [LibraryController::class, 'lend'])->name('library.loans.store');
            Route::post('/library/loans/{loan}/return', [LibraryController::class, 'return'])->name('library.loans.return');
        });

        Route::middleware('can:'.Permission::TransportManage)->group(function () {
            Route::get('/transport', [TransportController::class, 'index'])->name('transport.index');
            Route::post('/transport/buses', [TransportController::class, 'saveBus'])->name('transport.buses.store');
            Route::put('/transport/buses/{bus}', [TransportController::class, 'saveBus'])->name('transport.buses.update');
            Route::post('/transport/routes', [TransportController::class, 'saveRoute'])->name('transport.routes.store');
            Route::get('/transport/routes/{route}', [TransportController::class, 'route'])->name('transport.route');
            Route::put('/transport/routes/{route}', [TransportController::class, 'saveRoute'])->name('transport.routes.update');
            Route::delete('/transport/routes/{route}', [TransportController::class, 'destroyRoute'])->name('transport.routes.destroy');
            Route::post('/transport/routes/{route}/stops', [TransportController::class, 'saveStop'])->name('transport.stops.store');
            Route::put('/transport/routes/{route}/stops/{stop}', [TransportController::class, 'saveStop'])->name('transport.stops.update');
            Route::delete('/transport/routes/{route}/stops/{stop}', [TransportController::class, 'destroyStop'])->name('transport.stops.destroy');
            Route::post('/transport/routes/{route}/riders', [TransportController::class, 'assign'])->name('transport.riders.store');
            Route::delete('/transport/routes/{route}/riders/{rider}', [TransportController::class, 'unassign'])->name('transport.riders.destroy');
        });

        Route::middleware('can:'.Permission::InventoryManage)->group(function () {
            Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
            Route::get('/inventory/export', [InventoryController::class, 'export'])->name('inventory.export');
            Route::post('/inventory', [InventoryController::class, 'save'])->name('inventory.store');
            Route::put('/inventory/{item}', [InventoryController::class, 'save'])->name('inventory.update');
            Route::delete('/inventory/{item}', [InventoryController::class, 'destroy'])->name('inventory.destroy');
        });

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

        Route::middleware('can:'.Permission::AcademicStructureManage)->group(function () {
            Route::get('/setup/years', [SetupController::class, 'years'])->name('setup.years');
            Route::post('/setup/years', [SetupController::class, 'storeYear'])->name('setup.years.store');
            Route::put('/setup/years/{academic_year}', [SetupController::class, 'updateYear'])->name('setup.years.update');
            Route::delete('/setup/years/{academic_year}', [SetupController::class, 'destroyYear'])->name('setup.years.destroy');
            Route::post('/setup/years/{academic_year}/terms', [SetupController::class, 'storeTerm'])->name('setup.terms.store');
            Route::put('/setup/terms/{term}', [SetupController::class, 'updateTerm'])->name('setup.terms.update');
            Route::delete('/setup/terms/{term}', [SetupController::class, 'destroyTerm'])->name('setup.terms.destroy');
            Route::get('/setup/sections', [SetupController::class, 'sections'])->name('setup.sections');
            Route::post('/setup/sections', [SetupController::class, 'storeSection'])->name('setup.sections.store');
            Route::put('/setup/sections/{section}', [SetupController::class, 'updateSection'])->name('setup.sections.update');
            Route::delete('/setup/sections/{section}', [SetupController::class, 'destroySection'])->name('setup.sections.destroy');
            Route::get('/setup/subjects', [SetupController::class, 'subjects'])->name('setup.subjects');
            Route::post('/setup/subjects', [SetupController::class, 'storeSubject'])->name('setup.subjects.store');
            Route::put('/setup/subjects/{subject}', [SetupController::class, 'updateSubject'])->name('setup.subjects.update');
            Route::delete('/setup/subjects/{subject}', [SetupController::class, 'destroySubject'])->name('setup.subjects.destroy');
        });

        Route::middleware('can:'.Permission::StaffManage)->group(function () {
            Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
            Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
            Route::put('/staff/{staff_member}', [StaffController::class, 'update'])->name('staff.update');
            Route::get('/staff/assignments', [StaffController::class, 'assignments'])->name('staff.assignments');
            Route::post('/staff/assignments/{section}', [StaffController::class, 'assign'])->name('staff.assign');
        });

        Route::middleware('can:'.Permission::SchoolManage)->group(function () {
            Route::get('/settings/school', [SettingsController::class, 'school'])->name('settings.school');
            Route::put('/settings/school', [SettingsController::class, 'saveSchool'])->name('settings.school.save');
            Route::post('/settings/campuses', [SettingsController::class, 'saveCampus'])->name('settings.campuses.store');
            Route::put('/settings/campuses/{campus}', [SettingsController::class, 'saveCampus'])->name('settings.campuses.update');
            Route::get('/settings/notifications', [SettingsController::class, 'notifications'])->name('settings.notifications');
            Route::put('/settings/notifications', [SettingsController::class, 'saveNotifications'])->name('settings.notifications.save');
        });
    });
});
