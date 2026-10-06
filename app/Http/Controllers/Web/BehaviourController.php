<?php

namespace App\Http\Controllers\Web;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Jobs\NotifyGuardianOfBehaviour;
use App\Models\AcademicYear;
use App\Models\BehaviourCategory;
use App\Models\BehaviourIncident;
use App\Models\Section;
use App\Models\Student;
use App\Rules\ExistsInCurrentSchool;
use App\Support\BehaviourScore;
use App\Support\Teaching;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Behaviour records: teachers record for students they teach, counsellors
 * and managers for anyone. Each category says whether the guardian is told.
 */
class BehaviourController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(Permission::BehaviourRecord);
        $user = $request->user();
        $manage = $user->can(Permission::BehaviourManage);
        $year = AcademicYear::query()->where('is_current', true)->first();

        $sections = Section::query()->with('gradeLevel')
            ->when($year, fn ($q) => $q->where('academic_year_id', $year->id))
            ->when(! $manage, fn ($q) => $q->whereIn('id', Teaching::sectionIdsOf($user)))
            ->orderBy('grade_level_id')->orderBy('name')->get();
        $sectionId = $request->integer('section_id') ?: null;
        $students = $sectionId && $sections->contains('id', $sectionId)
            ? Student::query()->inSection($sectionId)->orderBy('first_name_ar')->get() : collect();
        $scores = $year ? BehaviourScore::forStudents($students->pluck('id'), $year->id) : collect();

        return Inertia::render('Behaviour/Index', [
            'sections' => $sections->map(fn (Section $s) => ['id' => $s->id, 'label' => $s->gradeLevel->name.' / '.$s->name]),
            'sectionId' => $sectionId,
            'students' => $students->map(fn (Student $s) => ['id' => $s->id, 'name' => $s->name, 'score' => $scores[$s->id]['score'] ?? null]),
            'categories' => BehaviourCategory::query()->where('active', true)->orderBy('kind')->orderBy('sequence')->get()
                ->map(fn (BehaviourCategory $c) => $c->only(['id', 'kind', 'degree', 'points']) + ['name' => $c->name]),
            'incidents' => BehaviourIncident::query()->with('category', 'student', 'recorder')
                ->when($year, fn ($q) => $q->where('academic_year_id', $year->id))
                ->when(! $manage, fn ($q) => $q->where('recorded_by', $user->id))
                ->when($sectionId, fn ($q) => $q->whereIn('student_id', $students->pluck('id')))
                ->latest('occurred_on')->latest('id')->limit(50)->get()
                ->map(fn (BehaviourIncident $i) => self::present($i) + [
                    'can_delete' => $manage || ($i->recorded_by === $user->id && $i->created_at->gt(now()->subDay())),
                ]),
            'canManage' => $manage,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize(Permission::BehaviourRecord);
        $data = $request->validate([
            'student_ids' => ['required', 'array', 'min:1', 'max:60'],
            'student_ids.*' => ['integer', ExistsInCurrentSchool::in('students')],
            'behaviour_category_id' => ['required', 'integer', ExistsInCurrentSchool::inTable('behaviour_categories')],
            'occurred_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:2000'],
            'action_taken' => ['nullable', 'string', 'max:300'],
        ]);
        $user = $request->user();
        $year = AcademicYear::query()->where('is_current', true)->firstOrFail();
        $category = BehaviourCategory::query()->findOrFail($data['behaviour_category_id']);

        $students = Student::query()->whereKey($data['student_ids'])->get();
        if (! $user->can(Permission::BehaviourManage)) {
            foreach ($students as $student) {
                if (! Teaching::teachesStudent($user, $student)) {
                    throw ValidationException::withMessages(['student_ids' => __('behaviour.not_your_student')]);
                }
            }
        }

        foreach ($students as $student) {
            $incident = BehaviourIncident::query()->create([
                'academic_year_id' => $year->id, 'student_id' => $student->id, 'behaviour_category_id' => $category->id,
                'recorded_by' => $user->id, 'occurred_on' => $data['occurred_on'],
                'note' => $data['note'] ?? null, 'action_taken' => $data['action_taken'] ?? null,
            ]);
            if ($category->notify_guardian) {
                NotifyGuardianOfBehaviour::dispatch($incident->school_id, $incident->id)->afterCommit();
            }
        }

        return back()->with('success', __('behaviour.recorded'));
    }

    public function destroy(Request $request, BehaviourIncident $incident): RedirectResponse
    {
        $user = $request->user();
        $own = $incident->recorded_by === $user->id && $incident->created_at->gt(now()->subDay());
        if (! $user->can(Permission::BehaviourManage) && ! $own) {
            return back()->withErrors(['incident' => __('behaviour.too_late_to_delete')]);
        }
        $incident->delete();

        return back()->with('success', __('Deleted.'));
    }

    public function categories(): Response
    {
        return Inertia::render('Behaviour/Categories', [
            'categories' => BehaviourCategory::query()->withCount('incidents')->orderBy('kind')->orderBy('sequence')->get()
                ->map(fn (BehaviourCategory $c) => $c->only(['id', 'name_ar', 'name_en', 'kind', 'degree', 'points', 'notify_guardian', 'active', 'incidents_count'])),
            'startingScore' => (int) config('madrasa_behaviour.starting_score'),
        ]);
    }

    public function saveCategory(Request $request, ?BehaviourCategory $category = null): RedirectResponse
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:200'],
            'name_en' => ['nullable', 'string', 'max:200'],
            'kind' => ['required', Rule::in([BehaviourCategory::POSITIVE, BehaviourCategory::NEGATIVE])],
            'degree' => ['nullable', 'integer', 'between:1,6', 'prohibited_if:kind,positive'],
            'points' => ['required', 'integer', 'between:0,100'],
            'notify_guardian' => ['boolean'],
            'active' => ['boolean'],
        ]);
        $category
            ? $category->update($data)
            : BehaviourCategory::query()->create($data + ['sequence' => BehaviourCategory::query()->max('sequence') + 1]);

        return back()->with('success', __('Changes saved.'));
    }

    public function destroyCategory(BehaviourCategory $category): RedirectResponse
    {
        if ($category->incidents()->exists()) {
            return back()->withErrors(['category' => __('behaviour.category_in_use')]);
        }
        $category->delete();

        return back()->with('success', __('Deleted.'));
    }

    /** @return array<string, mixed> */
    public static function present(BehaviourIncident $i): array
    {
        return [
            'id' => $i->id,
            'student' => $i->student->name,
            'student_id' => $i->student_id,
            'category' => $i->category->name,
            'kind' => $i->category->kind,
            'points' => $i->category->signedPoints(),
            'degree' => $i->category->degree,
            'occurred_on' => $i->occurred_on->toDateString(),
            'note' => $i->note,
            'action_taken' => $i->action_taken,
            'by' => $i->recorder?->name,
        ];
    }
}
