<?php

namespace App\Http\Middleware;

use App\Services\PackageService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Blocks routes whose feature is not in this installation's subscription package (see config/packages.php).
class RequireFeature
{
    public function __construct(private readonly PackageService $packages) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $this->packages->ensure($feature);

        return $next($request);
    }
}
