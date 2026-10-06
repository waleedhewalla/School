<?php

namespace App\Http\Controllers\Web;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Homework;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Rules\ExistsInCurrentSchool;
use App\Support\Teaching;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Homework: teachers post it for the subjects they teach; guardians and
 * students see it for their section. School managers can post for any.
 */
class HomeworkController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(Permission::HomeworkAssign);
        $user = $request->user();
        $all = $user->can(Permission::AcademicStructureManage);
        $sectionIds = $all ? null : Teaching::sectionIdsOf($user);

        // What the user may post for: section × subject pairs.
        $year = AcademicYear::query()->where('is_current', true)->first();
        $choices = $all
            ? Section::query()->with('gradeLevel')->when($year, fn ($q) => $q->where('academic_year_id', $year->id))
                ->orderBy('grade_level_id')->orderBy('name')->get()
                ->map(fn (Section $s) => ['section_id' => $s->id, 'label' => $s->gradeLevel->name.' / '.$s->name, 'subjects' => null])
            : Teaching::assignmentsOf($user)->groupBy('section_id')->map(fn ($list) => [
                'section_id' => $list->first()->section_id,
                'label' => $list->first()->section->gradeLevel->name.' / '.$list->first()->section->name,
                'subjects' => $list->pluck('subject_id')->all(),
            ])->values();

        return Inertia::render('Homework/Index', [
            'homework' => Homework::query()->with('section.gradeLevel', 'subject', 'author')
                ->when($sectionIds !== null, fn ($q) => $q->whereIn('section_id', $sectionIds))
                ->when($request->integer('section_id'), fn ($q, $id) => $q->where('section_id', $id))
                ->where('due_on', '>=', now()->subDays(30)->toDateString())
                ->orderByDesc('due_on')->orderByDesc('id')->limit(100)->get()
                ->map(fn (Homework $h) => self::present($h) + ['can_delete' => $all || $h->created_by === $user->id]),
            'choices' => $choices,
            'subjects' => Subject::query()->ordered()->get()->map(fn (Subject $s) => ['id' => $s->id, 'name' => $s->name]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize(Permission::HomeworkAssign);
        $data = $request->validate([
            'section_id' => ['required', 'integer', ExistsInCurrentSchool::in('sections')],
            'subject_id' => ['required', 'integer', ExistsInCurrentSchool::in('subjects')],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:5000'],
            'due_on' => ['required', 'date', 'after_or_equal:today'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,docx,pptx', 'max:10240'],
        ]);

        $user = $request->user();
        if (! $user->can(Permission::AcademicStructureManage) && ! Teaching::teachesSubject($user, $data['section_id'], $data['subject_id'])) {
            throw ValidationException::withMessages(['subject_id' => __('homework.not_your_subject')]);
        }

        $homework = new Homework(collect($data)->except('attachment')->all() + ['created_by' => $user->id]);
        if ($file = $request->file('attachment')) {
            $homework->attachment_path = $file->store('homework/'.app(CurrentSchool::class)->id(), 'local');
            $homework->attachment_name = mb_substr($file->getClientOriginalName(), 0, 200);
        }
        $homework->save();

        return back()->with('success', __('homework.posted'));
    }

    public function destroy(Request $request, Homework $homework): RedirectResponse
    {
        abort_unless($homework->created_by === $request->user()->id || $request->user()->can(Permission::AcademicStructureManage), 403);
        if ($homework->attachment_path) {
            Storage::disk('local')->delete($homework->attachment_path);
        }
        $homework->delete();

        return back()->with('success', __('Deleted.'));
    }

    /** Staff who teach the section, managers, and the families of students in it. */
    public function attachment(Request $request, Homework $homework): StreamedResponse
    {
        $user = $request->user();
        $allowed = $user->can(Permission::AcademicStructureManage)
            || in_array($homework->section_id, Teaching::sectionIdsOf($user), true)
            || Student::query()->inSection($homework->section_id)
                ->where(fn ($q) => $q->guardedBy($user)->orWhere('user_id', $user->id))->exists();
        abort_unless($allowed && $homework->attachment_path, 404);

        return Storage::disk('local')->download($homework->attachment_path, $homework->attachment_name);
    }

    /** Homework for the signed-in guardian's children (or the student themself). */
    public function mine(Request $request): Response
    {
        $user = $request->user();
        $children = Student::query()->where(fn ($q) => $q->guardedBy($user)->orWhere('user_id', $user->id))
            ->with('currentEnrollment.section.gradeLevel')->get();

        return Inertia::render('Homework/Mine', [
            'children' => $children->map(fn (Student $child) => [
                'id' => $child->id,
                'name' => $child->name,
                'homework' => $child->currentEnrollment?->section_id
                    ? Homework::query()->with('subject', 'section.gradeLevel')
                        ->where('section_id', $child->currentEnrollment->section_id)
                        ->where('due_on', '>=', now()->subDays(7)->toDateString())
                        ->orderBy('due_on')->get()->map(fn (Homework $h) => self::present($h))
                    : [],
            ]),
        ]);
    }

    /** @return array<string, mixed> */
    public static function present(Homework $h): array
    {
        return [
            'id' => $h->id,
            'title' => $h->title,
            'body' => $h->body,
            'due_on' => $h->due_on->toDateString(),
            'subject' => $h->subject->name,
            'section' => $h->section->gradeLevel->name.' / '.$h->section->name,
            'author' => $h->author?->name,
            'attachment' => $h->attachment_name ? ['name' => $h->attachment_name, 'url' => "/homework/{$h->id}/attachment"] : null,
        ];
    }
}
