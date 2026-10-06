<?php

namespace App\Http\Controllers\Web;

use App\Actions\Admissions\ChangeApplicationStatus;
use App\Actions\Admissions\EnrolApplicant;
use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\AdmissionWindow;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ApplicationEvent;
use App\Models\Section;
use App\Models\Student;
use App\Rules\ExistsInCurrentSchool;
use App\Support\Admissions\DocumentChecklist;
use App\Support\GuardianMatcher;
use App\Support\Tenancy\CurrentSchool;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Staff side of admissions: the pipeline, one application, documents, enrolment. */
class AdmissionsController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(ApplicationStatus::class)],
            'window' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:60'],
        ]);

        $base = Application::query()->when($filters['window'] ?? null, fn ($q, $id) => $q->where('admission_window_id', $id));
        $status = isset($filters['status']) ? ApplicationStatus::from($filters['status']) : null;

        $applications = (clone $base)->with('window.gradeLevel')
            ->when($status, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('reference', 'like', "%{$term}%")
                ->orWhere('national_id', $term)
                ->orWhere('first_name_ar', 'like', "%{$term}%")
                ->orWhere('family_name_ar', 'like', "%{$term}%")))
            // The waitlist is shown in the order seats are offered.
            ->when($status === ApplicationStatus::Waitlisted, fn ($q) => $q->waitlistOrder(), fn ($q) => $q->latest('submitted_at')->latest('id'))
            ->paginate(30)->withQueryString()
            ->through(fn (Application $a) => [
                'id' => $a->id,
                'reference' => $a->reference,
                'student' => $a->student_name,
                'grade' => $a->window->gradeLevel->name,
                'status' => $a->status->value,
                'age_check' => $a->age_check,
                'has_sibling' => $a->has_sibling,
                'submitted_at' => $a->submitted_at->toDateString(),
            ]);

        return Inertia::render('Admissions/Index', [
            'applications' => $applications,
            'counts' => (clone $base)->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'windows' => AdmissionWindow::query()->with('gradeLevel', 'academicYear')->orderByDesc('academic_year_id')->orderBy('grade_level_id')->get()
                ->map(fn (AdmissionWindow $w) => ['id' => $w->id, 'label' => $w->gradeLevel->name.' — '.$w->academicYear->name, 'seats' => $w->seats, 'seats_left' => $w->seatsLeft()]),
            'statuses' => array_column(ApplicationStatus::cases(), 'value'),
            'filters' => $filters,
        ]);
    }

    public function show(Application $application, CurrentSchool $currentSchool): Response
    {
        $application->load('window.gradeLevel', 'window.academicYear', 'documents', 'events.user');
        $window = $application->window;
        $documents = $application->documents->keyBy('type');
        $timezone = $currentSchool->get()->timezone;

        return Inertia::render('Admissions/Show', [
            'application' => [
                ...$application->only([
                    'id', 'reference', 'first_name_ar', 'father_name_ar', 'grandfather_name_ar', 'family_name_ar', 'name_en',
                    'national_id', 'nationality', 'current_school', 'from_private_school', 'has_sibling',
                    'guardian_name', 'guardian_national_id', 'guardian_phone', 'guardian_email', 'guardian_relationship',
                    'age_check', 'staff_note', 'noor_transfer_done', 'student_id',
                ]),
                'student' => $application->student_name,
                'gender' => $application->gender->value,
                'date_of_birth' => $application->date_of_birth->toDateString(),
                'status' => $application->status->value,
                'next' => array_map(fn (ApplicationStatus $s) => $s->value, $application->status->next()),
                'grade' => $window->gradeLevel->name,
                'year' => $window->academicYear->name,
                'born_from' => $window->born_from?->toDateString(),
                'born_to' => $window->born_to?->toDateString(),
                'seats_left' => $window->seatsLeft(),
                'submitted_at' => $application->submitted_at->toDateString(),
                'assessment_at' => $application->assessment_at?->setTimezone($timezone)->format('Y-m-d\TH:i'),
            ],
            'documents' => collect(DocumentChecklist::required($application))
                ->merge($documents->keys())->unique()->values()
                ->map(fn (string $type) => [
                    'type' => $type,
                    'id' => $documents->get($type)?->id,
                    'name' => $documents->get($type)?->original_name,
                    'status' => $documents->get($type)?->status,
                    'reason' => $documents->get($type)?->reason,
                ]),
            'events' => $application->events->map(fn (ApplicationEvent $e) => [
                'from' => $e->from_status?->value,
                'to' => $e->to_status->value,
                'note' => $e->note,
                'by' => $e->user?->name,
                'at' => $e->created_at->setTimezone($timezone)->format('Y-m-d H:i'),
            ]),
            'duplicates' => $this->duplicates($application),
            'family' => ($g = GuardianMatcher::findStrict($application->guardian_national_id, $application->guardian_phone))
                ? ['guardian' => $g->name_ar, 'children' => $g->students()->get()->map(fn (Student $s) => $s->name)->all()] : null,
            'sections' => Section::query()
                ->where('academic_year_id', $window->academic_year_id)->where('grade_level_id', $window->grade_level_id)
                ->orderBy('name')->get(['id', 'name', 'capacity']),
        ]);
    }

    public function transition(Request $request, Application $application, ChangeApplicationStatus $change, CurrentSchool $currentSchool): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(ApplicationStatus::class), Rule::notIn([ApplicationStatus::Enrolled->value])],
            'note' => ['nullable', 'string', 'max:1000'],
            'assessment_at' => ['nullable', 'date_format:Y-m-d\TH:i', 'required_if:status,assessment_scheduled'],
        ]);

        $change->handle(
            $application,
            ApplicationStatus::from($data['status']),
            $request->user(),
            $data['note'] ?? null,
            isset($data['assessment_at']) ? CarbonImmutable::parse($data['assessment_at'], $currentSchool->get()->timezone)->utc() : null,
        );

        return back()->with('success', __('admissions.status_changed'));
    }

    public function update(Request $request, Application $application): RedirectResponse
    {
        $application->update($request->validate([
            'staff_note' => ['nullable', 'string', 'max:5000'],
            'noor_transfer_done' => ['boolean'],
        ]));

        return back()->with('success', __('Saved.'));
    }

    public function reviewDocument(Request $request, ApplicationDocument $document): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([ApplicationDocument::ACCEPTED, ApplicationDocument::REJECTED])],
            'reason' => ['nullable', 'string', 'max:200', 'required_if:status,rejected'],
        ]);
        $document->update(['status' => $data['status'], 'reason' => $data['status'] === ApplicationDocument::REJECTED ? $data['reason'] : null]);

        return back()->with('success', __('Saved.'));
    }

    public function document(ApplicationDocument $document): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    public function enrol(Request $request, Application $application, EnrolApplicant $enrol): RedirectResponse
    {
        $data = $request->validate([
            'section_id' => ['nullable', 'integer', ExistsInCurrentSchool::in('sections')],
        ]);

        $student = $enrol->handle($application, isset($data['section_id']) ? Section::query()->find($data['section_id']) : null, $request->user());

        return redirect()->route('students.show', $student)->with('success', __('admissions.enrolled'));
    }

    /** @return list<array{kind: string, label: string, url: string|null}> the same child already known to the school */
    private function duplicates(Application $application): array
    {
        if (blank($application->national_id)) {
            return [];
        }

        $students = Student::query()->where('national_id', $application->national_id)
            ->whereKeyNot($application->student_id ?? 0)->get()
            ->map(fn (Student $s) => ['kind' => 'student', 'label' => $s->name, 'url' => route('students.show', $s)]);
        $applications = Application::query()->where('national_id', $application->national_id)->whereKeyNot($application->id)->get()
            ->map(fn (Application $a) => ['kind' => 'application', 'label' => $a->reference.' ('.__('admissions.status.'.$a->status->value).')', 'url' => route('admissions.show', $a)]);

        return [...$students->all(), ...$applications->all()];
    }
}
