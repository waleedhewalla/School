<?php

namespace App\Http\Middleware;

use App\Support\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the response language. A choice the person made (?lang=, the
 * session, their profile) always wins. Otherwise the active school's
 * default applies (set later by ResolveCurrentSchool), then the browser's
 * Accept-Language, then the app default (Arabic).
 */
class SetLocale
{
    public const EXPLICIT = 'locale.explicit';

    public function handle(Request $request, Closure $next): Response
    {
        $chosen = collect([
            $request->query('lang'),
            $request->hasSession() ? $request->session()->get('locale') : null,
            $request->user()?->locale,
        ])->first(fn ($candidate) => is_string($candidate) && Locale::isSupported($candidate));

        $request->attributes->set(self::EXPLICIT, $chosen !== null);

        app()->setLocale($chosen ?? $this->fromHeader($request) ?? config('app.locale'));

        $response = $next($request);
        $response->headers->set('Content-Language', app()->getLocale());

        return $response;
    }

    private function fromHeader(Request $request): ?string
    {
        if (! $request->headers->has('Accept-Language')) {
            return null;
        }

        $preferred = $request->getPreferredLanguage(Locale::supported());

        return Locale::isSupported($preferred) ? $preferred : null;
    }
}
