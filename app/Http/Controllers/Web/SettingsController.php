<?php

namespace App\Http\Controllers\Web;

use App\Actions\Timetable\SavePeriods;
use App\Enums\DateDisplay;
use App\Http\Controllers\Controller;
use App\Models\AttendanceCode;
use App\Models\Campus;
use App\Models\Period;
use App\Rules\ExistsInCurrentSchool;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function periods(CurrentSchool $currentSchool): Response
    {
        return Inertia::render('Settings/Periods', [
            'periods' => Period::query()->orderBy('sequence')->get()->map(fn (Period $p) => [
                'id' => $p->id, 'name_ar' => $p->name_ar, 'name_en' => $p->name_en,
                'starts_at' => $p->startsAtShort(), 'ends_at' => $p->endsAtShort(), 'is_break' => $p->is_break,
            ]),
            'schoolDays' => $currentSchool->get()->schoolDays(),
        ]);
    }

    public function savePeriods(Request $request, SavePeriods $save, CurrentSchool $currentSchool): RedirectResponse
    {
        $data = $request->validate(SavePeriods::rules() + [
            'school_days' => ['required', 'array', 'min:1', 'max:7'],
            'school_days.*' => ['integer', 'between:0,6', 'distinct'],
        ]);

        DB::transaction(function () use ($save, $data, $currentSchool) {
            $save->handle($data['periods']);
            $days = array_map('intval', $data['school_days']);
            sort($days);
            $currentSchool->get()->update(['school_days' => $days]);
        });

        return back()->with('success', __('Changes saved.'));
    }

    public function notifications(CurrentSchool $currentSchool): Response
    {
        return Inertia::render('Settings/Notifications', [
            'settings' => $currentSchool->get()->notificationSettings(),
            'codes' => AttendanceCode::query()->orderBy('sequence')->get()
                ->map(fn (AttendanceCode $c) => ['id' => $c->id, 'name' => $c->name, 'kind' => $c->kind, 'notify_guardian' => $c->notify_guardian]),
        ]);
    }

    public function saveNotifications(Request $request, CurrentSchool $currentSchool): RedirectResponse
    {
        $data = $request->validate([
            'channels.sms' => ['required', 'boolean'],
            'channels.whatsapp' => ['required', 'boolean'],
            'channels.email' => ['required', 'boolean'],
            'quiet_hours.enabled' => ['required', 'boolean'],
            'quiet_hours.start' => ['required', 'date_format:H:i'],
            'quiet_hours.end' => ['required', 'date_format:H:i'],
            'codes' => ['array'],
            'codes.*.id' => ['required', 'integer', ExistsInCurrentSchool::inTable('attendance_codes')],
            'codes.*.notify_guardian' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($data, $currentSchool) {
            $currentSchool->get()->update(['notification_settings' => [
                'channels' => array_map('boolval', $data['channels']),
                'quiet_hours' => [
                    'enabled' => (bool) $data['quiet_hours']['enabled'],
                    'start' => $data['quiet_hours']['start'],
                    'end' => $data['quiet_hours']['end'],
                ],
            ]]);

            foreach ($data['codes'] ?? [] as $code) {
                AttendanceCode::query()->whereKey($code['id'])->update(['notify_guardian' => (bool) $code['notify_guardian']]);
            }
        });

        return back()->with('success', __('Changes saved.'));
    }

    public const TIMEZONES = ['Asia/Riyadh', 'Asia/Dubai', 'Asia/Kuwait', 'Asia/Qatar', 'Asia/Bahrain', 'Asia/Muscat'];

    public function school(CurrentSchool $currentSchool): Response
    {
        $school = $currentSchool->get();

        return Inertia::render('Settings/School', [
            'school' => $school->only(['slug', 'name_ar', 'name_en', 'ministry_code', 'default_locale', 'timezone']) + ['date_display' => $school->date_display->value],
            'campuses' => Campus::query()->withCount('sections')->get()->map(fn (Campus $c) => $c->only(['id', 'name_ar', 'name_en', 'gender', 'sections_count'])),
            'timezones' => self::TIMEZONES,
        ]);
    }

    public function saveSchool(Request $request, CurrentSchool $currentSchool): RedirectResponse
    {
        $currentSchool->get()->update($request->validate([
            'name_ar' => ['required', 'string', 'max:150'],
            'name_en' => ['nullable', 'string', 'max:150'],
            'ministry_code' => ['nullable', 'string', 'max:30'],
            'default_locale' => ['required', Rule::in(config('madrasa.locales'))],
            'date_display' => ['required', Rule::enum(DateDisplay::class)],
            'timezone' => ['required', Rule::in(self::TIMEZONES)],
        ]));

        return back()->with('success', __('Changes saved.'));
    }

    public function saveCampus(Request $request, ?Campus $campus = null): RedirectResponse
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:150'],
            'name_en' => ['nullable', 'string', 'max:150'],
            'gender' => ['required', Rule::in(['male', 'female', 'mixed'])],
        ]);
        $campus ? $campus->update($data) : Campus::query()->create($data);

        return back()->with('success', __('Changes saved.'));
    }
}
