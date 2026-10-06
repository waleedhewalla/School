<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\BehaviourIncident;
use App\Models\Student;
use App\Models\Term;
use App\Support\BehaviourScore;
use App\Support\Grades\TermResults;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Guardian home: their children, current class, and recent attendance. */
class PortalController extends Controller
{
    public function children(Request $request): Response
    {
        $children = Student::query()
            ->whereHas('guardians', fn ($q) => $q->where('guardians.user_id', $request->user()->id))
            ->with([
                'currentEnrollment.gradeLevel', 'currentEnrollment.section',
                'attendanceRecords' => fn ($q) => $q->with('code')->latest('date')->limit(10),
            ])
            ->get();

        $published = Term::query()->whereNotNull('results_published_at')
            ->whereHas('academicYear', fn ($q) => $q->where('is_current', true))->orderBy('sequence')->get();

        $year = AcademicYear::query()->where('is_current', true)->first();
        $scores = $year ? BehaviourScore::forStudents($children->pluck('id'), $year->id) : collect();
        $incidents = $year ? BehaviourIncident::query()->with('category')->whereIn('student_id', $children->pluck('id'))
            ->where('academic_year_id', $year->id)->latest('occurred_on')->get()->groupBy('student_id') : collect();

        $sectionIds = $children->map(fn (Student $c) => $c->currentEnrollment?->section_id)->filter()->unique()->values()->all();

        return Inertia::render('Portal/Children', [
            'announcements' => Announcement::query()->with('section.gradeLevel', 'author')->published()
                ->forGuardianOf($sectionIds)->limit(10)->get()
                ->map(fn (Announcement $a) => AnnouncementController::present($a)),
            'children' => $children->map(fn (Student $child) => [
                'results' => $child->currentEnrollment?->section_id ? $published->map(function (Term $term) use ($child) {
                    $section = $child->currentEnrollment->section;
                    $row = TermResults::forSection($section, $term, collect([$child]))['students'][0] ?? null;

                    return [
                        'term' => $term->name,
                        'average' => $row['average'] ?? null,
                        'grade' => $row['average_grade'] ?? null,
                        'url' => "/report-cards/{$section->id}/{$term->id}?student_id={$child->id}",
                    ];
                })->values() : [],
                'id' => $child->id,
                'name' => $child->name,
                'behaviour' => [
                    'score' => $scores[$child->id]['score'] ?? null,
                    'recent' => $incidents->get($child->id, collect())->take(5)->map(fn (BehaviourIncident $i) => [
                        'date' => $i->occurred_on->toDateString(), 'category' => $i->category->name,
                        'kind' => $i->category->kind, 'points' => $i->category->signedPoints(),
                    ])->values(),
                ],
                'student_number' => $child->student_number,
                'class' => $child->currentEnrollment
                    ? $child->currentEnrollment->gradeLevel->name.($child->currentEnrollment->section ? ' / '.$child->currentEnrollment->section->name : '')
                    : null,
                'attendance' => $child->attendanceRecords->map(fn ($r) => [
                    'date' => $r->date->toDateString(), 'period' => $r->period, 'kind' => $r->code->kind, 'name' => $r->code->name,
                ]),
            ]),
        ]);
    }
}
