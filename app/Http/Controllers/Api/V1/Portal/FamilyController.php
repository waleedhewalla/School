<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\AnnouncementController;
use App\Http\Controllers\Web\HomeworkController;
use App\Models\AbsenceExcuse;
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\BehaviourIncident;
use App\Models\Homework;
use App\Models\Student;
use App\Models\Term;
use App\Support\BehaviourScore;
use App\Support\Grades\TermResults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Family endpoints for the mobile app. A guardian sees the children linked
 * to them; a student sees themself. Everything else answers 404.
 */
class FamilyController extends Controller
{
    public function homework(Request $request, int $student): JsonResponse
    {
        $child = $this->child($request, $student);
        $sectionId = $child->currentEnrollment?->section_id;

        return response()->json(['data' => $sectionId ? Homework::query()->with('subject', 'section.gradeLevel', 'author')
            ->where('section_id', $sectionId)->where('due_on', '>=', now()->subDays(14)->toDateString())
            ->orderBy('due_on')->get()->map(fn (Homework $h) => HomeworkController::present($h)) : []]);
    }

    public function behaviour(Request $request, int $student): JsonResponse
    {
        $child = $this->child($request, $student);
        $year = AcademicYear::query()->where('is_current', true)->first();

        return response()->json(['data' => [
            'score' => $year ? BehaviourScore::forStudents([$child->id], $year->id)[$child->id] : null,
            'records' => $year ? BehaviourIncident::query()->with('category')->where('student_id', $child->id)->where('academic_year_id', $year->id)
                ->latest('occurred_on')->limit(100)->get()->map(fn (BehaviourIncident $i) => [
                    'date' => $i->occurred_on->toDateString(), 'category' => $i->category->name,
                    'kind' => $i->category->kind, 'points' => $i->category->signedPoints(), 'note' => $i->note,
                ]) : [],
        ]]);
    }

    /** Published term results only. */
    public function results(Request $request, int $student): JsonResponse
    {
        $child = $this->child($request, $student);
        $section = $child->currentEnrollment?->section;
        $terms = Term::query()->whereNotNull('results_published_at')
            ->whereHas('academicYear', fn ($q) => $q->where('is_current', true))->orderBy('sequence')->get();

        return response()->json(['data' => $section ? $terms->map(function (Term $term) use ($child, $section) {
            $results = TermResults::forSection($section, $term, collect([$child]));
            $row = $results['students'][0] ?? null;

            return [
                'term' => $term->name, 'average' => $row['average'] ?? null, 'grade' => $row['average_grade'] ?? null,
                'subjects' => collect($results['subjects'])->map(fn ($s) => ['subject' => $s['name']] + ($row['subjects'][$s['id']] ?? [])),
                'report_card_url' => url("/report-cards/{$section->id}/{$term->id}?student_id={$child->id}"),
            ];
        })->values() : []]);
    }

    public function transport(Request $request, int $student): JsonResponse
    {
        $child = $this->child($request, $student)->load('transport.route.bus', 'transport.stop');
        $t = $child->transport;

        return response()->json(['data' => $t ? [
            'route' => $t->route->name, 'bus' => $t->route->bus?->number, 'stop' => $t->stop?->name,
            'pickup_at' => $t->stop?->pickup_at ? substr($t->stop->pickup_at, 0, 5) : null,
            'dropoff_at' => $t->stop?->dropoff_at ? substr($t->stop->dropoff_at, 0, 5) : null,
            'supervisor_phone' => $t->route->bus?->supervisor_phone,
        ] : null]);
    }

    public function announcements(Request $request): JsonResponse
    {
        $sections = $this->children($request)->with('currentEnrollment')->get()
            ->map(fn (Student $s) => $s->currentEnrollment?->section_id)->filter()->unique()->values()->all();

        return response()->json(['data' => Announcement::query()->with('section.gradeLevel', 'author')->published()
            ->forGuardianOf($sections)->limit(30)->get()->map(fn (Announcement $a) => AnnouncementController::present($a))]);
    }

    public function excuses(Request $request): JsonResponse
    {
        $ids = $this->children($request)->pluck('id');

        return response()->json(['data' => AbsenceExcuse::query()->with('student')->whereIn('student_id', $ids)->latest()->limit(50)->get()
            ->map(fn (AbsenceExcuse $e) => [
                'id' => $e->id, 'student_id' => $e->student_id, 'student' => $e->student->name,
                'from_date' => $e->from_date->toDateString(), 'to_date' => $e->to_date->toDateString(),
                'reason' => $e->reason, 'status' => $e->status, 'review_note' => $e->review_note,
            ])]);
    }

    public function storeExcuse(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer'],
            'from_date' => ['required', 'date', 'after_or_equal:'.now()->subDays(30)->toDateString()],
            'to_date' => ['required', 'date', 'after_or_equal:from_date', 'before_or_equal:'.now()->addDays(30)->toDateString()],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        abort_unless(Student::query()->guardedBy($request->user())->whereKey($data['student_id'])->exists(), 404);

        $excuse = AbsenceExcuse::query()->create($data + ['submitted_by' => $request->user()->id]);

        return response()->json(['data' => ['id' => $excuse->id, 'status' => $excuse->status]], 201);
    }

    private function child(Request $request, int $id): Student
    {
        return $this->children($request)->with('currentEnrollment.section.gradeLevel')->findOrFail($id);
    }

    /** @return Builder<Student> */
    private function children(Request $request): Builder
    {
        $user = $request->user();

        return Student::query()->where(fn ($q) => $q->guardedBy($user)->orWhere('user_id', $user->id));
    }
}
