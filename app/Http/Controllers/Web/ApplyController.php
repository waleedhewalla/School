<?php

namespace App\Http\Controllers\Web;

use App\Actions\Admissions\ChangeApplicationStatus;
use App\Actions\Admissions\SubmitApplication;
use App\Enums\ApplicationStatus;
use App\Enums\GuardianRelationship;
use App\Http\Controllers\Controller;
use App\Models\AdmissionWindow;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Rules\SaudiNationalId;
use App\Support\Admissions\DocumentChecklist;
use App\Support\PhoneNumber;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public side of admissions: the application form and the family's
 * status page, reached by the private link sent on submission.
 */
class ApplyController extends Controller
{
    public function create(CurrentSchool $currentSchool): Response
    {
        return Inertia::render('Apply/Form', [
            'schoolName' => $this->schoolName($currentSchool),
            'windows' => AdmissionWindow::query()->openToday()->with('gradeLevel', 'academicYear')
                ->orderBy('grade_level_id')->get()
                ->map(fn (AdmissionWindow $w) => [
                    'id' => $w->id,
                    'grade' => $w->gradeLevel->name,
                    'year' => $w->academicYear->name,
                    'closes_on' => $w->closes_on->toDateString(),
                    'born_from' => $w->born_from?->toDateString(),
                    'born_to' => $w->born_to?->toDateString(),
                    'full' => $w->seatsLeft() === 0,
                ]),
            'relationships' => array_column(GuardianRelationship::cases(), 'value'),
        ]);
    }

    public function store(Request $request, SubmitApplication $submit): RedirectResponse
    {
        $data = $request->validate([
            'admission_window_id' => ['required', 'integer'],
            'first_name_ar' => ['required', 'string', 'max:60'],
            'father_name_ar' => ['required', 'string', 'max:60'],
            'grandfather_name_ar' => ['nullable', 'string', 'max:60'],
            'family_name_ar' => ['required', 'string', 'max:60'],
            'name_en' => ['nullable', 'string', 'max:150'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'national_id' => ['nullable', new SaudiNationalId],
            'nationality' => ['required', 'string', 'size:2', 'alpha'],
            'current_school' => ['nullable', 'string', 'max:150'],
            'from_private_school' => ['boolean'],
            'guardian_name' => ['required', 'string', 'max:150'],
            'guardian_national_id' => ['nullable', new SaudiNationalId],
            'guardian_phone' => ['required', 'string', function ($attribute, $value, $fail) {
                if (PhoneNumber::normalize($value) === null) {
                    $fail(__('admissions.bad_phone'));
                }
            }],
            'guardian_email' => ['nullable', 'email', 'max:150'],
            'guardian_relationship' => ['required', Rule::enum(GuardianRelationship::class)],
            'consent' => ['accepted'],
        ]);

        // Only windows open today; anything else reads as "not found".
        $window = AdmissionWindow::query()->openToday()->find($data['admission_window_id']);
        if ($window === null) {
            throw ValidationException::withMessages(['admission_window_id' => __('admissions.window_closed')]);
        }

        unset($data['consent']);
        $data['nationality'] = strtoupper($data['nationality']);
        $data['from_private_school'] = (bool) ($data['from_private_school'] ?? false);

        [, $token] = $submit->handle($window, $data);

        return redirect()->route('apply.status', [app(CurrentSchool::class)->get()->slug, $token])
            ->with('success', __('admissions.submitted'));
    }

    public function status(string $token, CurrentSchool $currentSchool): Response
    {
        $application = $this->find($token);
        $required = DocumentChecklist::required($application);
        $documents = $application->documents()->get()->keyBy('type');

        return Inertia::render('Apply/Status', [
            'schoolName' => $this->schoolName($currentSchool),
            'token' => $token,
            'application' => [
                'reference' => $application->reference,
                'student' => $application->student_name,
                'grade' => $application->window->gradeLevel->name,
                'year' => $application->window->academicYear->name,
                'status' => $application->status->value,
                'submitted_at' => $application->submitted_at->toDateString(),
                'assessment_at' => $application->status === ApplicationStatus::AssessmentScheduled
                    ? $application->assessment_at?->setTimezone($currentSchool->get()->timezone)->format('Y-m-d H:i') : null,
                'can_accept' => $application->status === ApplicationStatus::Offered,
                'can_withdraw' => $application->status->canMoveTo(ApplicationStatus::Withdrawn),
                'can_upload' => $application->status->isOpen(),
            ],
            'documents' => collect($required)->map(fn (string $type) => [
                'type' => $type,
                'status' => $documents->get($type)?->status,
                'name' => $documents->get($type)?->original_name,
                'reason' => $documents->get($type)?->reason,
            ])->values(),
        ]);
    }

    public function upload(Request $request, string $token): RedirectResponse
    {
        $application = $this->find($token);
        abort_unless($application->status->isOpen(), 403);

        $data = $request->validate([
            'type' => ['required', Rule::in(DocumentChecklist::required($application))],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $existing = $application->documents()->where('type', $data['type'])->first();
        if ($existing?->status === ApplicationDocument::ACCEPTED) {
            return back()->withErrors(['file' => __('admissions.document_already_accepted')]);
        }

        $path = $data['file']->store("admissions/{$application->school_id}/{$application->id}", 'local');
        if ($existing) {
            Storage::disk('local')->delete($existing->path);
        }

        $application->documents()->updateOrCreate(['type' => $data['type']], [
            'path' => $path,
            'original_name' => mb_substr($data['file']->getClientOriginalName(), 0, 200),
            'status' => ApplicationDocument::PENDING,
            'reason' => null,
        ]);

        return back()->with('success', __('admissions.document_uploaded'));
    }

    public function respond(Request $request, string $token, ChangeApplicationStatus $change): RedirectResponse
    {
        $application = $this->find($token);
        $data = $request->validate(['action' => ['required', Rule::in(['accept', 'withdraw'])]]);

        $change->handle($application, $data['action'] === 'accept' ? ApplicationStatus::Accepted : ApplicationStatus::Withdrawn, note: __('admissions.by_family'));

        return back()->with('success', __($data['action'] === 'accept' ? 'admissions.offer_accepted' : 'admissions.withdrawn'));
    }

    private function find(string $token): Application
    {
        $application = strlen($token) === 40 ? Application::findByToken($token) : null;
        abort_if($application === null, 404);

        return $application->load('window.gradeLevel', 'window.academicYear');
    }

    private function schoolName(CurrentSchool $currentSchool): string
    {
        $school = $currentSchool->get();

        return app()->getLocale() === 'en' && $school->name_en ? $school->name_en : $school->name_ar;
    }
}
