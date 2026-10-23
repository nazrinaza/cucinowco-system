<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AgentSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('agent');
        $authenticatedAgent = $guard->user();
        if (! $authenticatedAgent) {
            return redirect()->route('agents.login');
        }

        // Reload the account on every request so an HQ suspension takes effect
        // immediately, including for an already-authenticated agent session.
        $agent = $authenticatedAgent->newQuery()->find($authenticatedAgent->getAuthIdentifier());
        if (! $agent) {
            $guard->logout();

            return redirect()->route('agents.login');
        }
        $guard->setUser($agent);

        return $next($request);
    }
}
