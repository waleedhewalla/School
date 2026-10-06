<?php

namespace App\Http\Middleware;

use App\Models\School;
use App\Support\Locale;
use App\Support\Tenancy\CurrentSchool;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the school for public pages (the admission form and a family's
 * status page) from the {school} slug in the URL. Only active schools are
 * served. Pages carry private-link tokens, so they are not indexed and no
 * referrer leaks the link.
 */
class ResolvePublicSchool
{
    public function __construct(private CurrentSchool $currentSchool) {}

    public function handle(Request $request, Closure $next): Response
    {
        $school = School::query()->where('slug', (string) $request->route('school'))->first();
        abort_if($school === null || ! $school->isActive(), 404);

        $this->currentSchool->set($school);
        $request->route()->forgetParameter('school');

        if (! $request->attributes->get(SetLocale::EXPLICIT) && Locale::isSupported($school->default_locale)) {
            app()->setLocale($school->default_locale);
        }

        try {
            $response = $next($request);
        } finally {
            $this->currentSchool->forget();
        }

        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
