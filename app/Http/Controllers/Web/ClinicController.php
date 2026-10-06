<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\NotifyStudentGuardians;
use App\Models\ClinicVisit;
use App\Models\HealthRecord;
use App\Models\Student;
use App\Rules\ExistsInCurrentSchool;
use App\Support\Tenancy\CurrentSchool;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * School clinic: students' health cards and visits to the nurse. Health
 * data is sensitive, so only clinic staff see it; families see and update
 * their own child's card from the portal.
 */
class ClinicController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        return Inertia::render('Clinic/Index', [
            'visits' => ClinicVisit::query()->with('student.currentEnrollment.section.gradeLevel', 'recorder')
                ->where('visited_at', '>=', now()->subDays(14))->latest('visited_at')->limit(100)->get()
                ->map(fn (ClinicVisit $v) => self::presentVisit($v)),
            'alerts' => HealthRecord::query()->with('student')
                ->where(fn ($q) => $q->whereNotNull('allergies')->orWhereNotNull('chronic_conditions')->orWhereNotNull('medications'))
                ->get()->filter->hasAlerts()
                ->map(fn (HealthRecord $h) => ['student_id' => $h->student_id, 'student' => $h->student->name,
                    'summary' => collect([$h->allergies, $h->chronic_conditions, $h->medications])->filter()->implode(' · ')])->values(),
            'results' => $search === '' ? [] : Student::query()->search($search)->limit(10)->get()
                ->map(fn (Student $s) => ['id' => $s->id, 'name' => $s->name, 'number' => $s->student_number]),
            'search' => $search,
            'outcomes' => ClinicVisit::OUTCOMES,
            'today' => ClinicVisit::query()->whereDate('visited_at', today())->count(),
        ]);
    }

    public function student(Student $student): Response
    {
        $record = HealthRecord::query()->firstOrNew(['student_id' => $student->id]);

        return Inertia::render('Clinic/Student', [
            'student' => ['id' => $student->id, 'name' => $student->name, 'gender' => $student->gender->value],
            'record' => self::presentRecord($record),
            'visits' => ClinicVisit::query()->with('recorder')->where('student_id', $student->id)->latest('visited_at')->limit(50)->get()
                ->map(fn (ClinicVisit $v) => self::presentVisit($v)),
            'outcomes' => ClinicVisit::OUTCOMES,
        ]);
    }

    public function saveRecord(Request $request, Student $student): RedirectResponse
    {
        HealthRecord::query()->updateOrCreate(['student_id' => $student->id], self::recordRules($request) + ['updated_by' => $request->user()->id]);

        return back()->with('success', __('Changes saved.'));
    }

    public function storeVisit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', ExistsInCurrentSchool::in('students')],
            'complaint' => ['required', 'string', 'max:300'],
            'temperature' => ['nullable', 'numeric', 'between:34,43'],
            'outcome' => ['required', Rule::in(ClinicVisit::OUTCOMES)],
            'treatment' => ['nullable', 'string', 'max:2000'],
        ]);
        $visit = ClinicVisit::query()->create($data + ['visited_at' => now(), 'recorded_by' => $request->user()->id]);

        if (in_array($visit->outcome, ClinicVisit::NOTIFY, true)) {
            $visit->forceFill(['guardian_notified_at' => now()])->save();
            NotifyStudentGuardians::dispatch($visit->school_id, $visit->student_id, 'clinic', 'clinic.sms_'.$visit->outcome, 'clinic.subject', [
                'time' => CarbonImmutable::now(app(CurrentSchool::class)->get()->timezone)->format('H:i'),
            ]);
        }

        return back()->with('success', __('clinic.visit_saved'));
    }

    // --- Families -------------------------------------------------------

    public function myHealth(Request $request): Response
    {
        $children = Student::query()->guardedBy($request->user())->get();

        return Inertia::render('Clinic/MyHealth', [
            'children' => $children->map(fn (Student $s) => [
                'id' => $s->id, 'name' => $s->name,
                'record' => self::presentRecord(HealthRecord::query()->firstOrNew(['student_id' => $s->id])),
                'visits' => ClinicVisit::query()->where('student_id', $s->id)->latest('visited_at')->limit(5)->get()
                    ->map(fn (ClinicVisit $v) => ['visited_at' => $v->visited_at->toDateString(), 'complaint' => $v->complaint, 'outcome' => $v->outcome]),
            ]),
        ]);
    }

    public function saveMyHealth(Request $request, Student $student): RedirectResponse
    {
        abort_unless(Student::query()->guardedBy($request->user())->whereKey($student->id)->exists(), 403);
        HealthRecord::query()->updateOrCreate(['student_id' => $student->id], self::recordRules($request) + ['updated_by' => $request->user()->id]);

        return back()->with('success', __('Changes saved.'));
    }

    /** @return array<string, mixed> */
    private static function recordRules(Request $request): array
    {
        return $request->validate([
            'blood_type' => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])],
            'allergies' => ['nullable', 'string', 'max:1000'],
            'chronic_conditions' => ['nullable', 'string', 'max:1000'],
            'medications' => ['nullable', 'string', 'max:1000'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['nullable', 'string', 'regex:/^\+?[0-9]{9,15}$/'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    /** @return array<string, mixed> */
    private static function presentRecord(HealthRecord $r): array
    {
        return $r->only(['blood_type', 'allergies', 'chronic_conditions', 'medications', 'emergency_contact_name', 'emergency_contact_phone', 'notes'])
            + ['updated_at' => $r->updated_at?->toDateString()];
    }

    /** @return array<string, mixed> */
    private static function presentVisit(ClinicVisit $v): array
    {
        $enrollment = $v->student?->relationLoaded('currentEnrollment') ? $v->student->currentEnrollment : null;

        return [
            'id' => $v->id,
            'student_id' => $v->student_id,
            'student' => $v->relationLoaded('student') ? $v->student->name : null,
            'class' => $enrollment?->section ? $enrollment->section->gradeLevel->name.' / '.$enrollment->section->name : null,
            'visited_at' => $v->visited_at->toIso8601String(),
            'complaint' => $v->complaint,
            'temperature' => $v->temperature,
            'outcome' => $v->outcome,
            'treatment' => $v->treatment,
            'by' => $v->recorder?->name,
            'notified' => $v->guardian_notified_at !== null,
        ];
    }
}
