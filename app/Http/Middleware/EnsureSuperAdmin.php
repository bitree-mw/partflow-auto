<?php

namespace App\Http\Middleware;

use App\Services\SuperAdminAuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function __construct(private readonly SuperAdminAuthService $superAdminAuth) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->superAdminAuth->check($request->session())) {
            // Not redirect()->guest(): that would hijack the staff login's intended URL.
            return redirect()->route('suadmin.login');
        }

        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('X-Frame-Options', 'DENY');

        return $response;
    }
}
