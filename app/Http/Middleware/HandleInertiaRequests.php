<?php

namespace App\Http\Middleware;

use App\Enums\Permission;
use App\Models\Student;
use App\Support\Locale;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Props every page receives: the user, the active school, what the user
     * may do there, and the UI strings for the active language.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // Closures are resolved when the page renders, after the school
        // middleware has run; reading CurrentSchool here would be too early.
        $user = $request->user();
        $school = fn () => app(CurrentSchool::class)->get();

        return [
            ...parent::share($request),
            'locale' => fn () => app()->getLocale(),
            'dir' => fn () => Locale::direction(),
            'translations' => fn () => $this->translations(),
            'auth' => [
                'user' => $user?->only(['id', 'name', 'email', 'locale']),
                'platform' => fn () => (bool) $user?->platformAccessAllowed(),
                // A student's own login (not a guardian): the menu shows their own pages.
                'student' => fn () => $user !== null && $school() !== null && Student::query()->where('user_id', $user->id)->exists(),
            ],
            'school' => fn () => $school()?->only(['id', 'slug', 'name']),
            'schools' => fn () => $user?->schools()->wherePivot('status', 'active')->get()
                ->map(fn ($s) => $s->only(['id', 'slug', 'name']))->values() ?? [],
            'can' => fn () => $school() === null || $user === null ? [] : collect(Permission::all())
                ->mapWithKeys(fn (string $permission) => [$permission => $user->can($permission)]),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
            ],
        ];
    }

    /** @return array<string, string> the JSON strings for the active locale (English keys). */
    private function translations(): array
    {
        $path = lang_path(app()->getLocale().'.json');

        return File::exists($path) ? json_decode(File::get($path), true) : [];
    }
}
