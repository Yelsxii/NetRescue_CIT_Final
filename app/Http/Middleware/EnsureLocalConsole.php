<?php

namespace App\Http\Middleware;

use App\Support\LocalConsole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLocalConsole
{
    public function handle(Request $request, Closure $next): Response
    {
        if (LocalConsole::allows($request)) {
            return $next($request);
        }

        abort(403, 'The NetRescue console is available only on the computer running it.');
    }
}
