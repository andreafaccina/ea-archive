<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateCompiler
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('archive.compiler_token');
        abort_unless(is_string($expected) && strlen($expected) >= 32, 503, 'Compiler not configured.');
        abort_unless(hash_equals($expected, $request->bearerToken() ?? ''), 401);
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
