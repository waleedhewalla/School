<?php

namespace App\Http\Controllers\Web;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Jobs\SendAnnouncementSms;
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\Section;
use App\Rules\ExistsInCurrentSchool;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function index(Request $request): Response
    {
        $year = AcademicYear::query()->where('is_current', true)->first();

        return Inertia::render('Announcements/Index', [
            'announcements' => Announcement::query()->with('section.gradeLevel', 'author')->published()->limit(50)->get()
                ->map(fn (Announcement $a) => self::present($a)),
            'canManage' => $request->user()->can(Permission::AnnouncementsManage),
            'sections' => Section::query()->with('gradeLevel')
                ->when($year, fn ($q) => $q->where('academic_year_id', $year->id))
                ->orderBy('grade_level_id')->orderBy('name')->get()
                ->map(fn (Section $s) => ['id' => $s->id, 'label' => $s->gradeLevel->name.' / '.$s->name]),
            'smsEnabled' => app(CurrentSchool::class)->get()->notificationSettings()['channels']['sms'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'audience' => ['required', Rule::in(Announcement::AUDIENCES)],
            'section_id' => ['nullable', 'integer', ExistsInCurrentSchool::in('sections'), 'prohibited_if:audience,staff'],
            'send_sms' => ['boolean', 'declined_if:audience,staff'],
        ]);

        $announcement = Announcement::query()->create($data + [
            'author_id' => $request->user()->id,
            'published_at' => now(),
        ]);

        if ($announcement->send_sms) {
            SendAnnouncementSms::dispatch($announcement->school_id, $announcement->id);
        }

        return back()->with('success', __('Announcement published.'));
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return back()->with('success', __('Announcement deleted.'));
    }

    /** @return array<string, mixed> */
    public static function present(Announcement $a): array
    {
        return [
            'id' => $a->id,
            'title' => $a->title,
            'body' => $a->body,
            'audience' => $a->audience,
            'section' => $a->section ? $a->section->gradeLevel->name.' / '.$a->section->name : null,
            'author' => $a->author?->name,
            'published_at' => $a->published_at->toDateString(),
        ];
    }
}
