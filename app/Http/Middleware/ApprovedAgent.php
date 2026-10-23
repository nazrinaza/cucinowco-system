<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ApprovedAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        $agent = Auth::guard('agent')->user();
        if (! $agent || ! $agent->email_verified_at || $agent->status !== 'approved' || ! $agent->referralSource?->is_active) {
            return redirect()->route('agents.account');
        }

        return $next($request);
    }
}
