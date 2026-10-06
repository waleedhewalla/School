<?php

namespace App\Http\Middleware;

use App\Models\School;
use App\Support\Locale;
use App\Support\Tenancy\CurrentSchool;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the active school for tenant routes. API clients send the school
 * id or slug in the X-School header; web users keep it in the session.
 * Users with exactly one membership need not send anything.
 *
 * Runs after SetLocale, so errors here are already translated, and before
 * route model binding (see bootstrap/app.php) so bound models are already
 * filtered to the active school.
 */
class ResolveCurrentSchool
{
    public function __construct(private CurrentSchool $currentSchool) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $school = $this->requestedSchool($request) ?? $this->onlySchoolOf($request);

        if ($school === null) {
            // Browsers are sent to pick a school; API clients get an error.
            return $request->expectsJson()
                ? abort(400, __('tenancy.school_required'))
                : redirect()->route($user->platformAccessAllowed() && $user->schools()->doesntExist() ? 'platform.index' : 'schools.select');
        }
        abort_unless($user->canEnterSchool($school), 403, __('tenancy.not_a_member'));

        $this->currentSchool->set($school);

        if (! $request->attributes->get(SetLocale::EXPLICIT) && Locale::isSupported($school->default_locale)) {
            app()->setLocale($school->default_locale);
        }

        try {
            return $next($request);
        } finally {
            $this->currentSchool->forget();
        }
    }

    private function requestedSchool(Request $request): ?School
    {
        $key = $request->header(config('madrasa.school_header'))
            ?? ($request->hasSession() ? $request->session()->get('school_id') : null);

        if ($key === null || $key === '') {
            return null;
        }

        $school = School::query()
            ->where(fn ($query) => ctype_digit((string) $key)
                ? $query->whereKey((int) $key)
                : $query->where('slug', $key))
            ->first();

        // An unknown school answers the same as a school you are not in, so
        // the header cannot be used to discover which schools exist.
        abort_if($school === null, 403, __('tenancy.not_a_member'));

        return $school;
    }

    private function onlySchoolOf(Request $request): ?School
    {
        $schools = $request->user()->schools()->wherePivot('status', 'active')->limit(2)->get();

        return $schools->count() === 1 ? $schools->first() : null;
    }
}
