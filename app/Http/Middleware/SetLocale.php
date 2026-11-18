<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locales = array_keys(config('archive.locales'));
        $locale = $request->session()->get('locale') ?? $request->user()?->locale ?? config('archive.default_locale');
        app()->setLocale(in_array($locale, $locales, true) ? $locale : 'it');
        $response = $next($request);
        $response->headers->set('Content-Language', app()->getLocale());
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
