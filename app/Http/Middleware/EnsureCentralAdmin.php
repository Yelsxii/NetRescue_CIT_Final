<?php

namespace App\Http\Middleware;

use App\Support\CentralAdmin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCentralAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (CentralAdmin::allows($request)) {
            return $next($request);
        }

        abort(403, 'The NetRescue CIT administration console is restricted to the Central monitoring computer.');
    }
}
