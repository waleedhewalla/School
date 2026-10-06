<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Student;
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

        return Inertia::render('Portal/Children', [
            'children' => $children->map(fn (Student $child) => [
                'id' => $child->id,
                'name' => $child->name,
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
